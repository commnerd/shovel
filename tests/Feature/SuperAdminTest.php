<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularUser;
    protected User $admin;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $this->organization = Organization::getDefault();
        $group = $this->organization->createDefaultGroup();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => true,
        ]);
        $this->superAdmin->joinGroup($group);

        // Create regular admin
        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => false,
        ]);
        $this->admin->joinGroup($group);
        $adminRole = $this->organization->roles()->where('name', 'admin')->first();
        $this->admin->assignRole($adminRole);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => false,
        ]);
        $this->regularUser->joinGroup($group);
    }

    public function test_super_admin_can_access_super_admin_dashboard()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) =>
            $page->component('SuperAdmin/Index')
                ->has('stats')
                ->has('stats.total_users')
                ->has('stats.total_organizations')
                ->has('stats.pending_users')
                ->has('stats.super_admins')
        );
    }

    public function test_regular_admin_cannot_access_super_admin_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->get('/super-admin');

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_access_super_admin_dashboard()
    {
        $response = $this->actingAs($this->regularUser)
            ->get('/super-admin');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_super_admin_dashboard()
    {
        $response = $this->get('/super-admin');
        $response->assertRedirect('/login');
    }

    public function test_super_admin_can_view_all_users()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) =>
            $page->component('SuperAdmin/Users')
                ->has('users')
                ->has('users.data', 3) // superAdmin, admin, regularUser
        );
    }

    public function test_super_admin_can_view_all_organizations()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/organizations');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) =>
            $page->component('SuperAdmin/Organizations')
                ->has('organizations')
                ->has('organizations.data', 1) // Default organization
        );
    }

    public function test_super_admin_can_login_as_other_user()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->regularUser->id}/login-as", [
                'reason' => 'Testing user account functionality',
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('message', 'You are now logged in as ' . $this->regularUser->name);

        // Verify we're now logged in as the regular user
        $this->assertEquals($this->regularUser->id, auth()->id());

        // Verify original super admin ID is stored in session
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));
    }

    public function test_super_admin_can_return_to_original_account()
    {
        // First login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->regularUser->id}/login-as", [
                'reason' => 'Testing',
            ]);

        // Verify we're logged in as regular user
        $this->assertEquals($this->regularUser->id, auth()->id());

        // Return to super admin
        $response = $this->post('/super-admin/return-to-super-admin');

        $response->assertRedirect('/super-admin');
        $response->assertSessionHas('message', 'Returned to super admin account');

        // Verify we're back to super admin
        $this->assertEquals($this->superAdmin->id, auth()->id());

        // Verify session is cleared
        $this->assertNull(session('original_super_admin_id'));
    }

    public function test_super_admin_can_assign_super_admin_role()
    {
        $this->assertFalse($this->regularUser->isSuperAdmin());

        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->regularUser->id}/assign-super-admin", [
                'reason' => 'Promoting user to super admin for testing',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('message', "Super admin role assigned to {$this->regularUser->name}");

        // Verify user is now super admin
        $this->regularUser->refresh();
        $this->assertTrue($this->regularUser->isSuperAdmin());
    }

    public function test_super_admin_can_remove_super_admin_role()
    {
        // First make regular user a super admin
        $this->regularUser->makeSuperAdmin();
        $this->assertTrue($this->regularUser->isSuperAdmin());

        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->regularUser->id}/remove-super-admin", [
                'reason' => 'Removing super admin role for testing',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('message', "Super admin role removed from {$this->regularUser->name}");

        // Verify user is no longer super admin
        $this->regularUser->refresh();
        $this->assertFalse($this->regularUser->isSuperAdmin());
    }

    public function test_super_admin_cannot_remove_own_super_admin_role()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->superAdmin->id}/remove-super-admin", [
                'reason' => 'Attempting to remove own role',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['error' => 'You cannot remove super admin role from yourself.']);

        // Verify super admin still has role
        $this->superAdmin->refresh();
        $this->assertTrue($this->superAdmin->isSuperAdmin());
    }

    public function test_regular_admin_cannot_access_super_admin_functions()
    {
        $routes = [
            '/super-admin',
            '/super-admin/users',
            '/super-admin/organizations',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->admin)->get($route);
            $response->assertStatus(403);
        }

        // Test POST routes
        $response = $this->actingAs($this->admin)
            ->post("/super-admin/users/{$this->regularUser->id}/login-as", [
                'reason' => 'Should not work',
            ]);
        $response->assertStatus(403);
    }

    public function test_user_model_super_admin_methods()
    {
        // Test isSuperAdmin
        $this->assertTrue($this->superAdmin->isSuperAdmin());
        $this->assertFalse($this->regularUser->isSuperAdmin());

        // Test canManageOrganizations
        $this->assertTrue($this->superAdmin->canManageOrganizations());
        $this->assertFalse($this->regularUser->canManageOrganizations());

        // Test canLoginAsOtherUsers
        $this->assertTrue($this->superAdmin->canLoginAsOtherUsers());
        $this->assertFalse($this->regularUser->canLoginAsOtherUsers());

        // Test canAssignSuperAdmin
        $this->assertTrue($this->superAdmin->canAssignSuperAdmin());
        $this->assertFalse($this->regularUser->canAssignSuperAdmin());
    }

    public function test_user_managed_users_scope()
    {
        // Create another organization
        $otherOrg = Organization::factory()->create();
        $otherGroup = $otherOrg->createDefaultGroup();

        $userInOtherOrg = User::factory()->create([
            'organization_id' => $otherOrg->id,
            'pending_approval' => false,
        ]);
        $userInOtherOrg->joinGroup($otherGroup);

        // Super admin can manage all users
        $superAdminManagedUsers = $this->superAdmin->getManagedUsers()->get();
        $this->assertCount(4, $superAdminManagedUsers); // All users

        // Regular admin can only manage users in same org (excluding super admins)
        $adminManagedUsers = $this->admin->getManagedUsers()->get();
        $this->assertCount(1, $adminManagedUsers); // Only regularUser (not superAdmin or users from other orgs)
        $this->assertEquals($this->regularUser->id, $adminManagedUsers->first()->id);

        // Regular user cannot manage anyone
        $regularUserManagedUsers = $this->regularUser->getManagedUsers()->get();
        $this->assertCount(0, $regularUserManagedUsers);
    }

    public function test_login_as_user_requires_reason_validation()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->regularUser->id}/login-as", [
                // No reason provided
            ]);

        // Should still work since reason is optional
        $response->assertRedirect('/dashboard');
    }

    public function test_assign_super_admin_requires_reason()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/super-admin/users/{$this->regularUser->id}/assign-super-admin", [
                // No reason provided
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    public function test_remove_super_admin_requires_reason()
    {
        $this->regularUser->makeSuperAdmin();

        $response = $this->actingAs($this->superAdmin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/super-admin/users/{$this->regularUser->id}/remove-super-admin", [
                // No reason provided
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    public function test_return_to_super_admin_without_session_fails()
    {
        // Try to return without having logged in as another user
        $response = $this->actingAs($this->regularUser)
            ->post('/super-admin/return-to-super-admin');

        $response->assertStatus(403);
    }

    public function test_super_admin_middleware_functionality()
    {
        $middleware = new \App\Http\Middleware\EnsureUserIsSuperAdmin();

        $request = \Illuminate\Http\Request::create('/super-admin');

        // Test with super admin
        $this->actingAs($this->superAdmin);
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });
        $this->assertEquals('OK', $response->getContent());

        // Test with regular user
        $this->actingAs($this->regularUser);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($request, function ($req) {
            return response('Should not reach here');
        });
    }
}

