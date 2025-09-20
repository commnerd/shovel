<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TasksController extends Controller
{
    /**
     * Display tasks for a specific project.
     */
    public function index(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        // Get filter parameter (default to 'all')
        $filter = $request->get('filter', 'all');

        // Build base query
        $tasksQuery = $project->tasks()->with(['parent', 'children']);

        // Apply filters and ordering
        switch ($filter) {
            case 'top-level':
                $tasksQuery->topLevel()->orderBy('sort_order');
                break;
            case 'leaf':
                $tasksQuery->leaf()->orderBy('sort_order');
                break;
            case 'all':
            default:
                // For 'all' tasks, order hierarchically using path and sort_order
                // This ensures parent tasks appear before their children
                $tasksQuery->orderByRaw('COALESCE(path, CAST(id AS CHAR)) ASC, sort_order ASC');
                break;
        }

        $tasks = $tasksQuery->get()->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'parent_id' => $task->parent_id,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'has_children' => $task->children->count() > 0,
                'depth' => $task->getDepth(),
                'is_top_level' => $task->isTopLevel(),
                'is_leaf' => $task->isLeaf(),
                'created_at' => $task->created_at->toISOString(),
            ];
        });

        return Inertia::render('Projects/Tasks/Index', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'due_date' => $project->due_date?->format('Y-m-d'),
                'status' => $project->status,
            ],
            'tasks' => $tasks,
            'filter' => $filter,
            'taskCounts' => [
                'all' => $project->tasks()->count(),
                'top_level' => $project->tasks()->topLevel()->count(),
                'leaf' => $project->tasks()->leaf()->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new task.
     */
    public function create(Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        // Get potential parent tasks (top-level tasks only for simplicity)
        $parentTasks = $project->tasks()->topLevel()->get()->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
            ];
        });

        return Inertia::render('Projects/Tasks/Create', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
            ],
            'parentTasks' => $parentTasks,
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:tasks,id',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:pending,in_progress,completed',
            'due_date' => 'nullable|date|after_or_equal:today',
            'subtasks' => 'nullable|array',
            'subtasks.*.title' => 'required|string|max:255',
            'subtasks.*.description' => 'nullable|string|max:1000',
            'subtasks.*.priority' => 'required|in:low,medium,high',
            'subtasks.*.status' => 'required|in:pending,in_progress,completed',
            'subtasks.*.due_date' => 'nullable|date|after_or_equal:today',
        ]);

        // If parent_id is provided, ensure it belongs to the same project
        if (! empty($validated['parent_id'])) {
            $parentTask = Task::find($validated['parent_id']);
            if (! $parentTask || $parentTask->project_id !== $project->id) {
                return back()->withErrors(['parent_id' => 'Invalid parent task.']);
            }
        }

        // Determine sort order based on parent
        $sortOrder = ! empty($validated['parent_id'])
            ? Task::find($validated['parent_id'])->getNextChildSortOrder()
            : $project->tasks()->whereNull('parent_id')->max('sort_order') + 1;

        $task = Task::create([
            'project_id' => $project->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'],
            'due_date' => $validated['due_date'] ?? null,
            'sort_order' => $sortOrder,
        ]);

        // Update hierarchy path and depth
        $task->updateHierarchyPath();

        // Create subtasks if provided
        if (! empty($validated['subtasks'])) {
            foreach ($validated['subtasks'] as $index => $subtaskData) {
                $subtask = Task::create([
                    'project_id' => $project->id,
                    'parent_id' => $task->id,
                    'title' => $subtaskData['title'],
                    'description' => $subtaskData['description'] ?? null,
                    'priority' => $subtaskData['priority'],
                    'status' => $subtaskData['status'],
                    'due_date' => $subtaskData['due_date'] ?? null,
                    'sort_order' => $index + 1,
                ]);

                $subtask->updateHierarchyPath();
            }
        }

        $message = ! empty($validated['subtasks'])
            ? 'Task created successfully with '.count($validated['subtasks']).' subtasks!'
            : 'Task created successfully!';

        return redirect()->route('projects.tasks.index', $project)->with([
            'message' => $message,
        ]);
    }

    /**
     * Show the form for editing the specified task.
     */
    public function edit(Project $project, Task $task)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        // Ensure the task belongs to the project
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found in this project.');
        }

        // Get potential parent tasks (exclude self and descendants)
        $parentTasks = $project->tasks()
            ->topLevel()
            ->where('id', '!=', $task->id)
            ->get()
            ->map(function ($parentTask) {
                return [
                    'id' => $parentTask->id,
                    'title' => $parentTask->title,
                ];
            });

        return Inertia::render('Projects/Tasks/Edit', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
            ],
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'parent_id' => $task->parent_id,
                'priority' => $task->priority,
                'status' => $task->status,
                'due_date' => $task->due_date?->format('Y-m-d'),
            ],
            'parentTasks' => $parentTasks,
        ]);
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, Project $project, Task $task)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        // Ensure the task belongs to the project
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found in this project.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:tasks,id',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:pending,in_progress,completed',
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);

        // If parent_id is provided, ensure it belongs to the same project and isn't self
        if (! empty($validated['parent_id'])) {
            $parentTask = Task::find($validated['parent_id']);
            if (! $parentTask || $parentTask->project_id !== $project->id || $parentTask->id === $task->id) {
                return back()->withErrors(['parent_id' => 'Invalid parent task.']);
            }

            // Check for circular references - prevent setting a descendant as parent
            $current = $parentTask;
            while ($current && $current->parent_id) {
                if ($current->parent_id === $task->id) {
                    return back()->withErrors(['parent_id' => 'Cannot create circular reference.']);
                }
                $current = $current->parent;
            }
        }

        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'],
            'due_date' => $validated['due_date'] ?? null,
        ]);

        // Update hierarchy path and depth if parent changed
        $task->updateHierarchyPath();

        return redirect()->route('projects.tasks.index', $project)->with([
            'message' => 'Task updated successfully!',
        ]);
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Project $project, Task $task)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        // Ensure the task belongs to the project
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found in this project.');
        }

        $task->delete();

        return redirect()->route('projects.tasks.index', $project)->with([
            'message' => 'Task deleted successfully!',
        ]);
    }

    /**
     * Show the form for creating a subtask of the specified task.
     */
    public function createSubtask(Project $project, Task $task)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        // Ensure the parent task belongs to the project
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found in this project.');
        }

        return Inertia::render('Projects/Tasks/Create', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
            ],
            'parentTask' => [
                'id' => $task->id,
                'title' => $task->title,
            ],
            'parentTasks' => [], // Empty since parent is pre-selected
        ]);
    }

    /**
     * Show the AI breakdown page for a specific task.
     */
    public function showBreakdown(Project $project, Task $task)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        // Ensure the task belongs to the project
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found in this project.');
        }

        return Inertia::render('Projects/Tasks/Breakdown', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
            ],
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'parent_id' => $task->parent_id,
                'has_children' => $task->children->count() > 0,
                'is_leaf' => $task->isLeaf(),
                'is_top_level' => $task->isTopLevel(),
                'depth' => $task->depth,
            ],
            'projectTaskCount' => $project->tasks()->count(),
        ]);
    }

    /**
     * Generate AI-powered task breakdown suggestions.
     */
    public function generateTaskBreakdown(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'user_feedback' => 'nullable|string|max:2000',
        ]);

        try {
            // Gather project context
            $existingTasks = $project->tasks()->get()->map(function ($task) {
                return [
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'is_leaf' => $task->isLeaf(),
                    'has_children' => $task->children->count() > 0,
                ];
            })->toArray();

            // Calculate task statistics
            $taskStats = [
                'total' => $project->tasks()->count(),
                'completed' => $project->tasks()->where('status', 'completed')->count(),
                'in_progress' => $project->tasks()->where('status', 'in_progress')->count(),
                'pending' => $project->tasks()->where('status', 'pending')->count(),
                'leaf_tasks' => $project->tasks()->leaf()->count(),
                'parent_tasks' => $project->tasks()->withChildren()->count(),
            ];

            $context = [
                'project' => [
                    'title' => $project->title,
                    'description' => $project->description,
                    'due_date' => $project->due_date?->format('Y-m-d'),
                    'status' => $project->status,
                ],
                'existing_tasks' => $existingTasks,
                'task_stats' => $taskStats,
                'user_feedback' => $validated['user_feedback'] ?? null,
            ];

            $aiResponse = \App\Services\AI\Facades\AI::breakdownTask(
                $validated['title'],
                $validated['description'] ?? '',
                $context
            );

            return response()->json([
                'success' => $aiResponse->isSuccessful(),
                'subtasks' => $aiResponse->getTasks(),
                'notes' => $aiResponse->getNotes(),
                'ai_used' => true,
            ]);

        } catch (\Exception $e) {
            \Log::error('Task breakdown failed', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'task_title' => $validated['title'],
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate task breakdown. Please try again.',
                'subtasks' => [],
                'notes' => ['AI service temporarily unavailable'],
                'ai_used' => false,
            ], 500);
        }
    }
}
