<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Task;
use App\Models\Project;
use App\Models\DailyCuration;
use App\Models\DailyWeightMetric;
use App\Services\AI\Facades\AI;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DailyCurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting daily curation for user', ['user_id' => $this->user->id]);

            // Get ALL projects the user can see (owned + group projects)
            $visibleProjects = $this->getVisibleProjects();

            if ($visibleProjects->isEmpty()) {
                Log::info('No visible projects found for user', ['user_id' => $this->user->id]);
                return;
            }

            // Calculate and store daily weight metrics
            $this->calculateAndStoreWeightMetrics($visibleProjects);

            // Process each project for curation
            foreach ($visibleProjects as $project) {
                $this->curateProjectTasks($project);
            }

            Log::info('Daily curation completed for user', ['user_id' => $this->user->id]);

        } catch (\Exception $e) {
            Log::error('Daily curation failed for user', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get all projects visible to the user (owned + group projects).
     */
    protected function getVisibleProjects()
    {
        // Get user's own projects
        $ownedProjects = $this->user->projects()
            ->with(['tasks' => function ($query) {
                $query->where('status', '!=', 'completed')
                    ->orderBy('due_date', 'asc')
                    ->orderBy('created_at', 'desc');
            }])
            ->get();

        // Get group projects the user has access to
        $groupProjects = Project::whereHas('group', function ($query) {
            $query->whereHas('users', function ($userQuery) {
                $userQuery->where('user_id', $this->user->id);
            });
        })
        ->with(['tasks' => function ($query) {
            $query->where('status', '!=', 'completed')
                ->orderBy('due_date', 'asc')
                ->orderBy('created_at', 'desc');
        }])
        ->get();

        // Merge and deduplicate projects
        return $ownedProjects->merge($groupProjects)->unique('id');
    }

    /**
     * Calculate and store daily weight metrics for the user.
     */
    protected function calculateAndStoreWeightMetrics($projects): void
    {
        $today = Carbon::now();
        $totalStoryPoints = 0;
        $totalTasksCount = 0;
        $signedTasksCount = 0;
        $unsignedTasksCount = 0;
        $projectBreakdown = [];
        $sizeBreakdown = ['xs' => 0, 's' => 0, 'm' => 0, 'l' => 0, 'xl' => 0];

        foreach ($projects as $project) {
            $projectPoints = 0;
            $projectTasksCount = 0;
            $projectSignedCount = 0;
            $projectUnsignedCount = 0;

            foreach ($project->tasks as $task) {
                if ($task->status === 'completed') {
                    continue; // Skip completed tasks
                }

                $projectTasksCount++;
                $totalTasksCount++;

                if ($task->current_story_points && $task->current_story_points > 0) {
                    $points = $task->current_story_points;
                    $totalStoryPoints += $points;
                    $projectPoints += $points;
                    $projectSignedCount++;
                    $signedTasksCount++;

                    // Track by size
                    if ($task->size && isset($sizeBreakdown[$task->size])) {
                        $sizeBreakdown[$task->size] += $points;
                    }
                } else {
                    $projectUnsignedCount++;
                    $unsignedTasksCount++;
                }
            }

            // Store project breakdown
            if ($projectTasksCount > 0) {
                $projectBreakdown[] = [
                    'project_id' => $project->id,
                    'project_title' => $project->title,
                    'total_points' => $projectPoints,
                    'total_tasks' => $projectTasksCount,
                    'signed_tasks' => $projectSignedCount,
                    'unsigned_tasks' => $projectUnsignedCount,
                    'average_points' => $projectSignedCount > 0 ? round($projectPoints / $projectSignedCount, 2) : 0,
                ];
            }
        }

        // Calculate average points per task
        $averagePointsPerTask = $signedTasksCount > 0 ? round($totalStoryPoints / $signedTasksCount, 2) : 0;

        // Calculate daily velocity (average of last 7 days)
        $dailyVelocity = DailyWeightMetric::getAverageVelocity($this->user, 7);

        // Store the metrics
        DailyWeightMetric::createOrUpdate($this->user, $today, [
            'total_story_points' => $totalStoryPoints,
            'total_tasks_count' => $totalTasksCount,
            'signed_tasks_count' => $signedTasksCount,
            'unsigned_tasks_count' => $unsignedTasksCount,
            'average_points_per_task' => $averagePointsPerTask,
            'daily_velocity' => $dailyVelocity,
            'project_breakdown' => $projectBreakdown,
            'size_breakdown' => $sizeBreakdown,
        ]);

        Log::info('Daily weight metrics calculated and stored', [
            'user_id' => $this->user->id,
            'total_story_points' => $totalStoryPoints,
            'total_tasks' => $totalTasksCount,
            'signed_tasks' => $signedTasksCount,
            'unsigned_tasks' => $unsignedTasksCount,
            'average_points_per_task' => $averagePointsPerTask,
        ]);
    }

    /**
     * Curate tasks for a specific project using AI suggestions.
     */
    protected function curateProjectTasks(Project $project): void
    {
        try {
            // Get ALL incomplete tasks (pending, in-progress, and unsigned tasks)
            $incompleteTasks = $project->tasks()
                ->whereIn('status', ['pending', 'in_progress'])
                ->with('parent', 'children')
                ->get();

            // Also include unsigned tasks (tasks without story points) regardless of status
            $unsignedTasks = $project->tasks()
                ->where(function ($query) {
                    $query->whereNull('current_story_points')
                          ->orWhere('current_story_points', 0);
                })
                ->whereIn('status', ['pending', 'in_progress'])
                ->with('parent', 'children')
                ->get();

            // Merge and deduplicate
            $allTasks = $incompleteTasks->merge($unsignedTasks)->unique('id');

            if ($allTasks->isEmpty()) {
                return;
            }

            // Prepare context for AI
            $context = [
                'project' => [
                    'title' => $project->title,
                    'description' => $project->description,
                    'type' => $project->project_type,
                    'due_date' => $project->due_date?->format('Y-m-d'),
                ],
                'user' => [
                    'name' => $this->user->name,
                    'timezone' => 'UTC', // Could be enhanced with user timezone
                ],
                'current_date' => Carbon::now()->format('Y-m-d'),
                'tasks' => $allTasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'due_date' => $task->due_date?->format('Y-m-d'),
                        'size' => $task->size,
                        'story_points' => $task->current_story_points,
                        'is_parent' => $task->children->isNotEmpty(),
                        'parent_title' => $task->parent?->title,
                    ];
                })->toArray(),
            ];

            // Get AI suggestions
            $suggestions = $this->getAISuggestions($project, $context);

            // Store suggestions (could be in a dedicated table or as notifications)
            $this->storeCurationSuggestions($project, $suggestions);

        } catch (\Exception $e) {
            Log::error('Project curation failed', [
                'project_id' => $project->id,
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get AI suggestions for task curation.
     */
    protected function getAISuggestions(Project $project, array $context): array
    {
        try {
            // Check if project has AI provider configured
            if (!$project->ai_provider || !AI::hasConfiguredProvider()) {
                Log::info('No AI provider configured for project curation', [
                    'project_id' => $project->id
                ]);
                return $this->getFallbackSuggestions($context);
            }

            $prompt = $this->buildCurationPrompt($context);

            $aiResponse = AI::driver($project->ai_provider)->chat([
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $responseContent = $aiResponse->getContent();

            // Clean up the response if it's wrapped in markdown code blocks
            if (strpos($responseContent, '```json') !== false) {
                $responseContent = preg_replace('/```json\s*/', '', $responseContent);
                $responseContent = preg_replace('/\s*```/', '', $responseContent);
            }

            $suggestions = json_decode($responseContent, true);

            if (!$suggestions || !isset($suggestions['suggestions'])) {
                Log::warning('Invalid AI response for curation', [
                    'project_id' => $project->id,
                    'response' => $responseContent
                ]);
                return $this->getFallbackSuggestions($context);
            }

            return $suggestions;

        } catch (\Exception $e) {
            Log::error('AI curation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
            return $this->getFallbackSuggestions($context);
        }
    }

    /**
     * Build the curation prompt for AI.
     */
    protected function buildCurationPrompt(array $context): string
    {
        $prompt = "Please analyze the following project and tasks to provide daily curation suggestions:\n\n";

        $prompt .= "**Project:** {$context['project']['title']}\n";
        $prompt .= "**Type:** {$context['project']['type']}\n";
        $prompt .= "**User:** {$context['user']['name']}\n";
        $prompt .= "**Current Date:** {$context['current_date']}\n\n";

        if ($context['project']['due_date']) {
            $prompt .= "**Project Due Date:** {$context['project']['due_date']}\n\n";
        }

        $prompt .= "**Current Tasks:**\n";
        foreach ($context['tasks'] as $task) {
            $prompt .= "- ID: {$task['id']} - {$task['title']} ({$task['status']})";
            if ($task['due_date']) {
                $prompt .= " - Due: {$task['due_date']}";
            }
            if ($task['size']) {
                $prompt .= " - Size: {$task['size']}";
            }
            if ($task['story_points']) {
                $prompt .= " - Points: {$task['story_points']}";
            }
            $prompt .= "\n";
        }

        $prompt .= "\nPlease provide suggestions for:\n";
        $prompt .= "1. Priority tasks to focus on today\n";
        $prompt .= "2. Tasks that might be overdue or at risk\n";
        $prompt .= "3. Recommended task breakdown or optimization\n";
        $prompt .= "4. Overall project progress insights\n\n";

        $prompt .= "Respond with JSON in this format:\n";
        $prompt .= "{\n";
        $prompt .= '  "suggestions": [';
        $prompt .= '    {"type": "priority", "task_id": 123, "message": "Focus on this task today"},';
        $prompt .= '    {"type": "risk", "task_id": 456, "message": "This task is at risk of delay"},';
        $prompt .= '    {"type": "optimization", "message": "Consider breaking down large tasks"}';
        $prompt .= '  ],';
        $prompt .= '  "summary": "Brief overall assessment",';
        $prompt .= '  "focus_areas": ["area1", "area2"]';
        $prompt .= "}\n";

        return $prompt;
    }

    /**
     * Get the system prompt for AI curation.
     */
    protected function getSystemPrompt(): string
    {
        return 'You are an expert project manager and productivity advisor. Analyze user tasks and provide helpful daily curation suggestions to improve productivity and project success. Focus on actionable insights and prioritization. Always respond with valid JSON only.';
    }

    /**
     * Get fallback suggestions when AI is not available.
     */
    protected function getFallbackSuggestions(array $context): array
    {
        $suggestions = [];
        $today = Carbon::now();

        foreach ($context['tasks'] as $task) {
            // Check for overdue tasks
            if ($task['due_date'] && Carbon::parse($task['due_date'])->lt($today)) {
                $suggestions[] = [
                    'type' => 'risk',
                    'task_id' => $task['id'],
                    'message' => 'This task is overdue and needs immediate attention.'
                ];
            }
            // Check for tasks due soon
            elseif ($task['due_date'] && Carbon::parse($task['due_date'])->diffInDays($today) <= 2) {
                $suggestions[] = [
                    'type' => 'priority',
                    'task_id' => $task['id'],
                    'message' => 'This task is due soon and should be prioritized.'
                ];
            }
            // Check for in-progress tasks
            elseif ($task['status'] === 'in_progress') {
                $suggestions[] = [
                    'type' => 'priority',
                    'task_id' => $task['id'],
                    'message' => 'Continue working on this in-progress task.'
                ];
            }
            // Check for pending tasks without due dates (general suggestions)
            elseif ($task['status'] === 'pending' && !$task['due_date']) {
                $suggestions[] = [
                    'type' => 'priority',
                    'task_id' => $task['id'],
                    'message' => 'Consider working on this pending task today.'
                ];
            }
            // Check for unsigned tasks (tasks without story points)
            elseif (!$task['story_points'] || $task['story_points'] == 0) {
                $suggestions[] = [
                    'type' => 'optimization',
                    'task_id' => $task['id'],
                    'message' => 'This task needs to be sized (assigned story points) to better track progress.'
                ];
            }
        }

        // If no specific task suggestions, provide general project guidance
        if (empty($suggestions)) {
            $suggestions[] = [
                'type' => 'optimization',
                'message' => 'Review your project tasks and consider setting priorities or due dates to better organize your work.'
            ];
        }

        return [
            'suggestions' => $suggestions,
            'summary' => 'Basic task analysis completed. Consider reviewing overdue and high-priority tasks.',
            'focus_areas' => ['overdue_tasks', 'in_progress_tasks', 'pending_tasks']
        ];
    }

    /**
     * Store curation suggestions for the user.
     */
    protected function storeCurationSuggestions(Project $project, array $suggestions): void
    {
        try {
            // Store in database
            $curation = DailyCuration::createOrUpdate(
                $this->user,
                $project,
                $suggestions['suggestions'] ?? [],
                $suggestions['summary'] ?? null,
                $suggestions['focus_areas'] ?? [],
                $project->ai_provider,
                true // AI generated
            );

            Log::info('Daily curation suggestions stored', [
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'curation_id' => $curation->id,
                'suggestions_count' => count($suggestions['suggestions'] ?? []),
                'summary' => $suggestions['summary'] ?? 'No summary provided'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to store curation suggestions', [
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'suggestions' => $suggestions
            ]);

            // Still log the suggestions even if storage fails
            Log::info('Daily curation suggestions generated (storage failed)', [
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'suggestions_count' => count($suggestions['suggestions'] ?? []),
                'summary' => $suggestions['summary'] ?? 'No summary provided'
            ]);
        }
    }
}
