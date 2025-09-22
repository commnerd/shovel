<?php

namespace App\Http\Controllers;

use App\Models\Iteration;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class IterationsController extends Controller
{
    /**
     * Display a listing of iterations for a project.
     */
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        $iterations = $project->iterations()
            ->withCount('tasks')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($iteration) {
                return [
                    'id' => $iteration->id,
                    'name' => $iteration->name,
                    'description' => $iteration->description,
                    'start_date' => $iteration->start_date->format('Y-m-d'),
                    'end_date' => $iteration->end_date->format('Y-m-d'),
                    'status' => $iteration->status,
                    'capacity_points' => $iteration->capacity_points,
                    'committed_points' => $iteration->committed_points,
                    'completed_points' => $iteration->completed_points,
                    'sort_order' => $iteration->sort_order,
                    'goals' => $iteration->goals,
                    'tasks_count' => $iteration->tasks_count,
                    'created_at' => $iteration->created_at->toISOString(),
                    'updated_at' => $iteration->updated_at->toISOString(),
                ];
            });

        return Inertia::render('Projects/Iterations/Index', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'project_type' => $project->project_type,
                'default_iteration_length_weeks' => $project->default_iteration_length_weeks,
                'auto_create_iterations' => $project->auto_create_iterations,
            ],
            'iterations' => $iterations,
            'currentIteration' => $project->getCurrentIteration()?->toArray(),
        ]);
    }

    /**
     * Show the form for creating a new iteration.
     */
    public function create(Project $project)
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Iterations/Create', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'default_iteration_length_weeks' => $project->default_iteration_length_weeks,
            ],
        ]);
    }

    /**
     * Store a newly created iteration.
     */
    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|string|in:planned,active,completed,cancelled',
            'capacity_points' => 'nullable|integer|min:0',
            'goals' => 'nullable|array',
            'goals.*' => 'string|max:255',
        ]);

        // Get the next sort order
        $lastIteration = $project->iterations()->orderBy('sort_order', 'desc')->first();
        $nextSortOrder = $lastIteration ? $lastIteration->sort_order + 1 : 1;

        $iteration = $project->iterations()->create(array_merge($validated, [
            'sort_order' => $nextSortOrder,
            'status' => $validated['status'] ?? 'planned',
            'committed_points' => 0,
            'completed_points' => 0,
        ]));

        return redirect()->route('projects.iterations.index', $project)
            ->with('message', 'Iteration created successfully!');
    }

    /**
     * Display the specified iteration.
     */
    public function show(Project $project, Iteration $iteration)
    {
        $this->authorize('view', $project);

        $iteration->load(['tasks' => function ($query) {
            $query->orderBy('sort_order');
        }]);

        return Inertia::render('Projects/Iterations/Show', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'project_type' => $project->project_type,
            ],
            'iteration' => [
                'id' => $iteration->id,
                'name' => $iteration->name,
                'description' => $iteration->description,
                'start_date' => $iteration->start_date->format('Y-m-d'),
                'end_date' => $iteration->end_date->format('Y-m-d'),
                'status' => $iteration->status,
                'capacity_points' => $iteration->capacity_points,
                'committed_points' => $iteration->committed_points,
                'completed_points' => $iteration->completed_points,
                'sort_order' => $iteration->sort_order,
                'goals' => $iteration->goals,
                'created_at' => $iteration->created_at->toISOString(),
                'updated_at' => $iteration->updated_at->toISOString(),
            ],
            'tasks' => $iteration->tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'size' => $task->size,
                    'current_story_points' => $task->current_story_points,
                    'due_date' => $task->due_date?->format('Y-m-d'),
                    'depth' => $task->depth,
                    'is_leaf' => $task->is_leaf,
                    'parent_id' => $task->parent_id,
                    'sort_order' => $task->sort_order,
                ];
            }),
        ]);
    }

    /**
     * Show the form for editing the specified iteration.
     */
    public function edit(Project $project, Iteration $iteration)
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Iterations/Edit', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
            ],
            'iteration' => [
                'id' => $iteration->id,
                'name' => $iteration->name,
                'description' => $iteration->description,
                'start_date' => $iteration->start_date->format('Y-m-d'),
                'end_date' => $iteration->end_date->format('Y-m-d'),
                'status' => $iteration->status,
                'capacity_points' => $iteration->capacity_points,
                'goals' => $iteration->goals,
            ],
        ]);
    }

    /**
     * Update the specified iteration.
     */
    public function update(Request $request, Project $project, Iteration $iteration)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'status' => ['sometimes', 'required', Rule::in(['planned', 'active', 'completed', 'cancelled'])],
            'capacity_points' => 'nullable|integer|min:0',
            'goals' => 'nullable|array',
            'goals.*' => 'string|max:255',
        ]);

        $iteration->update($validated);

        // If status changed to active, make sure no other iteration is active
        if (isset($validated['status']) && $validated['status'] === 'active') {
            $project->iterations()
                ->where('id', '!=', $iteration->id)
                ->where('status', 'active')
                ->update(['status' => 'planned']);
        }

        // Update points if tasks are assigned
        $iteration->updatePointsFromTasks();

        return redirect()->route('projects.iterations.index', $project)
            ->with('message', 'Iteration updated successfully!');
    }

    /**
     * Remove the specified iteration.
     */
    public function destroy(Project $project, Iteration $iteration)
    {
        $this->authorize('update', $project);

        // Move all tasks to backlog
        $iteration->tasks()->update(['iteration_id' => null]);

        $iteration->delete();

        return redirect()->route('projects.iterations.index', $project)
            ->with('message', 'Iteration deleted successfully!');
    }

    /**
     * Move a task to an iteration.
     */
    public function moveTask(Request $request, Project $project, Iteration $iteration)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
        ]);

        $task = $project->tasks()->findOrFail($validated['task_id']);
        $task->moveToIteration($iteration);

        return response()->json([
            'success' => true,
            'message' => 'Task moved to iteration successfully!',
        ]);
    }

    /**
     * Remove a task from an iteration (move to backlog).
     */
    public function removeTask(Request $request, Project $project, Iteration $iteration)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
        ]);

        $task = $project->tasks()->findOrFail($validated['task_id']);
        $task->moveToBacklog();

        return response()->json([
            'success' => true,
            'message' => 'Task moved to backlog successfully!',
        ]);
    }
}
