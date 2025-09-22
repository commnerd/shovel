<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DailyCuration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'curation_date',
        'suggestions',
        'summary',
        'focus_areas',
        'ai_provider',
        'ai_generated',
        'viewed_at',
        'dismissed_at',
    ];

    protected $casts = [
        'curation_date' => 'date',
        'suggestions' => 'array',
        'focus_areas' => 'array',
        'ai_generated' => 'boolean',
        'viewed_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the curation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project this curation belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scope to get curations for a specific date.
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->whereDate('curation_date', $date->format('Y-m-d'));
    }

    /**
     * Scope to get curations for today.
     */
    public function scopeForToday($query)
    {
        return $query->forDate(Carbon::now());
    }

    /**
     * Scope to get unviewed curations.
     */
    public function scopeUnviewed($query)
    {
        return $query->whereNull('viewed_at');
    }

    /**
     * Scope to get active (not dismissed) curations.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('dismissed_at');
    }

    /**
     * Get suggestions by type.
     */
    public function getSuggestionsByType(string $type): array
    {
        return collect($this->suggestions)->where('type', $type)->values()->toArray();
    }

    /**
     * Get priority suggestions.
     */
    public function getPrioritySuggestions(): array
    {
        return $this->getSuggestionsByType('priority');
    }

    /**
     * Get risk suggestions.
     */
    public function getRiskSuggestions(): array
    {
        return $this->getSuggestionsByType('risk');
    }

    /**
     * Get optimization suggestions.
     */
    public function getOptimizationSuggestions(): array
    {
        return $this->getSuggestionsByType('optimization');
    }

    /**
     * Mark as viewed.
     */
    public function markAsViewed(): void
    {
        $this->update(['viewed_at' => now()]);
    }

    /**
     * Mark as dismissed.
     */
    public function dismiss(): void
    {
        $this->update(['dismissed_at' => now()]);
    }

    /**
     * Check if the curation is new (unviewed).
     */
    public function isNew(): bool
    {
        return $this->viewed_at === null;
    }

    /**
     * Check if the curation is dismissed.
     */
    public function isDismissed(): bool
    {
        return $this->dismissed_at !== null;
    }

    /**
     * Get the total number of suggestions.
     */
    public function getSuggestionsCount(): int
    {
        return count($this->suggestions ?? []);
    }

    /**
     * Get suggestions that reference specific tasks.
     */
    public function getTaskSuggestions(): array
    {
        return collect($this->suggestions)
            ->filter(function ($suggestion) {
                return isset($suggestion['task_id']);
            })
            ->values()
            ->toArray();
    }

    /**
     * Get general suggestions (not task-specific).
     */
    public function getGeneralSuggestions(): array
    {
        return collect($this->suggestions)
            ->filter(function ($suggestion) {
                return !isset($suggestion['task_id']);
            })
            ->values()
            ->toArray();
    }

    /**
     * Create or update a daily curation for a user and project.
     */
    public static function createOrUpdate(
        User $user,
        Project $project,
        array $suggestions,
        ?string $summary = null,
        ?array $focusAreas = null,
        ?string $aiProvider = null,
        bool $aiGenerated = false
    ): self {
        return self::updateOrCreate(
            [
                'user_id' => $user->id,
                'project_id' => $project->id,
                'curation_date' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'suggestions' => $suggestions,
                'summary' => $summary,
                'focus_areas' => $focusAreas,
                'ai_provider' => $aiProvider,
                'ai_generated' => $aiGenerated,
            ]
        );
    }
}
