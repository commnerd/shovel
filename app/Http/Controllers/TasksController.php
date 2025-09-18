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
        $tasksQuery = $project->tasks()->with(['parent', 'children'])->orderBy('sort_order');

        // Apply filters
        switch ($filter) {
            case 'top-level':
                $tasksQuery->topLevel();
                break;
            case 'leaf':
                $tasksQuery->leaf();
                break;
            case 'all':
            default:
                // Show all tasks
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
        ]);

        // If parent_id is provided, ensure it belongs to the same project
        if ($validated['parent_id']) {
            $parentTask = Task::find($validated['parent_id']);
            if (!$parentTask || $parentTask->project_id !== $project->id) {
                return back()->withErrors(['parent_id' => 'Invalid parent task.']);
            }
        }

        $task = Task::create([
            'project_id' => $project->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'parent_id' => $validated['parent_id'],
            'priority' => $validated['priority'],
            'status' => $validated['status'],
            'sort_order' => $project->tasks()->max('sort_order') + 1,
        ]);

        return redirect()->route('projects.tasks.index', $project)->with([
            'message' => 'Task created successfully!',
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
                'description' => $project->description,
            ],
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'parent_id' => $task->parent_id,
                'priority' => $task->priority,
                'status' => $task->status,
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
        ]);

        // If parent_id is provided, ensure it belongs to the same project and isn't self
        if ($validated['parent_id']) {
            $parentTask = Task::find($validated['parent_id']);
            if (!$parentTask || $parentTask->project_id !== $project->id || $parentTask->id === $task->id) {
                return back()->withErrors(['parent_id' => 'Invalid parent task.']);
            }
        }

        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'parent_id' => $validated['parent_id'],
            'priority' => $validated['priority'],
            'status' => $validated['status'],
        ]);

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
}
