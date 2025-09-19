<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // First create organizations and groups
        $this->call(OrganizationSeeder::class);

        // Get the default organization and group
        $defaultOrg = \App\Models\Organization::where('is_default', true)->first();
        $defaultGroup = $defaultOrg->defaultGroup();

        // Create test user and assign to default organization and group
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'organization_id' => $defaultOrg->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Add user to the default group
        $user->groups()->attach($defaultGroup->id, ['joined_at' => now()]);
    }
}
