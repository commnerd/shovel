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
                // For leaf view, we want to show hierarchy but only allow leaf tasks to be actionable
                // Get all tasks and sort hierarchically, but frontend will handle the filtering
                $tasksQuery->orderBy('sort_order');
                break;
            case 'board':
                // For board view, get all tasks ordered by status for Kanban
                // Use CASE statements for SQLite compatibility
                $tasksQuery->orderByRaw("CASE
                                           WHEN status = 'pending' THEN 1
                                           WHEN status = 'in_progress' THEN 2
                                           WHEN status = 'completed' THEN 3
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

        // For 'all', 'leaf', and 'board' filters, sort hierarchically while respecting sort_order within levels
        if (in_array($filter, ['all', 'leaf', 'board'])) {
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
            'status' => 'required|in:pending,in_progress,completed',
            'due_date' => 'nullable|date|after_or_equal:today',
            'subtasks' => 'nullable|array',
            'subtasks.*.title' => 'required|string|max:255',
            'subtasks.*.description' => 'nullable|string|max:1000',
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
     * Show subtask reordering page for a specific task.
     */
    public function showSubtaskReorder(Project $project, Task $task)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
        }

        // Ensure the task belongs to the project
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found in this project.');
        }

        // Get all subtasks ordered by their current sort order
        $subtasks = $task->children()->orderBy('sort_order')->get();

        return Inertia::render('Projects/Tasks/SubtaskReorder', [
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
                'due_date' => $task->due_date?->format('Y-m-d'),
                'parent_id' => $task->parent_id,
                'has_children' => $task->children->count() > 0,
                'is_leaf' => $task->isLeaf(),
                'is_top_level' => $task->isTopLevel(),
                'depth' => $task->depth,
            ],
            'subtasks' => $subtasks->map(function ($subtask) {
                return [
                    'id' => $subtask->id,
                    'title' => $subtask->title,
                    'description' => $subtask->description,
                    'status' => $subtask->status,
                    'due_date' => $subtask->due_date?->format('Y-m-d'),
                    'parent_id' => $subtask->parent_id,
                    'has_children' => $subtask->children->count() > 0,
                    'is_leaf' => $subtask->isLeaf(),
                    'is_top_level' => $subtask->isTopLevel(),
                    'depth' => $subtask->depth,
                    'sort_order' => $subtask->sort_order,
                    'completion_percentage' => $subtask->completion_percentage,
                    'created_at' => $subtask->created_at->format('Y-m-d H:i:s'),
                ];
            }),
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
                ] : null,
                'existing_tasks' => $existingTasks,
                'task_stats' => $taskStats,
                'user_feedback' => $validated['user_feedback'] ?? null,
            ];

            // Get project-specific AI configuration
            $aiConfig = $project->getAIConfiguration();
            $provider = $aiConfig['provider'] ?? config('ai.default');
            $model = $aiConfig['model'] ?? null;

            // Validate that the AI provider is available BEFORE trying to use it
            $availableProviders = array_keys(\App\Services\AI\Facades\AI::getAvailableProviders());
            if (!in_array($provider, $availableProviders)) {
                throw new \InvalidArgumentException("AI provider '{$provider}' is not available. Available providers: " . implode(', ', $availableProviders));
            }

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

            // Add due dates to subtasks based on parent task or project due date
            $subtasks = $this->addDueDatesToSubtasks($subtasks, $parentTask, $project);

            return response()->json([
                'success' => $aiResponse->isSuccessful(),
                'subtasks' => $subtasks,
                'notes' => $aiResponse->getNotes(),
                'summary' => $aiResponse->getSummary(),
                'problems' => $aiResponse->getProblems(),
                'suggestions' => $aiResponse->getSuggestions(),
                'ai_used' => true,
                'prompt_used' => $this->generatePromptForViewing($validated, $context, $provider, $model),
                'full_prompt_text' => $fullPromptText,
            ]);

        } catch (\InvalidArgumentException $e) {
            \Log::error('Task breakdown failed - Invalid AI provider', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'task_title' => $validated['title'],
                'provider' => $provider ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Invalid AI provider configured. Please check your project settings.',
                'subtasks' => [],
                'notes' => ['AI provider configuration error'],
                'ai_used' => false,
            ], 400);
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
            // Get the filter context from the request to determine the appropriate validation
            $filter = $request->get('filter', 'all');
            $context = match($filter) {
                'top-level' => 'top-level',
                'subtasks' => 'subtasks',
                default => 'all'
            };

            $result = $task->reorderTo(
                $validated['new_position'],
                $validated['confirmed'] ?? false,
                $context
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
     * Add due dates to subtasks based on parent task due date only.
     */
    private function addDueDatesToSubtasks(array $subtasks, ?Task $parentTask, Project $project): array
    {
        // Determine the reference due date (only from parent task, not project)
        $referenceDueDate = null;
        if ($parentTask && $parentTask->due_date) {
            $referenceDueDate = $parentTask->due_date->format('Y-m-d');
        }

        if (!$referenceDueDate) {
            // Strip any AI-returned due dates since parent task has no due date
            $strippedSubtasks = [];
            foreach ($subtasks as $subtask) {
                // Remove due_date key if it exists
                if (isset($subtask['due_date'])) {
                    unset($subtask['due_date']);
                }
                $strippedSubtasks[] = $subtask;
            }
            return $strippedSubtasks;
        }

        $updatedSubtasks = [];
        foreach ($subtasks as $subtask) {
            // Only add due date if subtask doesn't already have one
            if (empty($subtask['due_date'])) {
                $subtask['due_date'] = $this->calculateSubtaskDueDate($referenceDueDate, $subtask);
            }
            $updatedSubtasks[] = $subtask;
        }

        return $updatedSubtasks;
    }

    /**
     * Calculate a reasonable due date for a subtask based on parent due date.
     */
    private function calculateSubtaskDueDate(string $parentDueDate, array $subtask): ?string
    {
        try {
            $parentDate = \Carbon\Carbon::parse($parentDueDate);
            $now = now();

            // If parent due date is in the past, don't set a due date
            if ($parentDate->isPast()) {
                return null;
            }

            // Calculate subtask due date - subtasks should be due before parent (40% into timeline)
            $daysFromNow = $parentDate->diffInDays($now);
            $dueDateOffset = $daysFromNow * 0.4;

            // Ensure minimum 1 day and maximum is parent due date minus 1 day
            $dueDateOffset = max(1, min($dueDateOffset, $daysFromNow - 1));
            $subtaskDueDate = $now->copy()->addDays(round($dueDateOffset));

            // Don't exceed parent due date minus 1 day
            if ($subtaskDueDate->gte($parentDate)) {
                $subtaskDueDate = $parentDate->copy()->subDay();
            }

            return $subtaskDueDate->format('Y-m-d');
        } catch (\Exception $e) {
            // If date parsing fails, return null
            return null;
        }
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
