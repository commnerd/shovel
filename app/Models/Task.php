<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'due_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'due_date' => 'date',
        'depth' => 'integer',
        'sort_order' => 'integer',
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
}
