<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        'iteration_id',
        'parent_id',
        'title',
        'description',
        'status',
        'size',
        'initial_story_points',
        'current_story_points',
        'story_points_change_count',
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
     * Get the iteration that owns the task.
     */
    public function iteration(): BelongsTo
    {
        return $this->belongsTo(Iteration::class);
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
     * Get the curated tasks for this task.
     */
    public function curatedTasks(): MorphMany
    {
        return $this->morphMany(CuratedTasks::class, 'curatable');
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
     * Check if moving this task to a new position requires confirmation.
     * Returns array with confirmation details or null if no confirmation needed.
     */
    public function checkReorderConfirmation(int $newPosition): ?array
    {
        // No priority-based confirmation needed anymore
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
     * Execute task reordering with tracking.
     */
    public function reorderTo(int $newPosition, bool $confirmed = false, string $context = 'all'): array
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

        // Simplified validation - just ensure the position is reasonable
        if ($newPosition < 1) {
            return [
                'success' => false,
                'message' => 'Position must be greater than 0.',
                'old_position' => $oldPosition,
                'new_position' => $newPosition,
                'move_count' => $this->move_count ?? 0,
            ];
        }

        // Check if confirmation is needed (none needed anymore)
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

        // Use database transaction to ensure all updates are atomic
        DB::transaction(function () use ($oldPosition, $newPosition) {
            // Update siblings' sort orders first
            $this->updateSiblingOrders($oldPosition, $newPosition);

            // Update the main sort_order
            $this->update(['sort_order' => $newPosition]);

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

        return [
            'success' => true,
            'message' => 'Task reordered successfully!',
            'old_position' => $oldPosition,
            'new_position' => $newPosition,
            'move_count' => $this->move_count ?? 0,
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

    /**
     * T-shirt size constants for top-level tasks.
     */
    public const SIZES = [
        'xs' => 'Extra Small',
        's' => 'Small',
        'm' => 'Medium',
        'l' => 'Large',
        'xl' => 'Extra Large',
    ];

    /**
     * Fibonacci sequence for story points.
     */
    public const FIBONACCI_POINTS = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];

    /**
     * Check if the task can have a T-shirt size (top-level tasks only).
     */
    public function canHaveSize(): bool
    {
        return $this->isTopLevel();
    }

    /**
     * Check if the task can have story points (subtasks only).
     */
    public function canHaveStoryPoints(): bool
    {
        return !$this->isTopLevel();
    }

    /**
     * Set the T-shirt size for the task.
     */
    public function setSize(string $size): void
    {
        if (!$this->canHaveSize()) {
            throw new \InvalidArgumentException('Only top-level tasks can have a T-shirt size');
        }

        // Normalize size to lowercase for database storage
        $normalizedSize = strtolower($size);

        if (!array_key_exists($normalizedSize, self::SIZES)) {
            throw new \InvalidArgumentException('Invalid size. Must be one of: ' . implode(', ', array_keys(self::SIZES)));
        }

        $this->update(['size' => $normalizedSize]);
    }

    /**
     * Set story points for the task.
     */
    public function setStoryPoints(int $points): void
    {
        if (!$this->canHaveStoryPoints()) {
            throw new \InvalidArgumentException('Only subtasks can have story points');
        }

        if (!in_array($points, self::FIBONACCI_POINTS)) {
            throw new \InvalidArgumentException('Story points must be a Fibonacci number: ' . implode(', ', self::FIBONACCI_POINTS));
        }

        // Set initial points if not set
        if ($this->initial_story_points === null) {
            $this->initial_story_points = $points;
            $this->current_story_points = $points;
            $this->story_points_change_count = 0;
        } else {
            // Update current points and increment change count
            if ($this->current_story_points !== $points) {
                $this->current_story_points = $points;
                $this->story_points_change_count++;
            }
        }

        $this->save();

        // Update iteration points if assigned to one
        if ($this->iteration) {
            $this->iteration->updatePointsFromTasks();
        }
    }

    /**
     * Get the display name for the task's size.
     */
    public function getSizeDisplayName(): ?string
    {
        return $this->size ? self::SIZES[$this->size] : null;
    }

    /**
     * Get the story points change history count.
     */
    public function getStoryPointsChangeCount(): int
    {
        return $this->story_points_change_count ?? 0;
    }

    /**
     * Check if story points have been changed from initial value.
     */
    public function hasStoryPointsChanged(): bool
    {
        return $this->initial_story_points !== $this->current_story_points;
    }

    /**
     * Move task to an iteration.
     */
    public function moveToIteration(?Iteration $iteration): void
    {
        $oldIteration = $this->iteration;

        $this->update(['iteration_id' => $iteration?->id]);

        // Update points for both old and new iterations
        if ($oldIteration) {
            $oldIteration->updatePointsFromTasks();
        }

        if ($iteration) {
            $iteration->updatePointsFromTasks();
        }
    }

    /**
     * Move task to backlog (remove from iteration).
     */
    public function moveToBacklog(): void
    {
        $this->moveToIteration(null);
    }

    /**
     * Scope to get tasks with story points.
     */
    public function scopeWithStoryPoints($query)
    {
        return $query->whereNotNull('current_story_points');
    }

    /**
     * Scope to get tasks with sizes.
     */
    public function scopeWithSize($query)
    {
        return $query->whereNotNull('size');
    }

    /**
     * Scope to get tasks in a specific iteration.
     */
    public function scopeInIteration($query, $iterationId)
    {
        return $query->where('iteration_id', $iterationId);
    }

    /**
     * Scope to get backlog tasks (not assigned to any iteration).
     */
    public function scopeInBacklog($query)
    {
        return $query->whereNull('iteration_id');
    }
}
