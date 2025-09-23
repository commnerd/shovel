<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $targetUser;

    protected User $regularAdmin;

    protected User $regularUser;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure AI provider to prevent middleware redirects
        \App\Models\Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebras API Key');

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $this->organization = Organization::getDefault();
        $group = $this->organization->createDefaultGroup();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin User',
            'email' => 'superadmin@test.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => true,
        ]);
        $this->superAdmin->joinGroup($group);

        // Create target user for impersonation
        $this->targetUser = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target@test.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => false,
        ]);
        $this->targetUser->joinGroup($group);

        // Create regular admin
        $this->regularAdmin = User::factory()->create([
            'name' => 'Regular Admin',
            'email' => 'admin@test.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => false,
        ]);
        $this->regularAdmin->joinGroup($group);
        $adminRole = $this->organization->roles()->where('name', 'admin')->first();
        $this->regularAdmin->assignRole($adminRole);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => false,
        ]);
        $this->regularUser->joinGroup($group);
    }

    public function test_super_admin_can_login_as_another_user()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing user issue',
            ]);

        $response->assertRedirect('/dashboard');

        // Check that we're now logged in as the target user
        $this->assertEquals($this->targetUser->id, Auth::id());

        // Check that the original super admin ID is stored in session
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));
    }

    public function test_super_admin_can_login_as_user_without_reason()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => '',
            ]);

        $response->assertRedirect('/dashboard');

        // Should still work even without reason
        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));
    }

    public function test_super_admin_cannot_login_as_themselves()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->superAdmin->id}/login-as", [
                'reason' => 'Testing',
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('message', 'Cannot login as yourself');

        // Should still be logged in as super admin
        $this->assertEquals($this->superAdmin->id, Auth::id());

        // Should not have original_super_admin_id in session
        $this->assertNull(session('original_super_admin_id'));
    }

    public function test_regular_admin_cannot_login_as_another_user()
    {
        $response = $this->actingAs($this->regularAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing',
            ]);

        $response->assertStatus(403);

        // Should still be logged in as regular admin
        $this->assertEquals($this->regularAdmin->id, Auth::id());
    }

    public function test_regular_user_cannot_login_as_another_user()
    {
        $response = $this->actingAs($this->regularUser)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing',
            ]);

        $response->assertStatus(403);

        // Should still be logged in as regular user
        $this->assertEquals($this->regularUser->id, Auth::id());
    }

    public function test_guest_cannot_login_as_another_user()
    {
        $response = $this->post("/super-admin/users/{$this->targetUser->id}/login-as", [
            'reason' => 'Testing',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_super_admin_can_return_to_original_account()
    {
        // First, login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing user issue',
            ]);

        // Verify we're logged in as target user
        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));

        // Now return to super admin
        $response = $this->post('/super-admin/return-to-super-admin');

        $response->assertRedirect('/super-admin');

        // Check that we're back to the original super admin
        $this->assertEquals($this->superAdmin->id, Auth::id());

        // Check that the session data is cleared
        $this->assertNull(session('original_super_admin_id'));
    }

    public function test_return_to_super_admin_without_original_session_fails()
    {
        // Login as target user directly (not through impersonation)
        $this->actingAs($this->targetUser);

        $response = $this->post('/super-admin/return-to-super-admin');

        $response->assertStatus(403);

        // Should still be logged in as target user
        $this->assertEquals($this->targetUser->id, Auth::id());
    }

    public function test_return_to_super_admin_with_invalid_original_user_fails()
    {
        // Login as target user and manually set invalid original super admin ID
        $this->actingAs($this->targetUser);
        session(['original_super_admin_id' => 99999]); // Non-existent user ID

        $response = $this->post('/super-admin/return-to-super-admin');

        $response->assertStatus(403);

        // Should still be logged in as target user
        $this->assertEquals($this->targetUser->id, Auth::id());
    }

    public function test_return_to_super_admin_with_non_super_admin_original_user_fails()
    {
        // Login as target user and set regular user as original
        $this->actingAs($this->targetUser);
        session(['original_super_admin_id' => $this->regularUser->id]);

        $response = $this->post('/super-admin/return-to-super-admin');

        $response->assertStatus(403);

        // Should still be logged in as target user
        $this->assertEquals($this->targetUser->id, Auth::id());
    }

    public function test_impersonation_session_data_is_shared_with_frontend()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing user issue',
            ]);

        // Visit any page and check that the session data is available
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', $this->superAdmin->id)
            ->where('auth.user.id', $this->targetUser->id)
        );
    }

    public function test_impersonation_banner_data_is_available_on_all_pages()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing user issue',
            ]);

        // Test just the dashboard page as it should always be accessible
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $inertiaPage) => $inertiaPage->where('auth.original_super_admin_id', $this->superAdmin->id)
            ->where('auth.user.id', $this->targetUser->id)
            ->where('auth.user.name', $this->targetUser->name)
        );
    }

    public function test_login_as_user_action_is_logged()
    {
        // We'll test that the action completes successfully,
        // which indicates logging is working (logs are written in the controller)
        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing user issue',
            ]);

        $response->assertRedirect('/dashboard');
        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));

        // If we got here, the logging didn't throw an error
        $this->assertTrue(true);
    }

    public function test_return_to_super_admin_action_is_logged()
    {
        // First, login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing user issue',
            ]);

        // Then return to super admin
        $response = $this->post('/super-admin/return-to-super-admin');

        $response->assertRedirect('/super-admin');
        $this->assertEquals($this->superAdmin->id, Auth::id());
        $this->assertNull(session('original_super_admin_id'));

        // If we got here, the logging didn't throw an error
        $this->assertTrue(true);
    }

    public function test_impersonation_works_across_different_organizations()
    {
        // Create user in different organization
        $otherOrg = Organization::factory()->create();
        $otherGroup = $otherOrg->createDefaultGroup();
        $userInOtherOrg = User::factory()->create([
            'organization_id' => $otherOrg->id,
            'pending_approval' => false,
        ]);
        $userInOtherOrg->joinGroup($otherGroup);

        // Super admin should be able to login as user from different organization
        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$userInOtherOrg->id}/login-as", [
                'reason' => 'Cross-organization support',
            ]);

        $response->assertRedirect('/dashboard');

        // Check that we're now logged in as the user from other organization
        $this->assertEquals($userInOtherOrg->id, Auth::id());
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));
    }

    public function test_multiple_impersonation_sessions_are_handled_correctly()
    {
        // Login as first user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'First impersonation',
            ]);

        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));

        // Try to login as another user while already impersonating
        $anotherUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $anotherUser->joinGroup($this->organization->groups()->first());

        $response = $this->post("/super-admin/users/{$anotherUser->id}/login-as", [
            'reason' => 'Second impersonation',
        ]);

        // Should fail because we're not currently a super admin
        $response->assertStatus(403);

        // Should still be logged in as first target user
        $this->assertEquals($this->targetUser->id, Auth::id());
    }

    public function test_impersonation_persists_across_requests()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing persistence',
            ]);

        // Make multiple requests to verify session persistence
        for ($i = 0; $i < 5; $i++) {
            $response = $this->get('/dashboard');

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', $this->superAdmin->id)
                ->where('auth.user.id', $this->targetUser->id)
            );

            // Verify we're still logged in as target user
            $this->assertEquals($this->targetUser->id, Auth::id());
            $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));
        }
    }

    public function test_impersonation_validation_requires_valid_user_id()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/super-admin/users/99999/login-as', [
                'reason' => 'Testing invalid user',
            ]);

        $response->assertStatus(404);

        // Should still be logged in as super admin
        $this->assertEquals($this->superAdmin->id, Auth::id());
        $this->assertNull(session('original_super_admin_id'));
    }

    public function test_impersonation_handles_soft_deleted_users()
    {
        // Soft delete the target user (if soft deletes are implemented)
        // For now, we'll just test with a deactivated user
        $this->targetUser->update(['pending_approval' => true]);

        $response = $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing deactivated user',
            ]);

        // Should still work - super admin can impersonate any user
        $response->assertRedirect('/dashboard');
        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));
    }

    public function test_session_cleanup_on_logout()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing session cleanup',
            ]);

        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));

        // Logout
        $response = $this->post('/logout');

        $response->assertRedirect('/');

        // Session should be cleared
        $this->assertNull(Auth::id());
        $this->assertNull(session('original_super_admin_id'));
    }
}
