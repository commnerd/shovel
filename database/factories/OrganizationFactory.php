<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'domain' => fake()->unique()->domainName(),
            'address' => fake()->address(),
            'is_default' => false,
        ];
    }

    /**
     * Indicate that this is the default 'None' organization.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'None',
            'domain' => null,
            'address' => null,
            'is_default' => true,
            'creator_id' => null,
        ]);
    }
}
