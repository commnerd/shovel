<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CuratedTasks>
 */
class CuratedTasksFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'curatable_type' => \App\Models\Task::class,
            'curatable_id' => \App\Models\Task::factory(),
            'work_date' => $this->faker->date(),
            'assigned_to' => \App\Models\User::factory(),
            'initial_index' => $this->faker->numberBetween(1, 10),
            'current_index' => $this->faker->numberBetween(1, 10),
            'moved_count' => $this->faker->numberBetween(0, 5),
        ];
    }

    /**
     * Create a curated task for today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'work_date' => now()->toDateString(),
        ]);
    }

    /**
     * Create a curated task for a specific user.
     */
    public function forUser(\App\Models\User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $user->id,
        ]);
    }

    /**
     * Create a curated task for a specific task.
     */
    public function forTask(\App\Models\Task $task): static
    {
        return $this->state(fn (array $attributes) => [
            'curatable_type' => \App\Models\Task::class,
            'curatable_id' => $task->id,
        ]);
    }

    /**
     * Create a curated task that has been moved.
     */
    public function moved(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_index' => $this->faker->numberBetween(1, 10),
            'moved_count' => $this->faker->numberBetween(1, 5),
        ]);
    }
}
