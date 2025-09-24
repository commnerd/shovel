<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CuratedTasks extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'curatable_type',
        'curatable_id',
        'work_date',
        'assigned_to',
        'initial_index',
        'current_index',
        'moved_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'work_date' => 'date',
        'initial_index' => 'integer',
        'current_index' => 'integer',
        'moved_count' => 'integer',
    ];

    /**
     * Get the parent curatable model (polymorphic relationship).
     */
    public function curatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user that this curated task is assigned to.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope to filter by work date.
     */
    public function scopeForWorkDate($query, $date)
    {
        return $query->whereDate('work_date', $date);
    }

    /**
     * Scope to filter by assigned user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope to get today's curated tasks.
     */
    public function scopeToday($query)
    {
        return $query->where('work_date', today());
    }

    /**
     * Update the current index and increment moved count.
     */
    public function updateIndex(int $newIndex): void
    {
        $this->update([
            'current_index' => $newIndex,
            'moved_count' => $this->moved_count + 1,
        ]);
    }

    /**
     * Reset the current index to initial index.
     */
    public function resetIndex(): void
    {
        $this->update([
            'current_index' => $this->initial_index,
        ]);
    }
}
