<?php

namespace App\Services\AI;

use App\Models\Project;
use App\Models\Task;
use App\Services\AI\Contracts\AIResponse;
use App\Services\AI\Contracts\AITaskResponse;
use App\Services\AI\Facades\AI;
use Illuminate\Support\Facades\Log;

class TaskSizingService
{
    /**
     * Automatically size a task using AI.
     */
    public function sizeTask(Task $task, ?string $provider = null, ?string $model = null): ?string
    {
        try {
            // Only size top-level tasks
            if (!$task->canHaveSize()) {
                return null;
            }

            // Get project context for better sizing
            $project = $task->project;
            $context = $this->buildSizingContext($task, $project);

            // Get AI configuration
            $aiConfig = $project->getAIConfiguration();
            $provider = $provider ?? $aiConfig['provider'] ?? config('ai.default');
            $model = $model ?? $aiConfig['model'] ?? null;

            // Validate provider availability
            $availableProviders = array_keys(AI::getAvailableProviders());
            if (!in_array($provider, $availableProviders)) {
                Log::warning('AI provider not available for task sizing', [
                    'provider' => $provider,
                    'available_providers' => $availableProviders,
                    'task_id' => $task->id,
                ]);
                return $this->getFallbackSize($task);
            }

            // Generate AI sizing
            $aiResponse = $this->generateAISizing($task, $context, $provider, $model);

            if ($aiResponse && $aiResponse->isSuccessful()) {
                $suggestedSize = $this->extractSizeFromResponse($aiResponse);
                if ($suggestedSize) {
                    Log::info('AI successfully sized task', [
                        'task_id' => $task->id,
                        'task_title' => $task->title,
                        'suggested_size' => $suggestedSize,
                        'provider' => $provider,
                    ]);
                    return $suggestedSize;
                }
            }

            // Fallback to heuristic sizing
            return $this->getFallbackSize($task);

        } catch (\Exception $e) {
            Log::error('AI task sizing failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'provider' => $provider ?? 'unknown',
            ]);

            return $this->getFallbackSize($task);
        }
    }

    /**
     * Size multiple tasks in batch.
     */
    public function sizeTasks(array $tasks, ?string $provider = null, ?string $model = null): array
    {
        $results = [];

        foreach ($tasks as $task) {
            if ($task instanceof Task) {
                $results[$task->id] = $this->sizeTask($task, $provider, $model);
            }
        }

        return $results;
    }

    /**
     * Build context for AI sizing.
     */
    protected function buildSizingContext(Task $task, Project $project): array
    {
        // Get similar tasks for context
        $similarTasks = $project->tasks()
            ->where('id', '!=', $task->id)
            ->whereNotNull('size')
            ->where('parent_id', null) // Only top-level tasks
            ->get(['title', 'description', 'size', 'status'])
            ->toArray();

        // Get project statistics
        $projectStats = [
            'total_tasks' => $project->tasks()->count(),
            'completed_tasks' => $project->tasks()->where('status', 'completed')->count(),
            'average_complexity' => $this->calculateAverageComplexity($project),
        ];

        return [
            'task' => [
                'title' => $task->title,
                'description' => $task->description ?? '',
                'status' => $task->status,
                'due_date' => $task->due_date?->format('Y-m-d'),
            ],
            'project' => [
                'title' => $project->title,
                'description' => $project->description ?? '',
                'type' => $project->project_type,
                'due_date' => $project->due_date?->format('Y-m-d'),
            ],
            'similar_tasks' => $similarTasks,
            'project_stats' => $projectStats,
        ];
    }

    /**
     * Generate AI sizing using the specified provider.
     */
    protected function generateAISizing(Task $task, array $context, string $provider, ?string $model = null): ?AITaskResponse
    {
        try {
            $systemPrompt = $this->buildSizingSystemPrompt();
            $userPrompt = $this->buildSizingUserPrompt($context);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $options = array_merge([
                'temperature' => 0.3, // Lower temperature for more consistent sizing
                'max_tokens' => 500,
            ], $model ? ['model' => $model] : []);

            $response = AI::driver($provider)->chat($messages, $options);

            if (!$response->isSuccessful()) {
                Log::warning('AI sizing request failed', [
                    'task_id' => $task->id,
                    'provider' => $provider,
                    'error' => $response->getErrorMessage(),
                ]);
                return null;
            }

            // Parse the response
            return $this->parseSizingResponse($response, $context);

        } catch (\Exception $e) {
            Log::error('AI sizing generation failed', [
                'task_id' => $task->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Build system prompt for task sizing.
     */
    protected function buildSizingSystemPrompt(): string
    {
        return 'You are an expert project manager and software development estimator. Your job is to assign T-shirt sizes (XS, S, M, L, XL) to tasks based on their complexity, scope, and estimated effort.

T-shirt size guidelines:
- XS (Extra Small): 1-2 hours, simple tasks, minor changes, quick fixes
- S (Small): 3-8 hours, straightforward tasks, small features, simple implementations
- M (Medium): 1-3 days, moderate complexity, standard features, some research needed
- L (Large): 3-7 days, complex tasks, major features, significant research or integration
- XL (Extra Large): 1-2 weeks, very complex tasks, major architectural changes, multiple dependencies

Consider these factors:
1. Task complexity and technical difficulty
2. Amount of research or learning required
3. Number of dependencies or integrations
4. Testing and quality assurance needs
5. Documentation requirements
6. Similar tasks in the project for context

Respond with ONLY the T-shirt size (XS, S, M, L, or XL) and a brief explanation in this format:
SIZE: [size]
REASON: [brief explanation]';
    }

    /**
     * Build user prompt for task sizing.
     */
    protected function buildSizingUserPrompt(array $context): string
    {
        $task = $context['task'];
        $project = $context['project'];
        $similarTasks = $context['similar_tasks'];
        $projectStats = $context['project_stats'];

        $prompt = "Please size this task:\n\n";
        $prompt .= "TASK: {$task['title']}\n";

        if (!empty($task['description'])) {
            $prompt .= "DESCRIPTION: {$task['description']}\n";
        }

        $prompt .= "STATUS: {$task['status']}\n";

        if ($task['due_date']) {
            $prompt .= "DUE DATE: {$task['due_date']}\n";
        }

        $prompt .= "\nPROJECT CONTEXT:\n";
        $prompt .= "Project: {$project['title']}\n";
        $prompt .= "Type: {$project['type']}\n";

        if (!empty($project['description'])) {
            $prompt .= "Description: {$project['description']}\n";
        }

        $prompt .= "\nPROJECT STATISTICS:\n";
        $prompt .= "Total tasks: {$projectStats['total_tasks']}\n";
        $prompt .= "Completed tasks: {$projectStats['completed_tasks']}\n";
        $prompt .= "Average complexity: {$projectStats['average_complexity']}\n";

        if (!empty($similarTasks)) {
            $prompt .= "\nSIMILAR TASKS IN PROJECT:\n";
            foreach (array_slice($similarTasks, 0, 5) as $similarTask) {
                $prompt .= "- {$similarTask['title']} (Size: {$similarTask['size']}, Status: {$similarTask['status']})\n";
            }
        }

        return $prompt;
    }

    /**
     * Parse AI response to extract size.
     */
    protected function parseSizingResponse(AIResponse $response, array $context): ?AITaskResponse
    {
        try {
            $content = trim($response->getContent());

            // Look for SIZE: pattern
            if (preg_match('/SIZE:\s*(XS|S|M|L|XL)/i', $content, $matches)) {
                $size = strtoupper($matches[1]);

                // Validate size
                if (in_array($size, ['XS', 'S', 'M', 'L', 'XL'])) {
                    return AITaskResponse::success(
                        tasks: [['size' => $size, 'reason' => $this->extractReason($content)]],
                        notes: ['AI successfully sized the task'],
                        rawResponse: $response
                    );
                }
            }

            // Fallback: look for just the size anywhere in the response
            if (preg_match('/\b(XS|S|M|L|XL)\b/i', $content, $matches)) {
                $size = strtoupper($matches[1]);
                if (in_array($size, ['XS', 'S', 'M', 'L', 'XL'])) {
                    return AITaskResponse::success(
                        tasks: [['size' => $size, 'reason' => 'AI suggested size']],
                        notes: ['AI suggested size from response'],
                        rawResponse: $response
                    );
                }
            }

            Log::warning('Could not parse size from AI response', [
                'content' => $content,
                'context' => $context,
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to parse AI sizing response', [
                'error' => $e->getMessage(),
                'content' => $response->getContent(),
            ]);
            return null;
        }
    }

    /**
     * Extract reason from AI response.
     */
    protected function extractReason(string $content): string
    {
        if (preg_match('/REASON:\s*(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'AI suggested size';
    }

    /**
     * Extract size from AI response.
     */
    protected function extractSizeFromResponse(AITaskResponse $response): ?string
    {
        $tasks = $response->getTasks();
        if (!empty($tasks) && isset($tasks[0]['size'])) {
            return $tasks[0]['size'];
        }
        return null;
    }

    /**
     * Get fallback size using heuristics.
     */
    protected function getFallbackSize(Task $task): string
    {
        $title = strtolower($task->title);
        $description = strtolower($task->description ?? '');

        // Heuristic sizing based on keywords
        $complexityKeywords = [
            'xs' => ['fix', 'bug', 'typo', 'small', 'quick', 'minor', 'update', 'change'],
            's' => ['add', 'create', 'implement', 'simple', 'basic', 'standard'],
            'm' => ['feature', 'component', 'module', 'integration', 'api', 'database'],
            'l' => ['system', 'architecture', 'refactor', 'migration', 'complex', 'major'],
            'xl' => ['rewrite', 'redesign', 'overhaul', 'platform', 'framework', 'enterprise']
        ];

        $text = $title . ' ' . $description;

        foreach ($complexityKeywords as $size => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $size;
                }
            }
        }

        // Default to medium if no keywords match
        return 'm';
    }

    /**
     * Calculate average complexity of project tasks.
     */
    protected function calculateAverageComplexity(Project $project): string
    {
        $sizedTasks = $project->tasks()
            ->whereNotNull('size')
            ->where('parent_id', null)
            ->pluck('size')
            ->toArray();

        if (empty($sizedTasks)) {
            return 'unknown';
        }

        $sizeValues = ['XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 'XL' => 5];
        $totalValue = array_sum(array_map(fn($size) => $sizeValues[$size] ?? 3, $sizedTasks));
        $averageValue = $totalValue / count($sizedTasks);

        if ($averageValue <= 1.5) return 'XS';
        if ($averageValue <= 2.5) return 'S';
        if ($averageValue <= 3.5) return 'M';
        if ($averageValue <= 4.5) return 'L';
        return 'XL';
    }
}
