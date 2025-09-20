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
        \DB::transaction(function () use ($oldPosition, $newPosition, $priorityAdjustment) {
            // Update siblings' sort orders first
            $this->updateSiblingOrders($oldPosition, $newPosition);

            // Prepare update data
            $updateData = ['sort_order' => $newPosition];

            // Add priority adjustment if needed
            if ($priorityAdjustment) {
                $updateData['priority'] = $priorityAdjustment['new_priority'];
            }

            // Update the main sort_order and potentially priority
            $mainUpdateResult = $this->update($updateData);


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
                        'move_count' => $this->move_count + 1,
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

        // Refresh to get the latest data and verify the update worked
        $this->refresh();


        // Verify the update actually worked
        $actualPosition = $this->sort_order;
        $updateSuccessful = $actualPosition == $newPosition;

        $message = 'Task reordered successfully.';
        if ($priorityAdjustment) {
            $message .= " Priority changed from {$priorityAdjustment['old_priority']} to {$priorityAdjustment['new_priority']}.";
        }

        return [
            'success' => $updateSuccessful,
            'message' => $updateSuccessful
                ? $message
                : "Task reorder failed - position is {$actualPosition}, expected {$newPosition}",
            'old_position' => $oldPosition,
            'new_position' => $newPosition,
            'actual_position' => $actualPosition,
            'move_count' => $this->move_count ?? 0,
            'priority_changed' => !!$priorityAdjustment,
            'old_priority' => $priorityAdjustment['old_priority'] ?? null,
            'new_priority' => $priorityAdjustment['new_priority'] ?? null,
        ];
    }

    /**
     * Determine if and how task priority should be adjusted based on neighbors.
     */
    private function determinePriorityAdjustment(int $newPosition, array $confirmationData): ?array
    {
        $siblings = $this->getSiblings();
        $neighbors = $this->getNeighborsAtPosition($siblings, $newPosition);

        if (empty($neighbors)) {
            return null;
        }

        $currentPriority = $this->priority;
        $neighborPriorities = array_map(fn($task) => $task->priority, $neighbors);

        // Determine the most appropriate priority based on neighbors
        $newPriority = $this->calculateOptimalPriority($neighbors, $confirmationData);

        if ($newPriority !== $currentPriority) {
            return [
                'old_priority' => $currentPriority,
                'new_priority' => $newPriority,
                'reason' => $confirmationData['type'],
            ];
        }

        return null;
    }

    /**
     * Calculate the optimal priority based on neighboring tasks.
     */
    private function calculateOptimalPriority(array $neighbors, array $confirmationData): string
    {
        if (empty($neighbors)) {
            return $this->priority;
        }

        $neighborPriorities = array_map(fn($task) => $task->getPriorityLevel(), $neighbors);

        // If moving to higher priority area, adopt the lowest high priority from neighbors
        if ($confirmationData['type'] === 'moving_to_higher_priority') {
            $maxNeighborPriority = max($neighborPriorities);
            return $this->getPriorityName($maxNeighborPriority);
        }

        // If moving to lower priority area, adopt the highest low priority from neighbors
        if ($confirmationData['type'] === 'moving_to_lower_priority') {
            $minNeighborPriority = min($neighborPriorities);
            return $this->getPriorityName($minNeighborPriority);
        }

        return $this->priority;
    }

    /**
     * Update sort orders of sibling tasks when reordering.
     */
    private function updateSiblingOrders(int $oldPosition, int $newPosition): void
    {
        $siblings = Task::where('parent_id', $this->parent_id)
            ->where('project_id', $this->project_id)
            ->where('id', '!=', $this->id);

        $affectedCount = 0;

        if ($newPosition < $oldPosition) {
            // Moving up: increment sort_order for tasks between new and old position
            $affectedCount = $siblings->whereBetween('sort_order', [$newPosition, $oldPosition - 1])
                ->increment('sort_order');
        } else {
            // Moving down: decrement sort_order for tasks between old and new position
            $affectedCount = $siblings->whereBetween('sort_order', [$oldPosition + 1, $newPosition])
                ->decrement('sort_order');
        }

    }
}
