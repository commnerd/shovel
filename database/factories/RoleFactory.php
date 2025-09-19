<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['admin', 'user']),
            'display_name' => fake()->jobTitle(),
            'description' => fake()->sentence(),
            'organization_id' => \App\Models\Organization::factory(),
            'permissions' => [],
        ];
    }

    /**
     * Indicate that this is an admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Organization administrator with full management rights',
            'permissions' => \App\Models\Role::getAdminPermissions(),
        ]);
    }

    /**
     * Indicate that this is a user role.
     */
    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'user',
            'display_name' => 'User',
            'description' => 'Standard organization member',
            'permissions' => \App\Models\Role::getUserPermissions(),
        ]);
    }
}
