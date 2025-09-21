<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::created(function ($task) {
            $task->updateHierarchyPath();
        });

        static::updated(function ($task) {
            if ($task->wasChanged('parent_id')) {
                $task->updateHierarchyPath();
            }

            // Update parent status when child status changes
            if ($task->wasChanged('status') && $task->parent_id) {
                $task->updateParentStatus();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'parent_id',
        'title',
        'description',
        'status',
        'priority',
        'depth',
        'path',
        'sort_order',
        'initial_order_index',
        'move_count',
        'current_order_index',
        'last_moved_at',
        'due_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'due_date' => 'date',
        'depth' => 'integer',
        'sort_order' => 'integer',
        'initial_order_index' => 'integer',
        'move_count' => 'integer',
        'current_order_index' => 'integer',
        'last_moved_at' => 'datetime',
    ];

    /**
     * Get the project that owns the task.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the parent task.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * Get the child tasks (subtasks).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all descendants (recursive).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Scope a query to only include top-level tasks (no parent).
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to only include leaf tasks (no children).
     */
    public function scopeLeaf($query)
    {
        return $query->whereDoesntHave('children');
    }

    /**
     * Scope a query to only include tasks with children.
     */
    public function scopeWithChildren($query)
    {
        return $query->whereHas('children');
    }

    /**
     * Check if this task is a top-level task.
     */
    public function isTopLevel(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this task is a leaf task (has no children).
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    /**
     * Get the depth level of this task (0 for top-level).
     */
    public function getDepth(): int
    {
        if ($this->depth !== null) {
            return $this->depth;
        }

        $depth = 0;
        $parent = $this->parent;

        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    /**
     * Update the hierarchy path for this task.
     */
    public function updateHierarchyPath(): void
    {
        $path = [];
        $current = $this;
        $maxDepth = 10; // Prevent infinite loops
        $depth = 0;

        // Build path from current task up to root
        while ($current && $depth < $maxDepth) {
            array_unshift($path, $current->id);
            $current = $current->parent;
            $depth++;
        }

        $newPath = implode('/', $path);
        $newDepth = count($path) - 1;

        // Only update if values changed to prevent recursion
        if ($this->path !== $newPath || $this->depth !== $newDepth) {
            $this->updateQuietly([
                'path' => $newPath,
                'depth' => $newDepth,
            ]);
        }
    }

    /**
     * Get all ancestors of this task.
     */
    public function ancestors()
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get the root task (top-level parent).
     */
    public function getRoot(): ?Task
    {
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
        }

        return $current->isTopLevel() ? $current : null;
    }

    /**
     * Get all siblings (tasks with the same parent).
     */
    public function siblings()
    {
        return Task::where('parent_id', $this->parent_id)
            ->where('id', '!=', $this->id)
            ->orderBy('sort_order');
    }

    /**
     * Get the next sort order for a new child task.
     */
    public function getNextChildSortOrder(): int
    {
        return $this->children()->max('sort_order') + 1;
    }

    /**
     * Check if this task has any incomplete descendants.
     */
    public function hasIncompleteDescendants(): bool
    {
        return $this->descendants()->where('status', '!=', 'completed')->exists();
    }

    /**
     * Get completion percentage based on descendants.
     */
    public function getCompletionPercentage(): float
    {
        $descendants = $this->descendants()->get();

        if ($descendants->isEmpty()) {
            return $this->status === 'completed' ? 100.0 : 0.0;
        }

        $completed = $descendants->where('status', 'completed')->count();
        $total = $descendants->count();

        return ($completed / $total) * 100;
    }

    /**
     * Initialize order tracking when task is created.
     */
    public function initializeOrderTracking(): void
    {
        if ($this->initial_order_index === null) {
            $this->updateQuietly([
                'initial_order_index' => $this->sort_order,
                'current_order_index' => $this->sort_order,
                'move_count' => 0,
            ]);
        }
    }

    /**
     * Get priority level as numeric value for comparison.
     */
    public function getPriorityLevel(): int
    {
        return match($this->priority) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }

    /**
     * Validate that task priority is not lower than its parent's priority.
     */
    public function validateParentPriorityConstraint(?string $newPriority = null): array
    {
        $priority = $newPriority ?? $this->priority;
        $priorityLevel = $this->getPriorityLevelFromString($priority);

        if (!$this->parent_id) {
            // Top-level tasks have no constraints
            return ['valid' => true];
        }

        $parent = $this->parent;
        if (!$parent) {
            return ['valid' => true];
        }

        $parentPriorityLevel = $parent->getPriorityLevel();

        if ($priorityLevel < $parentPriorityLevel) {
            return [
                'valid' => false,
                'error' => "Child task cannot have lower priority ({$priority}) than its parent ({$parent->priority})",
                'parent_priority' => $parent->priority,
                'attempted_priority' => $priority,
                'minimum_allowed_priority' => $parent->priority,
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get priority level from string value.
     */
    private function getPriorityLevelFromString(string $priority): int
    {
        return match($priority) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }

    /**
     * Check if moving this task to a new position requires confirmation.
     * Returns array with confirmation details or null if no confirmation needed.
     */
    public function checkReorderConfirmation(int $newPosition): ?array
    {
        $siblings = $this->getSiblings();
        $currentPriority = $this->getPriorityLevel();

        // Get neighbors at the new position
        $neighbors = $this->getNeighborsAtPosition($siblings, $newPosition);

        if (empty($neighbors)) {
            return null;
        }

        $neighborPriorities = array_map(fn($task) => $task->getPriorityLevel(), $neighbors);
        $higherPriorityNeighbors = array_filter($neighborPriorities, fn($p) => $p > $currentPriority);
        $lowerPriorityNeighbors = array_filter($neighborPriorities, fn($p) => $p < $currentPriority);

        if (!empty($higherPriorityNeighbors)) {
            return [
                'type' => 'moving_to_higher_priority',
                'message' => 'You are moving a ' . $this->priority . ' priority task near higher priority tasks. Continue?',
                'task_priority' => $this->priority,
                'neighbor_priorities' => array_unique(array_map(fn($p) => $this->getPriorityName($p), $higherPriorityNeighbors)),
            ];
        }

        if (!empty($lowerPriorityNeighbors)) {
            return [
                'type' => 'moving_to_lower_priority',
                'message' => 'You are moving a ' . $this->priority . ' priority task near lower priority tasks. Continue?',
                'task_priority' => $this->priority,
                'neighbor_priorities' => array_unique(array_map(fn($p) => $this->getPriorityName($p), $lowerPriorityNeighbors)),
            ];
        }

        return null;
    }

    /**
     * Get siblings (tasks with same parent) ordered by sort_order.
     */
    public function getSiblings()
    {
        return Task::where('parent_id', $this->parent_id)
            ->where('project_id', $this->project_id)
            ->where('id', '!=', $this->id)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get neighboring tasks at a specific position.
     */
    private function getNeighborsAtPosition($siblings, int $newPosition): array
    {
        $neighbors = [];

        // Get task before new position
        if ($newPosition > 1) {
            $beforeTask = $siblings->firstWhere('sort_order', $newPosition - 1);
            if ($beforeTask) {
                $neighbors[] = $beforeTask;
            }
        }

        // Get task after new position
        $afterTask = $siblings->firstWhere('sort_order', $newPosition);
        if ($afterTask) {
            $neighbors[] = $afterTask;
        }

        return $neighbors;
    }

    /**
     * Get priority name from numeric level.
     */
    private function getPriorityName(int $level): string
    {
        return match($level) {
            3 => 'high',
            2 => 'medium',
            1 => 'low',
            default => 'unknown',
        };
    }

    /**
     * Execute task reordering with tracking.
     */
    public function reorderTo(int $newPosition, bool $confirmed = false): array
    {
        $oldPosition = $this->sort_order;

        if ($oldPosition === $newPosition) {
            return [
                'success' => true,
                'message' => 'Task is already in the requested position.',
                'old_position' => $oldPosition,
                'new_position' => $newPosition,
                'move_count' => $this->move_count ?? 0,
            ];
        }

        // Validate that subtasks can only be reordered within their parent context
        if ($this->parent_id) {
            $siblings = $this->getSiblings();
            $parent = $this->parent;

            if (!$parent) {
                return [
                    'success' => false,
                    'message' => 'Cannot reorder subtask: parent task not found.',
                    'old_position' => $oldPosition,
                    'new_position' => $newPosition,
                    'move_count' => $this->move_count ?? 0,
                ];
            }

            // Check if the target position conflicts with a task that has a different parent
            $taskAtNewPosition = static::where('project_id', $this->project_id)
                ->where('sort_order', $newPosition)
                ->where('id', '!=', $this->id)
                ->first();

            if ($taskAtNewPosition) {
                // If the task at the new position has a different parent (or no parent), it's invalid
                if ($taskAtNewPosition->parent_id !== $this->parent_id) {
                    return [
                        'success' => false,
                        'message' => 'Subtasks cannot be moved outside their parent task context. Use the edit form to change the parent.',
                        'old_position' => $oldPosition,
                        'new_position' => $newPosition,
                        'move_count' => $this->move_count ?? 0,
                    ];
                }
            }
        }

        // Check if confirmation is needed
        $confirmationCheck = $this->checkReorderConfirmation($newPosition);
        if ($confirmationCheck && !$confirmed) {
            return [
                'success' => false,
                'requires_confirmation' => true,
                'confirmation_data' => $confirmationCheck,
                'old_position' => $oldPosition,
                'new_position' => $newPosition,
                'move_count' => $this->move_count ?? 0,
            ];
        }

        // Determine if priority should be adjusted after confirmation
        $priorityAdjustment = null;
        if ($confirmationCheck && $confirmed) {
            $priorityAdjustment = $this->determinePriorityAdjustment($newPosition, $confirmationCheck);
        }

        // Use database transaction to ensure all updates are atomic
        DB::transaction(function () use ($oldPosition, $newPosition, $priorityAdjustment) {
            // Update siblings' sort orders first
            $this->updateSiblingOrders($oldPosition, $newPosition);

            // Prepare update data
            $updateData = ['sort_order' => $newPosition];

            // Add priority adjustment if needed
            if ($priorityAdjustment) {
                $updateData['priority'] = $priorityAdjustment['new_priority'];
            }

            // Update the main sort_order and potentially priority
            $this->update($updateData);

            // Try to update tracking fields if they exist (these are optional)
            try {
                if ($this->initial_order_index === null) {
                    // First time being moved - initialize tracking
                    $this->updateQuietly([
                        'initial_order_index' => $oldPosition,
                        'current_order_index' => $newPosition,
                        'move_count' => 1,
                        'last_moved_at' => now(),
                    ]);
                } else {
                    // Already has tracking - update tracking fields
                    $this->updateQuietly([
                        'current_order_index' => $newPosition,
                        'move_count' => ($this->move_count ?? 0) + 1,
                        'last_moved_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                // If tracking fields don't exist in DB, just log and continue
                \Log::warning('Task tracking fields update failed (fields may not exist in database)', [
                    'task_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $message = 'Task reordered successfully!';
        if ($priorityAdjustment) {
            $message .= " Priority changed from {$priorityAdjustment['old_priority']} to {$priorityAdjustment['new_priority']}.";
        }

        return [
            'success' => true,
            'message' => $message,
            'old_position' => $oldPosition,
            'new_position' => $newPosition,
            'move_count' => $this->move_count ?? 0,
            'priority_changed' => $priorityAdjustment !== null,
            'old_priority' => $priorityAdjustment['old_priority'] ?? null,
            'new_priority' => $priorityAdjustment['new_priority'] ?? null,
        ];
    }

    /**
     * Update parent task status based on children completion.
     */
    public function updateParentStatus(): void
    {
        if (!$this->parent_id) {
            return;
        }

        $parent = $this->parent;
        if (!$parent) {
            return;
        }

        // Get all direct children of the parent
        $children = $parent->children;
        if ($children->isEmpty()) {
            return;
        }

        // Calculate completion status
        $totalChildren = $children->count();
        $completedChildren = $children->where('status', 'completed')->count();
        $inProgressChildren = $children->where('status', 'in_progress')->count();

        // Determine parent status based on children
        $newStatus = 'pending'; // Default

        if ($completedChildren === $totalChildren) {
            // All children completed
            $newStatus = 'completed';
        } elseif ($completedChildren > 0 || $inProgressChildren > 0) {
            // Some children completed or in progress
            $newStatus = 'in_progress';
        }

        // Update parent status if it changed
        if ($parent->status !== $newStatus) {
            $parent->updateQuietly(['status' => $newStatus]);

            // Recursively update grandparent if needed
            if ($parent->parent_id) {
                $parent->updateParentStatus();
            }
        }
    }

    /**
     * Determine if priority should be adjusted and what the new priority should be.
     */
    private function determinePriorityAdjustment(int $newPosition, array $confirmationData): ?array
    {
        $neighborPriorities = $confirmationData['neighbor_priorities'] ?? [];

        if (empty($neighborPriorities)) {
            return null;
        }

        // Get the most common priority among neighbors
        $priorityCounts = array_count_values($neighborPriorities);
        arsort($priorityCounts);
        $suggestedPriority = array_key_first($priorityCounts);

        // Only suggest change if it's different from current
        if ($suggestedPriority && $suggestedPriority !== $this->priority) {
            // Validate the suggested priority respects parent constraints
            $validationResult = $this->validateParentPriorityConstraint($suggestedPriority);
            if (!$validationResult['valid']) {
                // If suggested priority violates parent constraint, use parent's priority
                $suggestedPriority = $this->parent ? $this->parent->priority : $this->priority;
            }

            return [
                'old_priority' => $this->priority,
                'new_priority' => $suggestedPriority,
            ];
        }

        return null;
    }

    /**
     * Update sibling sort orders when a task is moved.
     */
    private function updateSiblingOrders(int $oldPosition, int $newPosition): void
    {
        if ($oldPosition === $newPosition) {
            return;
        }

        $siblings = $this->getSiblings();

        if ($oldPosition < $newPosition) {
            // Moving down: shift tasks between old and new position up
            foreach ($siblings as $sibling) {
                if ($sibling->sort_order > $oldPosition && $sibling->sort_order <= $newPosition) {
                    $sibling->updateQuietly(['sort_order' => $sibling->sort_order - 1]);
                }
            }
        } else {
            // Moving up: shift tasks between new and old position down
            foreach ($siblings as $sibling) {
                if ($sibling->sort_order >= $newPosition && $sibling->sort_order < $oldPosition) {
                    $sibling->updateQuietly(['sort_order' => $sibling->sort_order + 1]);
                }
            }
        }
    }
}
