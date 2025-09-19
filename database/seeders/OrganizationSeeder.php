<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the default 'None' organization
        $noneOrg = \App\Models\Organization::create([
            'name' => 'None',
            'domain' => null,
            'address' => null,
            'creator_id' => null,
            'is_default' => true,
        ]);

        // Create the default 'Everyone' group for the 'None' organization
        $everyoneGroup = \App\Models\Group::create([
            'name' => 'Everyone',
            'description' => 'Default group for individual users',
            'organization_id' => $noneOrg->id,
            'is_default' => true,
        ]);

        // Create default roles for the 'None' organization
        $adminRole = \App\Models\Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Organization administrator with full management rights',
            'organization_id' => $noneOrg->id,
            'permissions' => \App\Models\Role::getAdminPermissions(),
        ]);

        $userRole = \App\Models\Role::create([
            'name' => 'user',
            'display_name' => 'User',
            'description' => 'Standard organization member',
            'organization_id' => $noneOrg->id,
            'permissions' => \App\Models\Role::getUserPermissions(),
        ]);

        $this->command->info('Created default "None" organization with "Everyone" group and default roles');
    }
}
