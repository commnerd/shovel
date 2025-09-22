<?php

namespace Database\Factories;

use App\Models\DailyCuration;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DailyCuration>
 */
class DailyCurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'curation_date' => Carbon::now()->format('Y-m-d'),
            'suggestions' => [
                [
                    'type' => 'priority',
                    'task_id' => $this->faker->numberBetween(1, 100),
                    'message' => 'Focus on this high-priority task today.',
                ],
                [
                    'type' => 'risk',
                    'task_id' => $this->faker->numberBetween(1, 100),
                    'message' => 'This task is at risk of delay.',
                ],
                [
                    'type' => 'optimization',
                    'message' => 'Consider breaking down large tasks into smaller ones.',
                ],
            ],
            'summary' => $this->faker->sentence(),
            'focus_areas' => ['high_priority', 'overdue_tasks', 'task_optimization'],
            'ai_provider' => $this->faker->randomElement(['cerebrus', 'openai']),
            'ai_generated' => true,
            'viewed_at' => null,
            'dismissed_at' => null,
        ];
    }

    /**
     * Indicate that the curation has been viewed.
     */
    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'viewed_at' => Carbon::now(),
        ]);
    }

    /**
     * Indicate that the curation has been dismissed.
     */
    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'dismissed_at' => Carbon::now(),
        ]);
    }

    /**
     * Create a curation for a specific date.
     */
    public function forDate(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'curation_date' => $date->format('Y-m-d'),
        ]);
    }

    /**
     * Create a curation for today.
     */
    public function forToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'curation_date' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    /**
     * Create a curation with fallback (non-AI) suggestions.
     */
    public function fallback(): static
    {
        return $this->state(fn (array $attributes) => [
            'suggestions' => [
                [
                    'type' => 'priority',
                    'task_id' => $this->faker->numberBetween(1, 100),
                    'message' => 'This task is due soon and should be prioritized.',
                ],
                [
                    'type' => 'risk',
                    'task_id' => $this->faker->numberBetween(1, 100),
                    'message' => 'This task is overdue and needs immediate attention.',
                ],
            ],
            'summary' => 'Basic task analysis completed. Consider reviewing overdue and high-priority tasks.',
            'focus_areas' => ['overdue_tasks', 'in_progress_tasks'],
            'ai_provider' => null,
            'ai_generated' => false,
        ]);
    }

    /**
     * Create a curation with only general suggestions (no task-specific ones).
     */
    public function generalOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'suggestions' => [
                [
                    'type' => 'optimization',
                    'message' => 'Consider reviewing your task priorities for the week.',
                ],
                [
                    'type' => 'insight',
                    'message' => 'Your project is progressing well. Keep up the momentum!',
                ],
            ],
        ]);
    }

    /**
     * Create a curation with risk-focused suggestions.
     */
    public function riskFocused(): static
    {
        return $this->state(fn (array $attributes) => [
            'suggestions' => [
                [
                    'type' => 'risk',
                    'task_id' => $this->faker->numberBetween(1, 100),
                    'message' => 'This task is significantly overdue.',
                ],
                [
                    'type' => 'risk',
                    'task_id' => $this->faker->numberBetween(1, 100),
                    'message' => 'This task may impact project deadline.',
                ],
            ],
            'summary' => 'Multiple tasks require immediate attention to avoid project delays.',
            'focus_areas' => ['overdue_tasks', 'deadline_risk'],
        ]);
    }
}
