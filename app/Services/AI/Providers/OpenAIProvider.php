<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\{AIProviderInterface, AIResponse, AITaskResponse};
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected array $defaultOptions;
    protected array $config;
    protected ?string $organization;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->apiKey = $config['api_key'] ?? throw new \InvalidArgumentException('OpenAI API key is required');
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->organization = $config['organization'] ?? null;

        $this->defaultOptions = [
            'model' => $config['model'] ?? 'gpt-4',
            'temperature' => $config['temperature'] ?? 0.7,
            'max_tokens' => $config['max_tokens'] ?? 4000,
            'timeout' => $config['timeout'] ?? 30,
        ];
    }

    public function chat(array $messages, array $options = []): AIResponse
    {
        try {
            $payload = array_merge($this->defaultOptions, $options, [
                'messages' => $messages,
            ]);

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $response = Http::withHeaders($headers)
                ->timeout($payload['timeout'])
                ->post($this->baseUrl . '/chat/completions', $payload);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? 'OpenAI API request failed';
                $this->logError('chat', $error, $response->status());
                throw new \Exception($error);
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            $this->logRequest('chat', $messages, $content,
                $data['usage']['total_tokens'] ?? 0,
                $data['model'] ?? null
            );

            return new AIResponse(
                content: $content,
                metadata: $data,
                model: $data['model'] ?? null,
                tokensUsed: $data['usage']['total_tokens'] ?? 0,
                responseTime: 0
            );

        } catch (\Exception $e) {
            $this->logError('chat', $e->getMessage());
            throw $e;
        }
    }

    public function generateTasks(string $projectDescription, array $schema = [], array $options = []): AITaskResponse
    {
        try {
            $systemPrompt = $this->buildTaskGenerationSystemPrompt();
            $userPrompt = $this->buildTaskGenerationUserPrompt($projectDescription);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->chat($messages, array_merge([
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ], $options));

            if (!$response->isSuccessful()) {
                $this->logError('task_generation', $response->getErrorMessage() ?? 'Unknown error');
                return $this->createFallbackTaskGeneration($projectDescription);
            }

            $content = $this->cleanResponseContent($response->getContent());
            $this->logRequest('task_generation', [$projectDescription], $content, 0, null);

            return $this->parseTaskGenerationResponse($content, $projectDescription);

        } catch (\Exception $e) {
            $this->logError('task_generation', $e->getMessage());
            return $this->createFallbackTaskGeneration($projectDescription);
        }
    }

    public function breakdownTask(string $taskTitle, string $taskDescription, array $context = [], array $options = []): AITaskResponse
    {
        try {
            $systemPrompt = $this->buildTaskBreakdownSystemPrompt();
            $userPrompt = $this->buildTaskBreakdownUserPrompt($taskTitle, $taskDescription, $context);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->chat($messages, array_merge([
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ], $options));

            if (!$response->isSuccessful()) {
                $this->logError('task_breakdown', $response->getErrorMessage() ?? 'Unknown error');
                return $this->createFallbackTaskBreakdown($taskTitle, $taskDescription);
            }

            $content = $this->cleanResponseContent($response->getContent());
            $this->logRequest('task_breakdown', [$taskTitle, $taskDescription], $content, 0, null);

            return $this->parseTaskBreakdownResponse($content, $taskTitle, $taskDescription);

        } catch (\Exception $e) {
            $this->logError('task_breakdown', $e->getMessage());
            return $this->createFallbackTaskBreakdown($taskTitle, $taskDescription);
        }
    }

    public function analyzeProject(string $projectDescription, array $existingTasks = [], array $options = []): string
    {
        $systemPrompt = config('ai.prompts.project_analysis.system',
            'You are a senior project consultant who analyzes project requirements and provides strategic insights.'
        );

        $userPrompt = str_replace('{description}', $projectDescription,
            config('ai.prompts.project_analysis.user',
                'Analyze this project: "{description}". Provide insights about scope, complexity, timeline estimates, potential risks, and recommended technologies.'
            )
        );

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $response = $this->chat($messages, $options);
        return $response->isSuccessful() ? $response->getContent() : 'Analysis unavailable due to API error.';
    }

    public function suggestTaskImprovements(array $tasks, array $options = []): array
    {
        $systemPrompt = config('ai.prompts.task_suggestions.system',
            'You are an AI assistant that helps improve task management by suggesting optimizations and next steps.'
        );

        $tasksJson = json_encode($tasks, JSON_PRETTY_PRINT);
        $userPrompt = str_replace('{tasks}', $tasksJson,
            config('ai.prompts.task_suggestions.user',
                'Given these existing tasks: {tasks}, suggest improvements, identify missing tasks, or recommend task prioritization changes.'
            )
        );

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $response = $this->chat($messages, $options);

        if ($response->isSuccessful()) {
            // Parse the response into an array of suggestions
            $content = $response->getContent();
            return array_filter(array_map('trim', explode("\n", $content)));
        }

        return ['Unable to generate suggestions due to API error.'];
    }

    public function testConnection(): array
    {
        try {
            $messages = [['role' => 'user', 'content' => 'Hello, this is a connection test.']];
            $response = $this->chat($messages, ['max_tokens' => 50]);

            if ($response->isSuccessful()) {
                return [
                    'success' => true,
                    'message' => 'OpenAI connection successful',
                    'response' => $response->getContent(),
                ];
            }

            return [
                'success' => false,
                'message' => 'OpenAI connection failed',
                'error' => $response->getErrorMessage(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'OpenAI connection test failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    // Protected helper methods
    protected function buildTaskGenerationSystemPrompt(): string
    {
        $currentDateTime = now()->format('l, F j, Y \a\t g:i A T');

        $basePrompt = config('ai.prompts.task_generation.system',
            'You are an expert project manager and task breakdown specialist. Your job is to analyze project descriptions, create compelling project titles, and generate comprehensive, actionable task lists. You must respond with valid JSON only - no additional text, explanations, or markdown formatting.'
        );

        return $basePrompt . "\n\nCurrent date and time: {$currentDateTime}\nUse this temporal context when suggesting deadlines, timeframes, or time-sensitive considerations.";
    }

    protected function buildTaskGenerationUserPrompt(string $projectDescription): string
    {
        $currentDateTime = now()->format('l, F j, Y \a\t g:i A T');

        $basePrompt = str_replace('{description}', $projectDescription,
            config('ai.prompts.task_generation.user',
                'Based on this project description: "{description}", create a compelling project title and detailed task breakdown.'
            )
        );

        return "**Current Context:**\nDate and time: {$currentDateTime}\n\n" . $basePrompt;
    }

    protected function buildTaskBreakdownSystemPrompt(): string
    {
        $currentDateTime = now()->format('l, F j, Y \a\t g:i A T');

        $basePrompt = config('ai.prompts.task_breakdown.system',
            'You are an expert project manager and task breakdown specialist. Your job is to analyze a given task and break it down into smaller, actionable subtasks. Consider the project context, existing tasks, and completion statuses to provide relevant and practical subtask suggestions.'
        );

        return $basePrompt . "\n\nCurrent date and time: {$currentDateTime}\nUse this temporal context when suggesting deadlines, timeframes, or time-sensitive considerations.";
    }

    protected function buildTaskBreakdownUserPrompt(string $taskTitle, string $taskDescription, array $context): string
    {
        $basePrompt = config('ai.prompts.task_breakdown.user',
            'Please break down the following task into smaller, actionable subtasks:'
        );

        $currentDateTime = now()->format('l, F j, Y \a\t g:i A T');
        $prompt = $basePrompt . "\n\n";
        $prompt .= "**Current Context:**\n";
        $prompt .= "Date and time: {$currentDateTime}\n\n";
        $prompt .= "**Task to Break Down:**\n";
        $prompt .= "Title: {$taskTitle}\n";
        $prompt .= "Description: {$taskDescription}\n\n";

        // Add user feedback if provided
        if (!empty($context['user_feedback'])) {
            $prompt .= "**User Feedback for Improvement:**\n";
            $prompt .= $context['user_feedback'] . "\n\n";
            $prompt .= "Please incorporate this feedback to improve the task breakdown.\n\n";
        }

        // Add project context
        if (!empty($context['project'])) {
            $project = $context['project'];
            $prompt .= "**Project Context:**\n";
            $prompt .= "Project: {$project['title']}\n";
            $prompt .= "Description: {$project['description']}\n\n";
        }

        // Add parent task context if this is a subtask
        if (!empty($context['parent_task'])) {
            $parent = $context['parent_task'];
            $prompt .= "**Parent Task:**\n";
            $prompt .= "Title: {$parent['title']}\n";
        }

        // Add existing tasks context
        if (!empty($context['existing_tasks'])) {
            $prompt .= "**Existing Project Tasks (for context):**\n";
            foreach (array_slice($context['existing_tasks'], 0, 5) as $task) {
                $prompt .= "- {$task['title']} ({$task['status']})\n";
            }
            $prompt .= "\n";
        }

        // Add task statistics
        if (!empty($context['task_stats'])) {
            $stats = $context['task_stats'];
            $prompt .= "**Project Progress:**\n";
            $prompt .= "Total tasks: {$stats['total']}, Completed: {$stats['completed']}\n\n";
        }

        $prompt .= "Please provide 2-5 specific, actionable subtasks that would help complete this main task. ";
        $prompt .= "Each subtask should be clear, measurable, and logically ordered. ";
        $prompt .= "Format your response as a simple list of subtask titles and descriptions.";

        return $prompt;
    }

    protected function cleanResponseContent(string $content): string
    {
        // Remove markdown code blocks
        $content = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $content);

        // Remove leading/trailing whitespace
        return trim($content);
    }

    protected function parseTaskGenerationResponse(string $content, string $projectDescription): AITaskResponse
    {
        try {
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from OpenAI');
            }

            $tasks = $data['tasks'] ?? [];
            $projectTitle = $data['project_title'] ?? 'Untitled Project';

            // Add service information to notes
            $notes = $data['notes'] ?? [];
            $notes[] = "Generated using OpenAI ({$this->defaultOptions['model']})";

            return AITaskResponse::success(
                tasks: $tasks,
                projectTitle: $projectTitle,
                notes: $notes,
                summary: $data['summary'] ?? '',
                problems: $data['problems'] ?? [],
                suggestions: $data['suggestions'] ?? []
            );

        } catch (\Exception $e) {
            $this->logError('parse_task_generation', $e->getMessage());
            return $this->createFallbackTaskGeneration($projectDescription);
        }
    }

    protected function parseTaskBreakdownResponse(string $content, string $taskTitle, string $taskDescription): AITaskResponse
    {
        try {
            // Try to parse as JSON first
            $data = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($data['subtasks'])) {
                $tasks = $data['subtasks'];
            } else {
                // Parse as plain text list
                $tasks = $this->parseTextListToTasks($content);
            }

            // Add service information to notes
            $notes = ["Generated using OpenAI ({$this->defaultOptions['model']})"];

            return AITaskResponse::success(
                tasks: $tasks,
                projectTitle: null,
                notes: $notes,
                summary: "Task breakdown for: {$taskTitle}",
                problems: [],
                suggestions: []
            );

        } catch (\Exception $e) {
            $this->logError('parse_task_breakdown', $e->getMessage());
            return $this->createFallbackTaskBreakdown($taskTitle, $taskDescription);
        }
    }

    protected function parseTextListToTasks(string $content): array
    {
        $tasks = [];
        $lines = array_filter(array_map('trim', explode("\n", $content)));

        foreach ($lines as $line) {
            if (preg_match('/^\d+\.\s*(.+)/', $line, $matches) ||
                preg_match('/^[-*]\s*(.+)/', $line, $matches)) {

                $title = trim($matches[1]);
                if (!empty($title)) {
                    $tasks[] = [
                        'title' => $title,
                        'description' => '',
                        'status' => 'pending',
                    ];
                }
            }
        }

        return $tasks;
    }

    protected function createFallbackTaskGeneration(string $projectDescription): AITaskResponse
    {
        $tasks = [
            [
                'title' => 'Project Planning',
                'description' => 'Define project requirements, scope, and timeline',
                'status' => 'pending',
            ],
            [
                'title' => 'Initial Setup',
                'description' => 'Set up development environment and basic project structure',
                'status' => 'pending',
            ],
        ];

        return AITaskResponse::success(
            tasks: $tasks,
            projectTitle: 'Fallback Project',
            notes: ['OpenAI service temporarily unavailable'],
            summary: 'Fallback task generation due to API error',
            problems: ['API connection failed'],
            suggestions: ['Try again later or check API configuration']
        );
    }

    protected function createFallbackTaskBreakdown(string $taskTitle, string $taskDescription): AITaskResponse
    {
        $tasks = [
            [
                'title' => 'Research and Planning',
                'description' => "Research requirements and plan approach for: {$taskTitle}",
                'priority' => 'medium',
                'status' => 'pending',
            ],
            [
                'title' => 'Implementation',
                'description' => "Implement the main functionality for: {$taskTitle}",
                'priority' => 'medium',
                'status' => 'pending',
            ],
        ];

        return AITaskResponse::success(
            tasks: $tasks,
            projectTitle: null,
            notes: ['OpenAI service temporarily unavailable'],
            summary: "Fallback breakdown for: {$taskTitle}",
            problems: ['API connection failed'],
            suggestions: ['Try again later or check API configuration']
        );
    }

    protected function logRequest(string $operation, array $input, string $output, int $tokens = 0, ?string $model = null): void
    {
        if (config('ai.logging.enabled') && config('ai.logging.log_requests')) {
            Log::channel(config('ai.logging.channel', 'daily'))->info('OpenAI API Request', [
                'provider' => 'openai',
                'operation' => $operation,
                'model' => $model ?? $this->defaultOptions['model'],
                'tokens' => $tokens,
                'input_length' => strlen(json_encode($input)),
                'output_length' => strlen($output),
            ]);
        }
    }

    protected function logError(string $operation, string $error, ?int $statusCode = null): void
    {
        if (config('ai.logging.enabled') && config('ai.logging.log_errors')) {
            Log::channel(config('ai.logging.channel', 'daily'))->error('OpenAI API Error', [
                'provider' => 'openai',
                'operation' => $operation,
                'error' => $error,
                'status_code' => $statusCode,
            ]);
        }
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
