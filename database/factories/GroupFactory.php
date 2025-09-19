<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'organization_id' => \App\Models\Organization::factory(),
            'is_default' => false,
        ];
    }

    /**
     * Indicate that this is the default 'Everyone' group.
     */
    public function everyone(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Everyone',
            'description' => 'Default group for all organization members',
            'is_default' => true,
        ]);
    }
}
