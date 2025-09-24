<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurationPrompt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'project_id',
        'prompt_text',
        'ai_provider',
        'ai_model',
        'is_organization_user',
        'task_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_organization_user' => 'boolean',
        'task_count' => 'integer',
    ];

    /**
     * Get the user that owns the curation prompt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project that owns the curation prompt.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by project.
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to get prompts from today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to get prompts from a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('created_at', $date);
    }

    /**
     * Scope to get organization user prompts.
     */
    public function scopeOrganizationUsers($query)
    {
        return $query->where('is_organization_user', true);
    }

    /**
     * Scope to get individual user prompts.
     */
    public function scopeIndividualUsers($query)
    {
        return $query->where('is_organization_user', false);
    }

    /**
     * Clear all curation prompts (for morning cleanup).
     */
    public static function clearAll(): int
    {
        return static::query()->delete();
    }

    /**
     * Clear curation prompts older than a specific date.
     */
    public static function clearOlderThan($date): int
    {
        return static::where('created_at', '<', $date)->delete();
    }

    /**
     * Clear curation prompts for a specific user.
     */
    public static function clearForUser($userId): int
    {
        return static::where('user_id', $userId)->delete();
    }

    /**
     * Get the truncated prompt text for display.
     */
    public function getTruncatedPromptAttribute(): string
    {
        return strlen($this->prompt_text) > 200
            ? substr($this->prompt_text, 0, 200) . '...'
            : $this->prompt_text;
    }

    /**
     * Get the prompt length in characters.
     */
    public function getPromptLengthAttribute(): int
    {
        return strlen($this->prompt_text);
    }
}
