<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Task;
use App\Models\Project;
use App\Models\DailyCuration;
use App\Models\DailyWeightMetric;
use App\Models\CuratedTasks;
use App\Models\CurationPrompt;
use App\Models\Iteration;
use App\Services\AI\Facades\AI;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserCurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected User $user;

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'user-curation-' . $this->user->id . '-' . now()->format('Y-m-d');
    }

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
            Log::info('Starting user curation for user', ['user_id' => $this->user->id]);

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

            // Get user's task completion history for the last month
            $userTaskHistory = $this->getUserTaskHistory();

            // Process each project for curation
            foreach ($visibleProjects as $project) {
                $this->curateProjectTasks($project, $userTaskHistory);
            }

            Log::info('User curation completed for user', ['user_id' => $this->user->id]);

        } catch (\Exception $e) {
            Log::error('User curation failed for user', [
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

        Log::info('UserCurationJob: Processing user projects', [
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
     * Get user's task completion history for the last month.
     */
    protected function getUserTaskHistory(): array
    {
        $oneMonthAgo = Carbon::now()->subMonth();

        // Get completed tasks from the last month
        $completedTasks = Task::whereHas('curatedTasks', function ($query) {
                $query->where('assigned_to', $this->user->id);
            })
            ->where('status', 'completed')
            ->where('updated_at', '>=', $oneMonthAgo)
            ->with(['project', 'curatedTasks' => function ($query) {
                $query->where('assigned_to', $this->user->id)
                      ->whereNotNull('completed_at')
                      ->orderBy('completed_at', 'asc');
            }])
            ->get();

        $taskTypes = [];
        $completionTimes = [];
        $storyPoints = [];
        $totalTasks = 0;

        foreach ($completedTasks as $task) {
            $totalTasks++;

            // Track task types (based on project and task characteristics)
            $taskType = $this->categorizeTaskType($task);
            $taskTypes[$taskType] = ($taskTypes[$taskType] ?? 0) + 1;

            // Track story points
            if ($task->current_story_points && $task->current_story_points > 0) {
                $storyPoints[] = $task->current_story_points;
            }

            // Calculate completion time
            $curatedTask = $task->curatedTasks->first();
            if ($curatedTask && $curatedTask->completed_at) {
                $assignedAt = $curatedTask->created_at;
                $completedAt = $curatedTask->completed_at;
                $hoursToComplete = $assignedAt->diffInHours($completedAt);
                $completionTimes[] = $hoursToComplete;
            }
        }

        // Calculate averages
        $avgCompletionTime = !empty($completionTimes) ? round(array_sum($completionTimes) / count($completionTimes), 2) : 0;
        $avgStoryPoints = !empty($storyPoints) ? round(array_sum($storyPoints) / count($storyPoints), 2) : 0;
        $totalStoryPoints = array_sum($storyPoints);

        // Get most common task types
        arsort($taskTypes);
        $topTaskTypes = array_slice(array_keys($taskTypes), 0, 5, true);

        return [
            'total_tasks_completed' => $totalTasks,
            'total_story_points' => $totalStoryPoints,
            'average_completion_time_hours' => $avgCompletionTime,
            'average_story_points' => $avgStoryPoints,
            'task_types' => $taskTypes,
            'top_task_types' => $topTaskTypes,
            'completion_times' => $completionTimes,
            'story_points' => $storyPoints,
        ];
    }

    /**
     * Categorize task type based on project and task characteristics.
     */
    protected function categorizeTaskType(Task $task): string
    {
        $project = $task->project;
        $type = 'general';

        // Categorize based on project type
        if ($project->project_type === 'finite') {
            $type = 'finite_project';
        } elseif ($project->project_type === 'iterative') {
            $type = 'iterative_project';
        }

        // Further categorize based on task characteristics
        if ($task->parent_id) {
            $type .= '_subtask';
        } else {
            $type .= '_toplevel';
        }

        // Add size-based categorization
        if ($task->size) {
            $type .= '_' . $task->size;
        }

        // Add story points categorization
        if ($task->current_story_points) {
            if ($task->current_story_points <= 2) {
                $type .= '_small';
            } elseif ($task->current_story_points <= 5) {
                $type .= '_medium';
            } else {
                $type .= '_large';
            }
        }

        return $type;
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
        try {
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
        } catch (\Exception $e) {
            \Log::error('Failed to create/update DailyWeightMetric', [
                'user_id' => $this->user->id,
                'date' => $today->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

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
    protected function curateProjectTasks(Project $project, array $userTaskHistory): void
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

            // Get leaf tasks (tasks without children)
            $leafTasks = $allTasks->filter(function ($task) {
                return $task->children->isEmpty();
            });

            if ($leafTasks->isEmpty()) {
                Log::info('No leaf tasks found for curation', [
                    'user_id' => $this->user->id,
                    'project_id' => $project->id
                ]);
                return;
            }

            // Check if user belongs to a non-default organization
            $isInOrganization = $this->user->organization_id &&
                               $this->user->organization &&
                               !$this->user->organization->is_default;

            // Get project's next iteration due date if it's an iterative project
            $nextIterationDueDate = $this->getNextIterationDueDate($project);

            // Prepare context for AI
            $context = [
                'project' => [
                    'title' => $project->title,
                    'description' => $project->description,
                    'type' => $project->project_type,
                    'due_date' => $project->due_date?->format('Y-m-d'),
                    'next_iteration_due_date' => $nextIterationDueDate,
                ],
                'user' => [
                    'name' => $this->user->name,
                    'timezone' => 'UTC', // Could be enhanced with user timezone
                ],
                'user_task_history' => $userTaskHistory,
                'current_date' => Carbon::now()->format('Y-m-d'),
                'is_organization_user' => $isInOrganization,
                'tasks' => $leafTasks->map(function ($task) {
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
                        'project_title' => $task->project->title,
                    ];
                })->toArray(),
            ];

            // Get AI suggestions
            $suggestions = $this->getAISuggestions($project, $context);

            // Store suggestions and populate curated tasks
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
     * Get the next iteration due date for iterative projects.
     */
    protected function getNextIterationDueDate(Project $project): ?string
    {
        if ($project->project_type !== 'iterative') {
            return null;
        }

        $nextIteration = Iteration::where('project_id', $project->id)
            ->where('end_date', '>', Carbon::now())
            ->orderBy('start_date', 'asc')
            ->first();

        return $nextIteration?->end_date?->format('Y-m-d');
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
     * Build the enhanced curation prompt for AI.
     */
    protected function buildCurationPrompt(array $context): string
    {
        $prompt = "Please analyze the following project and user context to provide daily curation suggestions:\n\n";

        $prompt .= "**Project Information:**\n";
        $prompt .= "- Title: {$context['project']['title']}\n";
        $prompt .= "- Type: {$context['project']['type']}\n";
        $prompt .= "- Description: {$context['project']['description']}\n";

        if ($context['project']['due_date']) {
            $prompt .= "- Project Due Date: {$context['project']['due_date']}\n";
        }

        if ($context['project']['next_iteration_due_date']) {
            $prompt .= "- Next Iteration Due Date: {$context['project']['next_iteration_due_date']}\n";
        }

        $prompt .= "\n**User Information:**\n";
        $prompt .= "- Name: {$context['user']['name']}\n";
        $prompt .= "- Current Date: {$context['current_date']}\n";

        // Add user task completion history
        $history = $context['user_task_history'];
        $prompt .= "\n**User's Recent Performance (Last Month):**\n";
        $prompt .= "- Total Tasks Completed: {$history['total_tasks_completed']}\n";
        $prompt .= "- Total Story Points Completed: {$history['total_story_points']}\n";
        $prompt .= "- Average Completion Time: {$history['average_completion_time_hours']} hours\n";
        $prompt .= "- Average Story Points per Task: {$history['average_story_points']}\n";

        if (!empty($history['top_task_types'])) {
            $prompt .= "- Top Task Types Completed: " . implode(', ', array_keys($history['top_task_types'])) . "\n";
        }

        // Add organization context
        if ($context['is_organization_user']) {
            $prompt .= "\n**User Type:** Organization member (only suggest unassigned tasks)\n";
        } else {
            $prompt .= "\n**User Type:** Individual user (can suggest any tasks)\n";
        }

        $prompt .= "\n**Available Leaf Tasks (Tasks without subtasks):**\n";
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
            if ($task['description']) {
                $prompt .= " - Description: {$task['description']}";
            }
            $prompt .= "\n";
        }

        if ($context['is_organization_user']) {
            $prompt .= "\n**IMPORTANT:** This user is in an organization. Only suggest tasks that are NOT already assigned to someone today.\n";
            $prompt .= "Please provide suggestions for:\n";
            $prompt .= "1. Unassigned priority tasks to focus on today (considering user's completion history and project deadlines)\n";
            $prompt .= "2. Unassigned tasks that match the user's proven capabilities (based on their task type history)\n";
            $prompt .= "3. Unassigned tasks that might be overdue or at risk\n";
            $prompt .= "4. Recommended task prioritization based on user's average completion time and story point preferences\n";
            $prompt .= "5. Overall project progress insights and recommendations\n\n";
        } else {
            $prompt .= "\nPlease provide suggestions for:\n";
            $prompt .= "1. Priority tasks to focus on today (considering user's completion history and project deadlines)\n";
            $prompt .= "2. Tasks that match the user's proven capabilities (based on their task type history)\n";
            $prompt .= "3. Tasks that might be overdue or at risk\n";
            $prompt .= "4. Recommended task prioritization based on user's average completion time and story point preferences\n";
            $prompt .= "5. Overall project progress insights and recommendations\n\n";
        }

        $prompt .= "Consider the user's historical performance when making recommendations:\n";
        $prompt .= "- If they typically complete {$history['average_story_points']} point tasks in {$history['average_completion_time_hours']} hours, prioritize similar tasks\n";
        $prompt .= "- If they have a strong track record with certain task types, suggest similar tasks\n";
        $prompt .= "- Consider project deadlines and iteration cycles when prioritizing\n\n";

        $prompt .= "Respond with JSON in this format:\n";
        $prompt .= "{\n";
        $prompt .= '  "suggestions": [';
        $prompt .= '    {"type": "priority", "task_id": 123, "message": "Focus on this task today - matches your proven capabilities"},';
        $prompt .= '    {"type": "risk", "task_id": 456, "message": "This task is at risk of delay and needs attention"},';
        $prompt .= '    {"type": "optimization", "message": "Consider breaking down large tasks based on your completion patterns"}';
        $prompt .= '  ],';
        $prompt .= '  "summary": "Brief overall assessment considering user performance",';
        $prompt .= '  "focus_areas": ["area1", "area2"],';
        $prompt .= '  "recommended_tasks": [123, 456, 789]';
        $prompt .= "}\n";

        return $prompt;
    }

    /**
     * Get the system prompt for AI curation.
     */
    protected function getSystemPrompt(): string
    {
        return 'You are an expert project manager and productivity advisor. Analyze user tasks and their completion history to provide personalized daily curation suggestions. Focus on matching tasks to user capabilities, considering their average completion times, preferred task types, and story point patterns. Always respond with valid JSON only.';
    }

    /**
     * Get fallback suggestions when AI is not available.
     */
    protected function getFallbackSuggestions(array $context): array
    {
        $suggestions = [];
        $recommendedTasks = [];
        $today = Carbon::now();
        $history = $context['user_task_history'];

        foreach ($context['tasks'] as $task) {
            $taskScore = 0;
            $reasoning = [];

            // Check for overdue tasks
            if ($task['due_date'] && Carbon::parse($task['due_date'])->lt($today)) {
                $taskScore += 100;
                $reasoning[] = 'overdue';
                $suggestions[] = [
                    'type' => 'risk',
                    'task_id' => $task['id'],
                    'message' => 'This task is overdue and needs immediate attention.'
                ];
            }
            // Check for tasks due soon
            elseif ($task['due_date'] && Carbon::parse($task['due_date'])->diffInDays($today) <= 2) {
                $taskScore += 80;
                $reasoning[] = 'due soon';
                $suggestions[] = [
                    'type' => 'priority',
                    'task_id' => $task['id'],
                    'message' => 'This task is due soon and should be prioritized.'
                ];
            }

            // Check for in-progress tasks
            if ($task['status'] === 'in_progress') {
                $taskScore += 60;
                $reasoning[] = 'in progress';
                $suggestions[] = [
                    'type' => 'priority',
                    'task_id' => $task['id'],
                    'message' => 'Continue working on this in-progress task.'
                ];
            }

            // Match user's historical preferences
            if ($task['story_points'] && $history['average_story_points'] > 0) {
                $pointsDiff = abs($task['story_points'] - $history['average_story_points']);
                if ($pointsDiff <= 1) {
                    $taskScore += 40;
                    $reasoning[] = 'matches user preference';
                }
            }

            // Check for unsigned tasks (tasks without story points)
            if (!$task['story_points'] || $task['story_points'] == 0) {
                $suggestions[] = [
                    'type' => 'optimization',
                    'task_id' => $task['id'],
                    'message' => 'This task needs to be sized (assigned story points) to better track progress.'
                ];
            }

            // Add to recommended tasks if score is high enough
            if ($taskScore >= 40) {
                $recommendedTasks[] = $task['id'];
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
            'summary' => "Basic task analysis completed considering user's historical performance. Recommended focusing on tasks that match their completion patterns.",
            'focus_areas' => ['overdue_tasks', 'in_progress_tasks', 'user_preferred_tasks'],
            'recommended_tasks' => $recommendedTasks
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

            // Populate CuratedTasks table with recommended tasks
            $recommendedTaskIds = $suggestions['recommended_tasks'] ?? [];

            // Also include tasks from suggestions with task IDs
            $suggestionTaskIds = collect($suggestions['suggestions'] ?? [])
                ->filter(function ($suggestion) {
                    return isset($suggestion['task_id']) &&
                           in_array($suggestion['type'], ['priority', 'risk']);
                })
                ->pluck('task_id')
                ->toArray();

            $allRecommendedTasks = array_unique(array_merge($recommendedTaskIds, $suggestionTaskIds));

            if (!empty($allRecommendedTasks)) {
                $this->populateCuratedTasks($project, $allRecommendedTasks);
            }

            Log::info('Daily curation suggestions stored', [
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'curation_id' => $curation->id,
                'suggestions_count' => count($suggestions['suggestions'] ?? []),
                'recommended_tasks_count' => count($allRecommendedTasks),
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
     * Populate CuratedTasks table with recommended tasks.
     */
    protected function populateCuratedTasks(Project $project, array $recommendedTaskIds): void
    {
        try {
            $today = Carbon::now()->toDateString();

            if (empty($recommendedTaskIds)) {
                Log::info('No recommended tasks found for curation', [
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
            $query = Task::whereIn('id', $recommendedTaskIds);

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
                    'recommended_task_ids' => $recommendedTaskIds,
                    'is_in_organization' => $isInOrganization,
                    'organization_name' => $this->user->organization?->name ?? 'None'
                ]);
                return;
            }

            // For organization users, only clear tasks that are being re-curated
            // For default organization users, clear all existing tasks for the project
            if ($isInOrganization) {
                // Only clear tasks that are in the current recommendation list and are being re-curated
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
