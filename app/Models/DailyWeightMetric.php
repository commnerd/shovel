<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DailyWeightMetric extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'metric_date',
        'total_story_points',
        'total_tasks_count',
        'signed_tasks_count',
        'unsigned_tasks_count',
        'average_points_per_task',
        'daily_velocity',
        'project_breakdown',
        'size_breakdown',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metric_date' => 'date',
        'project_breakdown' => 'array',
        'size_breakdown' => 'array',
        'average_points_per_task' => 'decimal:2',
        'daily_velocity' => 'decimal:2',
    ];

    /**
     * Get the user that owns the metric.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for a specific date.
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->whereDate('metric_date', $date->format('Y-m-d'));
    }

    /**
     * Scope for today.
     */
    public function scopeForToday($query)
    {
        return $query->whereDate('metric_date', Carbon::today());
    }

    /**
     * Scope for a date range.
     */
    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('metric_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    }

    /**
     * Create or update daily weight metrics for a user.
     */
    public static function createOrUpdate(User $user, Carbon $date, array $data): self
    {
        try {
            return static::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'metric_date' => $date->format('Y-m-d'),
                ],
                $data
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // If we get a unique constraint violation, try to find and update the existing record
            $existing = static::where('user_id', $user->id)
                ->whereDate('metric_date', $date->format('Y-m-d'))
                ->first();

            if ($existing) {
                $existing->update($data);
                return $existing->fresh();
            }

            // If no existing record found, re-throw the exception
            throw $e;
        }
    }

    /**
     * Calculate the average daily velocity over a period.
     */
    public static function getAverageVelocity(User $user, int $days = 7): float
    {
        $startDate = Carbon::today()->subDays($days - 1);

        return static::where('user_id', $user->id)
            ->forDateRange($startDate, Carbon::today())
            ->avg('daily_velocity') ?? 0;
    }

    /**
     * Get the trend of daily velocity over time.
     */
    public static function getVelocityTrend(User $user, int $days = 14): array
    {
        $startDate = Carbon::today()->subDays($days - 1);

        return static::where('user_id', $user->id)
            ->forDateRange($startDate, Carbon::today())
            ->orderBy('metric_date')
            ->get(['metric_date', 'daily_velocity'])
            ->map(function ($metric) {
                return [
                    'date' => $metric->metric_date->format('Y-m-d'),
                    'velocity' => (float) $metric->daily_velocity,
                ];
            })
            ->toArray();
    }
}
