<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Task;
use App\Models\Project;
use App\Models\DailyCuration;
use App\Models\DailyWeightMetric;
use App\Models\CuratedTasks;
use App\Models\CurationPrompt;
use App\Services\AI\Facades\AI;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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

            // Clear curation prompts from previous runs (morning cleanup)
            $this->clearPreviousPrompts();

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
        // Check if user belongs to a non-default organization
        $isInOrganization = $this->user->organization_id &&
                           $this->user->organization &&
                           !$this->user->organization->is_default;

        Log::info('DailyCurationJob: Processing user projects', [
            'user_id' => $this->user->id,
            'organization_id' => $this->user->organization_id,
            'is_in_organization' => $isInOrganization,
            'organization_name' => $this->user->organization?->name ?? 'None'
        ]);

        // Get user's own projects
        $ownedProjects = $this->user->projects()
            ->with(['tasks' => function ($query) use ($isInOrganization) {
                $query->where('status', '!=', 'completed');

                // For organization users, only include unassigned tasks (not yet curated)
                if ($isInOrganization) {
                    $query->whereNotExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('curated_tasks')
                            ->whereColumn('curated_tasks.curatable_id', 'tasks.id')
                            ->where('curated_tasks.curatable_type', Task::class)
                            ->where('curated_tasks.work_date', today());
                    });
                }

                $query->orderBy('due_date', 'asc')
                    ->orderBy('created_at', 'desc');
            }])
            ->get();

        // Get group projects the user has access to
        $groupProjects = Project::whereHas('group', function ($query) {
            $query->whereHas('users', function ($userQuery) {
                $userQuery->where('user_id', $this->user->id);
            });
        })
        ->with(['tasks' => function ($query) use ($isInOrganization) {
            $query->where('status', '!=', 'completed');

            // For organization users, only include unassigned tasks (not yet curated)
            if ($isInOrganization) {
                $query->whereNotExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('curated_tasks')
                        ->whereColumn('curated_tasks.curatable_id', 'tasks.id')
                        ->where('curated_tasks.curatable_type', Task::class)
                        ->where('curated_tasks.work_date', today());
                });
            }

            $query->orderBy('due_date', 'asc')
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

            // Check if user belongs to a non-default organization
            $isInOrganization = $this->user->organization_id &&
                               $this->user->organization &&
                               !$this->user->organization->is_default;

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
                'is_organization_user' => $isInOrganization,
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

            // Store the prompt for debugging and tracking
            $this->storeCurationPrompt($project, $prompt, $context);

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
        $prompt .= "**Current Date:** {$context['current_date']}\n";

        // Add organization context
        if ($context['is_organization_user']) {
            $prompt .= "**User Type:** Organization member (only suggest unassigned tasks)\n";
        } else {
            $prompt .= "**User Type:** Individual user (can suggest any tasks)\n";
        }

        $prompt .= "\n";

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

        if ($context['is_organization_user']) {
            $prompt .= "\n**IMPORTANT:** This user is in an organization. Only suggest tasks that are NOT already assigned to someone today.\n";
            $prompt .= "Please provide suggestions for:\n";
            $prompt .= "1. Unassigned priority tasks to focus on today\n";
            $prompt .= "2. Unassigned tasks that might be overdue or at risk\n";
            $prompt .= "3. Recommended task breakdown or optimization for unassigned tasks\n";
            $prompt .= "4. Overall project progress insights\n\n";
        } else {
            $prompt .= "\nPlease provide suggestions for:\n";
            $prompt .= "1. Priority tasks to focus on today\n";
            $prompt .= "2. Tasks that might be overdue or at risk\n";
            $prompt .= "3. Recommended task breakdown or optimization\n";
            $prompt .= "4. Overall project progress insights\n\n";
        }

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

            // Populate CuratedTasks table with priority tasks
            // Only call this if there are actual suggestions with task IDs
            $hasTaskSuggestions = collect($suggestions['suggestions'] ?? [])
                ->filter(function ($suggestion) {
                    return isset($suggestion['task_id']) &&
                           in_array($suggestion['type'], ['priority', 'risk']);
                })
                ->isNotEmpty();

            if ($hasTaskSuggestions) {
                $this->populateCuratedTasks($project, $suggestions);
            }

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

    /**
     * Populate CuratedTasks table with tasks that should appear on Today's Tasks page.
     */
    protected function populateCuratedTasks(Project $project, array $suggestions): void
    {
        try {
            $today = Carbon::now()->toDateString();

            // Get all tasks that were suggested for today
            $suggestedTaskIds = collect($suggestions['suggestions'] ?? [])
                ->filter(function ($suggestion) {
                    return isset($suggestion['task_id']) &&
                           in_array($suggestion['type'], ['priority', 'risk']);
                })
                ->pluck('task_id')
                ->unique()
                ->toArray();

            if (empty($suggestedTaskIds)) {
                Log::info('No priority tasks found for curation', [
                    'user_id' => $this->user->id,
                    'project_id' => $project->id
                ]);
                return;
            }

            // Check if user belongs to a non-default organization
            $isInOrganization = $this->user->organization_id &&
                               $this->user->organization &&
                               !$this->user->organization->is_default;

            // Get the tasks from database with organization filtering
            $query = Task::whereIn('id', $suggestedTaskIds);

            // For organization users, only include unassigned tasks (not yet curated)
            if ($isInOrganization) {
                $query->whereNotExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('curated_tasks')
                        ->whereColumn('curated_tasks.curatable_id', 'tasks.id')
                        ->where('curated_tasks.curatable_type', Task::class)
                        ->where('curated_tasks.work_date', today());
                });
            }

            $tasks = $query->get();

            if ($tasks->isEmpty()) {
                Log::warning('No tasks found after organization filtering', [
                    'user_id' => $this->user->id,
                    'project_id' => $project->id,
                    'suggested_task_ids' => $suggestedTaskIds,
                    'is_in_organization' => $isInOrganization,
                    'organization_name' => $this->user->organization?->name ?? 'None'
                ]);
                return;
            }

            // For organization users, only clear tasks that are being re-curated
            // For default organization users, clear all existing tasks for the project
            if ($isInOrganization) {
                // Only clear tasks that are in the current suggestion list and are being re-curated
                if ($tasks->isNotEmpty()) {
                    CuratedTasks::where('assigned_to', $this->user->id)
                        ->where('work_date', $today)
                        ->whereIn('curatable_id', $tasks->pluck('id'))
                        ->where('curatable_type', Task::class)
                        ->delete();
                }
                // If no tasks to re-curate, don't clear anything
            } else {
                // Clear existing curated tasks for today for this user and project
                CuratedTasks::where('assigned_to', $this->user->id)
                    ->where('work_date', $today)
                    ->whereHas('curatable', function ($query) use ($project) {
                        $query->where('project_id', $project->id);
                    })
                    ->delete();
            }

            // Create new curated tasks
            $curatedTasksData = [];
            foreach ($tasks as $index => $task) {
                $curatedTasksData[] = [
                    'curatable_type' => Task::class,
                    'curatable_id' => $task->id,
                    'work_date' => $today,
                    'assigned_to' => $this->user->id,
                    'initial_index' => $index + 1,
                    'current_index' => $index + 1,
                    'moved_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Batch insert for better performance
            CuratedTasks::insert($curatedTasksData);

            Log::info('CuratedTasks populated successfully', [
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'tasks_count' => count($curatedTasksData),
                'work_date' => $today
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to populate CuratedTasks', [
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Store the curation prompt for debugging and tracking.
     */
    protected function storeCurationPrompt(Project $project, string $prompt, array $context): void
    {
        try {
            CurationPrompt::create([
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'prompt_text' => $prompt,
                'ai_provider' => $project->ai_provider,
                'ai_model' => $project->ai_model ?? null,
                'is_organization_user' => $context['is_organization_user'] ?? false,
                'task_count' => count($context['tasks'] ?? []),
            ]);

            Log::info('Curation prompt stored', [
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'prompt_length' => strlen($prompt),
                'task_count' => count($context['tasks'] ?? []),
                'is_organization_user' => $context['is_organization_user'] ?? false,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to store curation prompt', [
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
        }
    }

    /**
     * Clear previous curation prompts for this user (morning cleanup).
     */
    protected function clearPreviousPrompts(): void
    {
        try {
            $deletedCount = CurationPrompt::clearForUser($this->user->id);

            Log::info('Cleared previous curation prompts', [
                'user_id' => $this->user->id,
                'deleted_count' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear previous curation prompts', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
