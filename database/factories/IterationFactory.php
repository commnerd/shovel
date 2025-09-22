<?php

namespace Database\Factories;

use App\Models\Iteration;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Iteration>
 */
class IterationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Iteration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', '+2 months');
        $endDate = (clone $startDate)->modify('+2 weeks');

        return [
            'project_id' => Project::factory(),
            'name' => 'Sprint ' . $this->faker->numberBetween(1, 20),
            'description' => $this->faker->optional(0.7)->sentence(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $this->faker->randomElement(['planned', 'active', 'completed', 'cancelled']),
            'capacity_points' => $this->faker->optional(0.8)->numberBetween(20, 100),
            'committed_points' => $this->faker->numberBetween(0, 80),
            'completed_points' => $this->faker->numberBetween(0, 60),
            'sort_order' => $this->faker->numberBetween(1, 10),
            'goals' => $this->faker->optional(0.6)->randomElements([
                'Complete user authentication',
                'Implement dashboard',
                'Fix critical bugs',
                'Improve performance',
                'Add mobile support',
            ], $this->faker->numberBetween(1, 3)),
        ];
    }

    /**
     * Indicate that the iteration is planned.
     */
    public function planned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'planned',
            'committed_points' => 0,
            'completed_points' => 0,
        ]);
    }

    /**
     * Indicate that the iteration is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'start_date' => now()->subDays(3),
            'end_date' => now()->addDays(11),
        ]);
    }

    /**
     * Indicate that the iteration is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'start_date' => now()->subDays(17),
            'end_date' => now()->subDays(3),
            'completed_points' => $attributes['committed_points'] ?? $this->faker->numberBetween(20, 80),
        ]);
    }

    /**
     * Indicate that the iteration is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'completed_points' => 0,
        ]);
    }

    /**
     * Create an iteration with high capacity.
     */
    public function highCapacity(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity_points' => $this->faker->numberBetween(80, 150),
            'committed_points' => $this->faker->numberBetween(60, 120),
        ]);
    }

    /**
     * Create an iteration with low capacity.
     */
    public function lowCapacity(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity_points' => $this->faker->numberBetween(10, 30),
            'committed_points' => $this->faker->numberBetween(5, 25),
        ]);
    }
}
