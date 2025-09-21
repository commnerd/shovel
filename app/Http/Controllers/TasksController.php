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

        // Get filter parameter (default to 'top-level' for List view)
        $filter = $request->get('filter', 'top-level');

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
            case 'board':
                // For board view, get all tasks ordered by status then priority for Kanban
                // Use CASE statements for SQLite compatibility
                $tasksQuery->orderByRaw("CASE
                                           WHEN status = 'pending' THEN 1
                                           WHEN status = 'in_progress' THEN 2
                                           WHEN status = 'completed' THEN 3
                                           ELSE 4
                                         END")
                          ->orderByRaw("CASE
                                         WHEN priority = 'high' THEN 1
                                         WHEN priority = 'medium' THEN 2
                                         WHEN priority = 'low' THEN 3
                                         ELSE 4
                                       END")
                          ->orderBy('sort_order');
                break;
            case 'all':
            default:
                // For 'all' tasks, we need to order hierarchically but respect sort_order within levels
                // First get all tasks, then sort them properly in PHP to maintain hierarchy
                $tasksQuery->orderBy('sort_order');
                break;
        }

        $tasks = $tasksQuery->get();

        // For 'all' filter, sort hierarchically while respecting sort_order within levels
        if ($filter === 'all') {
            $tasks = $this->sortTasksHierarchically($tasks);
        }


        $tasks = $tasks->map(function ($task) {
            // Initialize order tracking if not set
            $task->initializeOrderTracking();

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
                'sort_order' => $task->sort_order,
                'initial_order_index' => $task->initial_order_index,
                'move_count' => $task->move_count,
                'current_order_index' => $task->current_order_index,
                'completion_percentage' => $task->getCompletionPercentage(),
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
                'priority' => $task->priority,
                'priority_level' => $task->getPriorityLevel(),
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

            // Validate parent priority constraint
            $tempTask = new Task(['parent_id' => $validated['parent_id']]);
            $tempTask->setRelation('parent', $parentTask);
            $priorityValidation = $tempTask->validateParentPriorityConstraint($validated['priority']);

            if (!$priorityValidation['valid']) {
                return back()->withErrors(['priority' => $priorityValidation['error']]);
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
                // Validate subtask priority against parent
                $tempSubtask = new Task(['parent_id' => $task->id]);
                $tempSubtask->setRelation('parent', $task);
                $subtaskPriorityValidation = $tempSubtask->validateParentPriorityConstraint($subtaskData['priority']);

                if (!$subtaskPriorityValidation['valid']) {
                    return back()->withErrors([
                        "subtasks.{$index}.priority" => $subtaskPriorityValidation['error']
                    ]);
                }

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
                    'priority' => $parentTask->priority,
                    'priority_level' => $parentTask->getPriorityLevel(),
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

            // Validate parent priority constraint
            $tempTask = new Task(['parent_id' => $validated['parent_id']]);
            $tempTask->setRelation('parent', $parentTask);
            $priorityValidation = $tempTask->validateParentPriorityConstraint($validated['priority']);

            if (!$priorityValidation['valid']) {
                return back()->withErrors(['priority' => $priorityValidation['error']]);
            }
        }

        // Also validate if we're changing an existing task's priority
        if (!empty($validated['parent_id']) || $task->parent_id) {
            $parentTask = !empty($validated['parent_id'])
                ? Task::find($validated['parent_id'])
                : $task->parent;

            if ($parentTask) {
                $tempTask = new Task(['parent_id' => $parentTask->id]);
                $tempTask->setRelation('parent', $parentTask);
                $priorityValidation = $tempTask->validateParentPriorityConstraint($validated['priority']);

                if (!$priorityValidation['valid']) {
                    return back()->withErrors(['priority' => $priorityValidation['error']]);
                }
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
     * Update task status (AJAX endpoint).
     */
    public function updateStatus(Request $request, Project $project, Task $task)
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
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        // Prevent parent tasks from being marked completed directly
        if (!$task->isLeaf() && $validated['status'] === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Parent tasks cannot be marked completed directly. Complete all child tasks instead.',
            ], 400);
        }

        $oldStatus = $task->status;
        $task->update(['status' => $validated['status']]);

        // Manually trigger parent status update if this task has a parent
        // This ensures the parent status is recalculated even if the boot event doesn't fire
        if ($task->parent_id && $oldStatus !== $validated['status']) {
            $task->updateParentStatus();
        }

        return response()->json([
            'success' => true,
            'message' => "Task marked as {$validated['status']}!",
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'title' => $task->title,
            ],
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
                'priority' => $task->priority,
                'priority_level' => $task->getPriorityLevel(),
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
            'parent_task_id' => 'nullable|exists:tasks,id',
        ]);

        try {
            // Get parent task if provided
            $parentTask = null;
            if (!empty($validated['parent_task_id'])) {
                $parentTask = Task::find($validated['parent_task_id']);
                if (!$parentTask || $parentTask->project_id !== $project->id) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid parent task.',
                    ], 400);
                }
            }

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
                'parent_task' => $parentTask ? [
                    'title' => $parentTask->title,
                    'priority' => $parentTask->priority,
                    'priority_level' => $parentTask->getPriorityLevel(),
                ] : null,
                'existing_tasks' => $existingTasks,
                'task_stats' => $taskStats,
                'user_feedback' => $validated['user_feedback'] ?? null,
            ];

            // Get project-specific AI configuration
            $aiConfig = $project->getAIConfiguration();
            $provider = $aiConfig['provider'] ?? config('ai.default');
            $model = $aiConfig['model'] ?? null;

        // Use project-specific AI configuration for breakdown
        $aiResponse = \App\Services\AI\Facades\AI::driver($provider)->breakdownTask(
            $validated['title'],
            $validated['description'] ?? '',
            $context,
            $model ? ['model' => $model] : []
        );

        // Capture the full prompt text for transparency
        $fullPromptText = $this->generateFullPromptText($validated, $context, $provider, $model);

            // Validate and adjust subtask priorities if parent task exists
            $subtasks = $aiResponse->getTasks();
            if ($parentTask && !empty($subtasks)) {
                $subtasks = $this->validateAndAdjustSubtaskPriorities($subtasks, $parentTask);
            }

            return response()->json([
                'success' => $aiResponse->isSuccessful(),
                'subtasks' => $subtasks,
                'notes' => $aiResponse->getNotes(),
                'summary' => $aiResponse->getSummary(),
                'problems' => $aiResponse->getProblems(),
                'suggestions' => $aiResponse->getSuggestions(),
                'ai_used' => true,
                'priority_adjustments' => $parentTask ? $this->getPriorityAdjustmentMessage($subtasks, $parentTask) : null,
                'prompt_used' => $this->generatePromptForViewing($validated, $context, $provider, $model),
                'full_prompt_text' => $fullPromptText,
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

    /**
     * Reorder a task within its sibling group.
     */
    public function reorder(Request $request, Project $project, Task $task)
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
            'new_position' => 'required|integer|min:1',
            'confirmed' => 'boolean',
        ]);

        try {
            $result = $task->reorderTo(
                $validated['new_position'],
                $validated['confirmed'] ?? false
            );
        } catch (\Exception $e) {
            \Log::error('Task reorder failed', [
                'task_id' => $task->id,
                'new_position' => $validated['new_position'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder task: ' . $e->getMessage(),
                'old_position' => $task->sort_order,
                'new_position' => $validated['new_position'],
                'move_count' => $task->move_count ?? 0,
            ], 500);
        }

        if (!$result['success'] && isset($result['requires_confirmation'])) {
            return response()->json($result, 200);
        }

        if (!$result['success']) {
            // Check if this is a validation error (should return 200 with error message)
            if (isset($result['message']) && str_contains($result['message'], 'cannot be moved outside their parent task context')) {
                return response()->json($result, 200);
            }
            return response()->json($result, 400);
        }

        // Return updated task list for the project
        $tasks = $this->getTasksForProject($project, $request->get('filter', 'all'));

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'tasks' => $tasks,
            'reorder_data' => [
                'old_position' => $result['old_position'] ?? null,
                'new_position' => $result['new_position'] ?? null,
                'move_count' => $result['move_count'] ?? 0,
            ],
            'priority_changed' => $result['priority_changed'] ?? false,
            'old_priority' => $result['old_priority'] ?? null,
            'new_priority' => $result['new_priority'] ?? null,
        ]);
    }

    /**
     * Get tasks for a project with filtering (helper method).
     */
    private function getTasksForProject(Project $project, string $filter = 'all'): array
    {
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
                // Use the same logic as the main index method
                $tasksQuery->orderBy('sort_order');
                break;
        }

        $tasks = $tasksQuery->get();

        // For 'all' filter, sort hierarchically while respecting sort_order within levels
        if ($filter === 'all') {
            $tasks = $this->sortTasksHierarchically($tasks);
        }

        return $tasks->map(function ($task) {
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
                'sort_order' => $task->sort_order,
                'initial_order_index' => $task->initial_order_index,
                'move_count' => $task->move_count,
                'current_order_index' => $task->current_order_index,
                'completion_percentage' => $task->getCompletionPercentage(),
                'created_at' => $task->created_at->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Sort tasks hierarchically while respecting sort_order within each level.
     */
    private function sortTasksHierarchically($tasks)
    {
        $result = collect();

        // First, add all top-level tasks sorted by sort_order
        $topLevelTasks = $tasks->where('parent_id', null)->sortBy('sort_order');

        foreach ($topLevelTasks as $task) {
            // Add the parent task
            $result->push($task);

            // Recursively add children
            $this->addChildrenRecursively($task->id, $tasks, $result);
        }

        return $result;
    }

    /**
     * Recursively add children of a task, sorted by sort_order.
     */
    private function addChildrenRecursively($parentId, $allTasks, &$result)
    {
        // Find children of this parent, sorted by sort_order
        $children = $allTasks
            ->where('parent_id', $parentId)
            ->sortBy('sort_order');

        foreach ($children as $child) {
            $result->push($child);

            // Recursively add this child's children
            $this->addChildrenRecursively($child->id, $allTasks, $result);
        }
    }

    /**
     * Validate and adjust AI-generated subtask priorities to respect parent constraints.
     */
    private function validateAndAdjustSubtaskPriorities(array $subtasks, Task $parentTask): array
    {
        $parentPriorityLevel = $parentTask->getPriorityLevel();
        $adjustedSubtasks = [];

        foreach ($subtasks as $subtask) {
            $originalPriority = $subtask['priority'] ?? 'medium';
            $priorityLevel = $this->getPriorityLevelFromString($originalPriority);

            // If AI suggested priority is lower than parent, adjust it
            if ($priorityLevel < $parentPriorityLevel) {
                $subtask['priority'] = $parentTask->priority;
                $subtask['priority_adjusted'] = true;
                $subtask['original_priority'] = $originalPriority;
            } else {
                $subtask['priority_adjusted'] = false;
            }

            $adjustedSubtasks[] = $subtask;
        }

        return $adjustedSubtasks;
    }

    /**
     * Get priority adjustment message for user feedback.
     */
    private function getPriorityAdjustmentMessage(array $subtasks, Task $parentTask): ?string
    {
        $adjustedCount = count(array_filter($subtasks, fn($s) => $s['priority_adjusted'] ?? false));

        if ($adjustedCount > 0) {
            return "Note: {$adjustedCount} subtask(s) had their priority adjusted to match the minimum required by the parent task ({$parentTask->priority}).";
        }

        return null;
    }

    /**
     * Get priority level from string (helper for AI processing).
     */
    private function getPriorityLevelFromString(string $priority): int
    {
        return match($priority) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 1,
        };
    }

    /**
     * Generate a readable version of the prompt used for AI task breakdown.
     */
    private function generatePromptForViewing(array $validated, array $context, string $provider, ?string $model): array
    {
        $prompt = [
            'provider' => $provider,
            'model' => $model ?? 'default',
            'task_title' => $validated['title'],
            'task_description' => $validated['description'] ?? 'No description provided',
            'user_feedback' => $validated['user_feedback'] ?? 'No specific feedback provided',
            'project_context' => [
                'title' => $context['project']['title'],
                'description' => $context['project']['description'],
                'total_tasks' => $context['task_stats']['total'],
                'completed_tasks' => $context['task_stats']['completed'],
            ],
        ];

        if (!empty($context['parent_task'])) {
            $prompt['parent_task'] = [
                'title' => $context['parent_task']['title'],
                'priority' => $context['parent_task']['priority'],
            ];
        }

        if (!empty($context['existing_tasks'])) {
            $prompt['existing_tasks_count'] = count($context['existing_tasks']);
            $prompt['sample_existing_tasks'] = array_slice($context['existing_tasks'], 0, 3);
        }

        return $prompt;
    }

    /**
     * Generate the full prompt text that would be sent to the AI service.
     */
    private function generateFullPromptText(array $validated, array $context, string $provider, ?string $model): array
    {
        try {
            // Get the AI provider instance to access its prompt building methods
            $aiProvider = \App\Services\AI\Facades\AI::provider($provider);

            // For CerebrusProvider, we can call the protected methods via reflection
            if ($aiProvider instanceof \App\Services\AI\Providers\CerebrusProvider) {
                $reflection = new \ReflectionClass($aiProvider);

                // Get system prompt
                $systemPromptMethod = $reflection->getMethod('buildTaskBreakdownSystemPrompt');
                $systemPromptMethod->setAccessible(true);
                $systemPrompt = $systemPromptMethod->invoke($aiProvider);

                // Get user prompt
                $userPromptMethod = $reflection->getMethod('buildTaskBreakdownUserPrompt');
                $userPromptMethod->setAccessible(true);
                $userPrompt = $userPromptMethod->invoke($aiProvider, $validated['title'], $validated['description'] ?? '', $context);

                return [
                    'system_prompt' => $systemPrompt,
                    'user_prompt' => $userPrompt,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ];
            }

            // Fallback for other providers - return a generic representation
            return [
                'system_prompt' => 'System prompt not available for this provider',
                'user_prompt' => 'User prompt not available for this provider',
                'messages' => [],
                'note' => 'Full prompt text is only available for supported AI providers',
            ];

        } catch (\Exception $e) {
            return [
                'system_prompt' => 'Error retrieving system prompt',
                'user_prompt' => 'Error retrieving user prompt',
                'messages' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
}
