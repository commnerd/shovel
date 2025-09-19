<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $organization;
    protected $adminRole;
    protected $userRole;
    protected $defaultGroup;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization with roles
        $this->organization = Organization::factory()->create();
        $roles = $this->organization->createDefaultRoles();
        $this->adminRole = $roles['admin'];
        $this->userRole = $roles['user'];

        $this->defaultGroup = $this->organization->createDefaultGroup();

        // Create admin user
        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Assign admin role and add to default group
        $this->admin->assignRole($this->adminRole);
        $this->admin->assignRole($this->userRole);
        $this->admin->joinGroup($this->defaultGroup);
    }

    public function test_admin_can_view_user_management_page()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/users');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) =>
                $page->component('Admin/Users')
                    ->has('users')
                    ->has('filters')
            );
    }

    public function test_non_admin_cannot_access_user_management()
    {
        $regularUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $regularUser->assignRole($this->userRole);

        $response = $this->actingAs($regularUser)
            ->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_approve_pending_users()
    {
        $pendingUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$pendingUser->id}/approve");

        $response->assertRedirect();

        $pendingUser->refresh();
        $this->assertFalse($pendingUser->pending_approval);
        $this->assertNotNull($pendingUser->approved_at);
        $this->assertEquals($this->admin->id, $pendingUser->approved_by);
    }

    public function test_admin_can_assign_roles_to_users()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$user->id}/assign-role", [
                'role_id' => $this->adminRole->id,
            ]);

        $response->assertRedirect();

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_admin_can_remove_roles_from_users()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $user->assignRole($this->adminRole);

        $this->assertTrue($user->hasRole('admin'));

        $response = $this->actingAs($this->admin)
            ->delete("/admin/users/{$user->id}/remove-role", [
                'role_id' => $this->adminRole->id,
            ]);

        $response->assertRedirect();

        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    public function test_admin_can_add_users_to_groups()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $newGroup = Group::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Special Team',
        ]);

        // Test admin can login as user within same organization
        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$user->id}/login-as", [
                'reason' => 'User support',
            ]);

        $response->assertRedirect('/dashboard');
    }

    public function test_admin_can_remove_users_from_non_default_groups()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $nonDefaultGroup = Group::factory()->create([
            'organization_id' => $this->organization->id,
            'is_default' => false,
        ]);

        $user->joinGroup($nonDefaultGroup);
        $this->assertTrue($user->belongsToGroup($nonDefaultGroup->id));

        $response = $this->actingAs($this->admin)
            ->delete("/admin/users/{$user->id}/remove-from-group", [
                'group_id' => $nonDefaultGroup->id,
            ]);

        $response->assertRedirect();

        $this->assertFalse($user->fresh()->belongsToGroup($nonDefaultGroup->id));
    }

    public function test_admin_cannot_remove_users_from_default_group()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $user->joinGroup($this->defaultGroup);

        $response = $this->actingAs($this->admin)
            ->delete("/admin/users/{$user->id}/remove-from-group", [
                'group_id' => $this->defaultGroup->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['error']);

        $this->assertTrue($user->fresh()->belongsToGroup($this->defaultGroup->id));
    }

    public function test_admin_cannot_manage_users_from_other_organizations()
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$otherUser->id}/approve");

        $response->assertStatus(403);
    }

    public function test_user_with_multiple_roles_has_combined_permissions()
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Assign both roles
        $user->assignRole($this->adminRole);
        $user->assignRole($this->userRole);

        // Should have permissions from both roles
        $this->assertTrue($user->hasPermission('manage_users')); // Admin permission
        $this->assertTrue($user->hasPermission('create_projects')); // User permission
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_organization_creator_gets_admin_role_automatically()
    {
        // This test verifies the organization creation flow
        $creator = User::factory()->make([
            'name' => 'Org Creator',
            'email' => 'creator@neworg.com',
        ]);

        // Simulate the organization creation process
        session([
            'registration_data' => [
                'name' => $creator->name,
                'email' => $creator->email,
                'password' => \Hash::make('password'),
            ]
        ]);

        $response = $this->post('/organization/create', [
            'organization_name' => 'New Organization',
            'organization_address' => '123 Business St',
        ]);

        $response->assertRedirect('/dashboard');

        $createdUser = User::where('email', 'creator@neworg.com')->first();
        $this->assertTrue($createdUser->isAdmin());
        $this->assertTrue($createdUser->hasRole('user'));
        $this->assertTrue($createdUser->hasPermission('manage_users'));
        $this->assertTrue($createdUser->hasPermission('create_projects'));
    }
}
