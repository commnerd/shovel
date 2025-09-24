<?php

namespace App\Http\Controllers;

use App\Models\DailyCuration;
use App\Models\DailyWeightMetric;
use App\Models\Task;
use App\Models\Project;
use App\Models\CuratedTasks;
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

        // Get today's curated tasks for the user
        $curatedTasks = CuratedTasks::where('assigned_to', $user->id)
            ->today()
            ->with(['curatable' => function ($query) {
                $query->with(['project:id,title,project_type', 'parent:id,title']);
            }])
            ->orderBy('current_index')
            ->get();

        // Extract tasks from curated tasks
        $tasks = $curatedTasks->map(function ($curatedTask) {
            return $curatedTask->curatable;
        })->filter()->keyBy('id');

        // Get user's active projects for context
        $activeProjects = $user->projects()
            ->where('status', 'active')
            ->select('id', 'title', 'project_type', 'due_date')
            ->get();

        // Get today's weight metrics
        $weightMetrics = DailyWeightMetric::where('user_id', $user->id)
            ->forToday()
            ->first();

        $response = Inertia::render('TodaysTasks/Index', [
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
                    'is_overdue' => $task->due_date && Carbon::parse($task->due_date)->lt(Carbon::now()),
                    'days_until_due' => $task->due_date ? Carbon::now()->diffInDays(Carbon::parse($task->due_date), false) : null,
                ];
            }),
            'activeProjects' => $activeProjects,
            'stats' => [
                'total_curated_tasks' => $curatedTasks->count(),
                'pending_tasks' => $tasks->where('status', 'pending')->count(),
                'in_progress_tasks' => $tasks->where('status', 'in_progress')->count(),
                'completed_tasks' => $tasks->where('status', 'completed')->count(),
                'overdue_tasks' => $tasks->filter(function ($task) {
                    return $task->due_date && Carbon::parse($task->due_date)->lt(Carbon::now());
                })->count(),
            ],
            'weightMetrics' => $weightMetrics ? [
                'total_story_points' => $weightMetrics->total_story_points,
                'total_tasks_count' => $weightMetrics->total_tasks_count,
                'signed_tasks_count' => $weightMetrics->signed_tasks_count,
                'unsigned_tasks_count' => $weightMetrics->unsigned_tasks_count,
                'average_points_per_task' => $weightMetrics->average_points_per_task,
                'daily_velocity' => $weightMetrics->daily_velocity,
                'project_breakdown' => $weightMetrics->project_breakdown,
                'size_breakdown' => $weightMetrics->size_breakdown,
            ] : null,
            'cache_timestamp' => now()->toISOString(),
        ]);

        return $response;
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
            'timestamp' => now()->toISOString(),
        ])->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
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
            'timestamp' => now()->toISOString(),
        ])->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
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
            'timestamp' => now()->toISOString(),
        ])->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
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
            'timestamp' => now()->toISOString(),
        ])->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
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
                'curated_tasks' => CuratedTasks::where('assigned_to', $user->id)->today()->count(),
                'pending_tasks' => CuratedTasks::where('assigned_to', $user->id)->today()->whereHas('curatable', function ($query) {
                    $query->where('status', 'pending');
                })->count(),
                'in_progress_tasks' => CuratedTasks::where('assigned_to', $user->id)->today()->whereHas('curatable', function ($query) {
                    $query->where('status', 'in_progress');
                })->count(),
                'completed_tasks' => CuratedTasks::where('assigned_to', $user->id)->today()->whereHas('curatable', function ($query) {
                    $query->where('status', 'completed');
                })->count(),
            ],
            'this_week' => [
                'curated_tasks' => CuratedTasks::where('assigned_to', $user->id)
                    ->where('work_date', '>=', $today->startOfWeek()->format('Y-m-d'))
                    ->count(),
            ],
            'this_month' => [
                'curated_tasks' => CuratedTasks::where('assigned_to', $user->id)
                    ->where('work_date', '>=', $today->startOfMonth()->format('Y-m-d'))
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

        return response()->json([
            ...$stats,
            'timestamp' => now()->toISOString(),
        ])->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
