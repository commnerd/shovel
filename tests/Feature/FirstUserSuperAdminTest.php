<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstUserSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the first user to register becomes a Super Admin.
     */
    public function test_first_user_becomes_super_admin()
    {
        // Ensure no users exist initially
        $this->assertEquals(0, User::count());

        // Register the first user
        $response = $this->post('/register', [
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        // Should be redirected to dashboard
        $response->assertRedirect('/dashboard');

        // Verify user was created
        $this->assertEquals(1, User::count());

        $user = User::first();
        $this->assertEquals('First User', $user->name);
        $this->assertEquals('first@example.com', $user->email);

        // Verify user is Super Admin
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue($user->isSuperAdmin());

        // Verify user is approved and not pending
        $this->assertFalse($user->pending_approval);
        $this->assertNotNull($user->approved_at);
    }

    /**
     * Test that subsequent users do not become Super Admin.
     */
    public function test_subsequent_users_do_not_become_super_admin()
    {
        // Create first user (Super Admin)
        $firstUser = User::factory()->create([
            'is_super_admin' => true,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Verify first user is Super Admin
        $this->assertTrue($firstUser->is_super_admin);

        // Register second user
        $response = $this->post('/register', [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        // Should be redirected to dashboard
        $response->assertRedirect('/dashboard');

        // Verify second user was created
        $this->assertEquals(2, User::count());

        $secondUser = User::where('email', 'second@example.com')->first();
        $this->assertEquals('Second User', $secondUser->name);

        // Verify second user is NOT Super Admin
        $this->assertFalse($secondUser->is_super_admin);
        $this->assertFalse($secondUser->isSuperAdmin());
    }

    /**
     * Test that first user with organization email becomes Super Admin.
     */
    public function test_first_user_with_organization_email_becomes_super_admin()
    {
        // Ensure no users exist initially
        $this->assertEquals(0, User::count());

        // Create an organization first
        $organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'testorg.com',
        ]);

        // Register the first user with organization email
        $response = $this->post('/register', [
            'name' => 'First Org User',
            'email' => 'admin@testorg.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        // Should be redirected to login (pending approval)
        $response->assertRedirect('/login');
        $response->assertSessionHas('status', 'registration-pending');

        // Verify user was created
        $this->assertEquals(1, User::count());

        $user = User::first();
        $this->assertEquals('First Org User', $user->name);
        $this->assertEquals('admin@testorg.com', $user->email);

        // Verify user is Super Admin
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue($user->isSuperAdmin());

        // Verify user is approved (first user doesn't need approval)
        $this->assertFalse($user->pending_approval);
        $this->assertNotNull($user->approved_at);
    }

    /**
     * Test that first user gets proper role assignment.
     */
    public function test_first_user_gets_proper_role_assignment()
    {
        // Ensure no users exist initially
        $this->assertEquals(0, User::count());

        // Register the first user
        $response = $this->post('/register', [
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::first();

        // Verify user has a role assigned
        $this->assertTrue($user->roles()->exists());

        // Verify user is in the default group
        $this->assertTrue($user->groups()->exists());

        // Verify user belongs to an organization
        $this->assertNotNull($user->organization_id);
    }

    /**
     * Test that first user can manage other users.
     */
    public function test_first_user_can_manage_other_users()
    {
        // Create first user (Super Admin)
        $firstUser = User::factory()->create([
            'is_super_admin' => true,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Create second user
        $secondUser = User::factory()->create([
            'is_super_admin' => false,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Verify first user can manage organizations (super admin privilege)
        $this->assertTrue($firstUser->canManageOrganizations());

        // Verify first user can login as other users (super admin privilege)
        $this->assertTrue($firstUser->canLoginAsOtherUsers());

        // Verify first user can assign super admin role (super admin privilege)
        $this->assertTrue($firstUser->canAssignSuperAdmin());

        // Verify second user cannot manage organizations
        $this->assertFalse($secondUser->canManageOrganizations());

        // Verify second user cannot login as other users
        $this->assertFalse($secondUser->canLoginAsOtherUsers());

        // Verify second user cannot assign super admin role
        $this->assertFalse($secondUser->canAssignSuperAdmin());
    }

    /**
     * Test that first user has all super admin permissions.
     */
    public function test_first_user_has_super_admin_permissions()
    {
        // Create first user (Super Admin)
        $firstUser = User::factory()->create([
            'is_super_admin' => true,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Verify super admin status methods
        $this->assertTrue($firstUser->isSuperAdmin());
        $this->assertTrue($firstUser->is_super_admin);

        // Verify user has all super admin privileges
        $this->assertTrue($firstUser->canManageOrganizations());
        $this->assertTrue($firstUser->canLoginAsOtherUsers());
        $this->assertTrue($firstUser->canAssignSuperAdmin());
    }
}
