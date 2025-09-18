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
}
