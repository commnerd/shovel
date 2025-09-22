<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'group_id',
        'title',
        'description',
        'due_date',
        'status',
        'project_type',
        'default_iteration_length_weeks',
        'auto_create_iterations',
        'ai_provider',
        'ai_model',
        'ai_api_key',
        'ai_base_url',
        'ai_config',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'due_date' => 'date',
        'ai_config' => 'array',
    ];

    /**
     * Get the user that owns the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the group that owns the project.
     */
    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the tasks for the project.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the iterations for the project.
     */
    public function iterations(): HasMany
    {
        return $this->hasMany(Iteration::class)->orderBy('sort_order');
    }

    /**
     * Get only top-level tasks for the project.
     */
    public function topLevelTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * Scope a query to only include active projects.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include completed projects.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Check if the project is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'completed';
    }

    /**
     * Get the days remaining until due date.
     */
    public function daysRemaining(): ?int
    {
        if (! $this->due_date) {
            return null;
        }

        return today()->diffInDays($this->due_date, false);
    }

    /**
     * Get the AI configuration for this project.
     */
    public function getAIConfiguration(): array
    {
        return [
            'provider' => $this->ai_provider ?? 'cerebrus',
            'model' => $this->ai_model,
            'api_key' => $this->ai_api_key,
            'base_url' => $this->ai_base_url,
            'config' => $this->ai_config ?? [],
        ];
    }

    /**
     * Set the AI configuration for this project.
     */
    public function setAIConfiguration(array $config): void
    {
        $this->update([
            'ai_provider' => $config['provider'] ?? 'cerebrus',
            'ai_model' => $config['model'] ?? null,
            'ai_api_key' => $config['api_key'] ?? null,
            'ai_base_url' => $config['base_url'] ?? null,
            'ai_config' => $config['config'] ?? [],
        ]);
    }

    /**
     * Check if the project is iterative.
     */
    public function isIterative(): bool
    {
        return $this->project_type === 'iterative';
    }

    /**
     * Check if the project is finite.
     */
    public function isFinite(): bool
    {
        return $this->project_type === 'finite';
    }

    /**
     * Get the current active iteration.
     */
    public function getCurrentIteration(): ?Iteration
    {
        return $this->iterations()->active()->first();
    }

    /**
     * Get the next planned iteration.
     */
    public function getNextIteration(): ?Iteration
    {
        return $this->iterations()->planned()->orderBy('sort_order')->first();
    }

    /**
     * Get the backlog (tasks not assigned to any iteration).
     */
    public function getBacklogTasks()
    {
        return $this->tasks()->whereNull('iteration_id');
    }

    /**
     * Create the next iteration if auto-creation is enabled.
     */
    public function createNextIterationIfNeeded(): ?Iteration
    {
        if (!$this->isIterative() || !$this->auto_create_iterations) {
            return null;
        }

        // Check if we need a new iteration
        $hasActiveIteration = $this->iterations()->active()->exists();
        $hasPlannedIteration = $this->iterations()->planned()->exists();

        if (!$hasActiveIteration && !$hasPlannedIteration) {
            return Iteration::createNext($this);
        }

        return null;
    }

    /**
     * Calculate total story points for the project.
     */
    public function getTotalStoryPoints(): int
    {
        return $this->tasks()->leaf()->sum('current_story_points') ?? 0;
    }

    /**
     * Calculate completed story points for the project.
     */
    public function getCompletedStoryPoints(): int
    {
        return $this->tasks()->leaf()->where('status', 'completed')->sum('current_story_points') ?? 0;
    }

    /**
     * Get the project's velocity (average points completed per iteration).
     */
    public function getAverageVelocity(): float
    {
        $completedIterations = $this->iterations()->completed();
        $count = $completedIterations->count();

        if ($count === 0) {
            return 0.0;
        }

        $totalVelocity = $completedIterations->sum('completed_points');
        return round($totalVelocity / $count, 2);
    }

    /**
     * Apply default AI configuration from system settings.
     */
    public function applyDefaultAIConfiguration(): void
    {
        // Only apply provider and model from defaults
        // API keys and base URLs should be read from system provider configuration
        $defaultConfig = [
            'provider' => \App\Models\Setting::get('ai.default.provider', 'cerebrus'),
            'model' => \App\Models\Setting::get('ai.default.model'),
            'api_key' => null, // Don't store API keys per-project
            'base_url' => null, // Don't store base URLs per-project
            'config' => \App\Models\Setting::get('ai.default.config', []),
        ];

        $this->setAIConfiguration($defaultConfig);
    }
}
