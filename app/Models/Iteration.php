<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Iteration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
        'capacity_points',
        'committed_points',
        'completed_points',
        'sort_order',
        'goals',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'goals' => 'array',
    ];

    /**
     * Get the project that owns the iteration.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tasks for the iteration.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get only leaf tasks (actionable tasks) for the iteration.
     */
    public function leafTasks(): HasMany
    {
        return $this->hasMany(Task::class)->leaf();
    }

    /**
     * Scope a query to only include active iterations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include planned iterations.
     */
    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    /**
     * Scope a query to only include completed iterations.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Check if the iteration is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the iteration is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== 'completed';
    }

    /**
     * Get the days remaining until end date.
     */
    public function daysRemaining(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return today()->diffInDays($this->end_date, false);
    }

    /**
     * Get the completion percentage based on story points.
     */
    public function getCompletionPercentage(): float
    {
        if ($this->committed_points === 0) {
            return 0.0;
        }

        return round(($this->completed_points / $this->committed_points) * 100, 2);
    }

    /**
     * Get the velocity (completed points) for this iteration.
     */
    public function getVelocity(): int
    {
        return $this->completed_points;
    }

    /**
     * Calculate and update the committed and completed points based on assigned tasks.
     */
    public function updatePointsFromTasks(): void
    {
        $leafTasks = $this->leafTasks;

        $committedPoints = $leafTasks->sum('current_story_points');
        $completedPoints = $leafTasks->where('status', 'completed')->sum('current_story_points');

        $this->update([
            'committed_points' => $committedPoints,
            'completed_points' => $completedPoints,
        ]);
    }

    /**
     * Check if the iteration has capacity for additional story points.
     */
    public function hasCapacityFor(int $points): bool
    {
        if ($this->capacity_points === null) {
            return true; // No capacity limit set
        }

        return ($this->committed_points + $points) <= $this->capacity_points;
    }

    /**
     * Get the remaining capacity in story points.
     */
    public function getRemainingCapacity(): ?int
    {
        if ($this->capacity_points === null) {
            return null;
        }

        return max(0, $this->capacity_points - $this->committed_points);
    }

    /**
     * Generate a default name for the iteration based on project and sequence.
     */
    public static function generateDefaultName(Project $project): string
    {
        $iterationCount = $project->iterations()->count() + 1;
        return "Sprint {$iterationCount}";
    }

    /**
     * Create the next iteration for a project.
     */
    public static function createNext(Project $project, array $attributes = []): self
    {
        $lastIteration = $project->iterations()->orderBy('sort_order', 'desc')->first();
        $nextSortOrder = $lastIteration ? $lastIteration->sort_order + 1 : 1;

        $defaultStartDate = $lastIteration
            ? $lastIteration->end_date->addDay()
            : today();

        $defaultEndDate = $defaultStartDate->copy()->addWeeks(
            $project->default_iteration_length_weeks ?? 2
        );

        return static::create(array_merge([
            'project_id' => $project->id,
            'name' => static::generateDefaultName($project),
            'start_date' => $defaultStartDate,
            'end_date' => $defaultEndDate,
            'sort_order' => $nextSortOrder,
            'status' => 'planned',
        ], $attributes));
    }
}
