<?php

namespace App\Http\Controllers;

use App\Models\DailyCuration;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class TodaysTasksController extends Controller
{
    /**
     * Display today's curated tasks for the authenticated user.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $today = Carbon::now();

        // Get today's curations for the user
        $curations = DailyCuration::where('user_id', $user->id)
            ->forToday()
            ->active()
            ->with(['project' => function ($query) {
                $query->select('id', 'title', 'project_type');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all tasks referenced in curations
        $taskIds = $curations->flatMap(function ($curation) {
            return collect($curation->suggestions)
                ->pluck('task_id')
                ->filter();
        })->unique();

        $tasks = $taskIds->isNotEmpty() 
            ? Task::whereIn('id', $taskIds)
                ->with(['project:id,title,project_type', 'parent:id,title'])
                ->get()
                ->keyBy('id')
            : collect();

        // Get user's active projects for context
        $activeProjects = $user->projects()
            ->where('status', 'active')
            ->select('id', 'title', 'project_type', 'due_date')
            ->get();

        // Get today's priority tasks (due today or overdue)
        $priorityTasks = Task::whereHas('project', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 'active');
            })
            ->where(function ($query) use ($today) {
                $query->where('due_date', '<=', $today->format('Y-m-d'))
                    ->orWhere('status', 'in_progress');
            })
            ->where('status', '!=', 'completed')
            ->with(['project:id,title,project_type'])
            ->orderBy('due_date', 'asc')
            ->orderBy('status', 'desc') // in_progress first
            ->limit(10)
            ->get();

        // Mark curations as viewed
        $curations->each(function ($curation) {
            if ($curation->isNew()) {
                $curation->markAsViewed();
            }
        });

        return Inertia::render('TodaysTasks/Index', [
            'curations' => $curations->map(function ($curation) {
                return [
                    'id' => $curation->id,
                    'project' => [
                        'id' => $curation->project->id,
                        'title' => $curation->project->title,
                        'project_type' => $curation->project->project_type,
                    ],
                    'suggestions' => $curation->suggestions,
                    'summary' => $curation->summary,
                    'focus_areas' => $curation->focus_areas,
                    'ai_generated' => $curation->ai_generated,
                    'ai_provider' => $curation->ai_provider,
                    'is_new' => $curation->isNew(),
                    'created_at' => $curation->created_at,
                ];
            }),
            'tasks' => $tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'due_date' => $task->due_date,
                    'size' => $task->size,
                    'current_story_points' => $task->current_story_points,
                    'project' => [
                        'id' => $task->project->id,
                        'title' => $task->project->title,
                        'project_type' => $task->project->project_type,
                    ],
                    'parent' => $task->parent ? [
                        'id' => $task->parent->id,
                        'title' => $task->parent->title,
                    ] : null,
                ];
            }),
            'priorityTasks' => $priorityTasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'due_date' => $task->due_date,
                    'size' => $task->size,
                    'current_story_points' => $task->current_story_points,
                    'project' => [
                        'id' => $task->project->id,
                        'title' => $task->project->title,
                        'project_type' => $task->project->project_type,
                    ],
                    'is_overdue' => $task->due_date && Carbon::parse($task->due_date)->lt(Carbon::now()),
                    'days_until_due' => $task->due_date ? Carbon::now()->diffInDays(Carbon::parse($task->due_date), false) : null,
                ];
            }),
            'activeProjects' => $activeProjects,
            'stats' => [
                'total_curations' => $curations->count(),
                'total_suggestions' => $curations->sum(function ($curation) {
                    return count($curation->suggestions ?? []);
                }),
                'priority_tasks' => $priorityTasks->count(),
                'overdue_tasks' => $priorityTasks->filter(function ($task) {
                    return $task->due_date && Carbon::parse($task->due_date)->lt(Carbon::now());
                })->count(),
            ],
        ]);
    }

    /**
     * Dismiss a specific curation.
     */
    public function dismiss(Request $request, DailyCuration $curation): JsonResponse
    {
        // Ensure user owns this curation
        if ($curation->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $curation->dismiss();

        return response()->json([
            'success' => true,
            'message' => 'Curation dismissed successfully',
        ]);
    }

    /**
     * Mark a task as completed from the today's tasks view.
     */
    public function completeTask(Request $request, Task $task): JsonResponse
    {
        // Ensure user owns this task
        if ($task->project->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $task->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'Task marked as completed',
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
            ],
        ]);
    }

    /**
     * Update task status from the today's tasks view.
     */
    public function updateTaskStatus(Request $request, Task $task): JsonResponse
    {
        // Ensure user owns this task
        if ($task->project->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,in_progress,completed',
        ]);

        $task->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
            ],
        ]);
    }

    /**
     * Get fresh curations for today (manually trigger).
     */
    public function refresh(): JsonResponse
    {
        $user = auth()->user();

        // Dispatch curation job for immediate processing
        \App\Jobs\DailyCurationJob::dispatchSync($user);

        return response()->json([
            'success' => true,
            'message' => 'Today\'s tasks refreshed successfully',
        ]);
    }

    /**
     * Get curation statistics for the user.
     */
    public function stats(): JsonResponse
    {
        $user = auth()->user();
        $today = Carbon::now();

        $stats = [
            'today' => [
                'curations' => DailyCuration::where('user_id', $user->id)->forToday()->count(),
                'suggestions' => DailyCuration::where('user_id', $user->id)->forToday()->get()->sum(function ($curation) {
                    return count($curation->suggestions ?? []);
                }),
                'viewed' => DailyCuration::where('user_id', $user->id)->forToday()->whereNotNull('viewed_at')->count(),
            ],
            'this_week' => [
                'curations' => DailyCuration::where('user_id', $user->id)
                    ->where('curation_date', '>=', $today->startOfWeek()->format('Y-m-d'))
                    ->count(),
            ],
            'this_month' => [
                'curations' => DailyCuration::where('user_id', $user->id)
                    ->where('curation_date', '>=', $today->startOfMonth()->format('Y-m-d'))
                    ->count(),
            ],
            'active_projects' => $user->projects()->where('status', 'active')->count(),
            'pending_tasks' => Task::whereHas('project', function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('status', 'active');
            })->where('status', 'pending')->count(),
            'in_progress_tasks' => Task::whereHas('project', function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('status', 'active');
            })->where('status', 'in_progress')->count(),
        ];

        return response()->json($stats);
    }
}
