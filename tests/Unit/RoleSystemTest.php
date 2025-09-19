<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $organization;
    protected $adminRole;
    protected $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->adminRole = Role::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->userRole = Role::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_role_can_be_created()
    {
        $role = Role::factory()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'organization_id' => $this->organization->id,
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'manager',
            'display_name' => 'Manager',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_role_belongs_to_organization()
    {
        $this->assertEquals($this->organization->id, $this->adminRole->organization->id);
        $this->assertEquals($this->organization->id, $this->userRole->organization->id);
    }

    public function test_organization_can_create_default_roles()
    {
        $newOrg = Organization::factory()->create();
        $roles = $newOrg->createDefaultRoles();

        $this->assertArrayHasKey('admin', $roles);
        $this->assertArrayHasKey('user', $roles);

        $this->assertEquals('admin', $roles['admin']->name);
        $this->assertEquals('user', $roles['user']->name);
        $this->assertEquals($newOrg->id, $roles['admin']->organization_id);
        $this->assertEquals($newOrg->id, $roles['user']->organization_id);
    }

    public function test_admin_role_has_correct_permissions()
    {
        $this->assertTrue($this->adminRole->hasPermission('manage_users'));
        $this->assertTrue($this->adminRole->hasPermission('manage_groups'));
        $this->assertTrue($this->adminRole->hasPermission('approve_users'));
        $this->assertFalse($this->adminRole->hasPermission('invalid_permission'));
    }

    public function test_user_role_has_correct_permissions()
    {
        $this->assertTrue($this->userRole->hasPermission('create_projects'));
        $this->assertTrue($this->userRole->hasPermission('view_own_projects'));
        $this->assertFalse($this->userRole->hasPermission('manage_users'));
        $this->assertFalse($this->userRole->hasPermission('approve_users'));
    }

    public function test_user_can_have_multiple_roles()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Assign both roles
        $user->assignRole($this->adminRole);
        $user->assignRole($this->userRole);

        $this->assertEquals(2, $user->roles()->count());
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_user_permission_checking()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // User with no roles has no permissions
        $this->assertFalse($user->hasPermission('manage_users'));

        // Assign admin role
        $user->assignRole($this->adminRole);

        $this->assertTrue($user->hasPermission('manage_users'));
        $this->assertTrue($user->hasPermission('manage_groups'));
        $this->assertTrue($user->isAdmin());
    }

    public function test_user_can_be_assigned_and_removed_from_roles()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $assignedBy = User::factory()->create();

        // Initially no roles
        $this->assertEquals(0, $user->roles()->count());

        // Assign role
        $user->assignRole($this->userRole, $assignedBy);

        $this->assertEquals(1, $user->roles()->count());
        $this->assertTrue($user->hasRole('user'));

        // Remove role
        $user->removeRole($this->userRole);

        $this->assertEquals(0, $user->roles()->count());
        $this->assertFalse($user->hasRole('user'));
    }

    public function test_assigning_same_role_twice_doesnt_create_duplicates()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $user->assignRole($this->userRole);
        $user->assignRole($this->userRole); // Try to assign again

        $this->assertEquals(1, $user->roles()->count());
    }

    public function test_user_can_get_all_permissions()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Assign both roles
        $user->assignRole($this->adminRole);
        $user->assignRole($this->userRole);

        $allPermissions = $user->getAllPermissions();

        $this->assertContains('manage_users', $allPermissions);
        $this->assertContains('create_projects', $allPermissions);
        $this->assertContains('view_own_projects', $allPermissions);
    }

    public function test_organization_can_get_specific_roles()
    {
        $adminRole = $this->organization->getAdminRole();
        $userRole = $this->organization->getUserRole();

        $this->assertNotNull($adminRole);
        $this->assertNotNull($userRole);
        $this->assertEquals('admin', $adminRole->name);
        $this->assertEquals('user', $userRole->name);
    }
}
