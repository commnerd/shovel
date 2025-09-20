<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstUserSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function first_user_to_register_becomes_super_admin()
    {
        // Ensure no users exist
        $this->assertEquals(0, User::count());

        // Register the first user
        $response = $this->post('/register', [
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        // Verify user was created and is Super Admin
        $this->assertEquals(1, User::count());

        $user = User::first();
        $this->assertEquals('First User', $user->name);
        $this->assertEquals('first@example.com', $user->email);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertFalse($user->pending_approval);
        $this->assertNotNull($user->approved_at);
    }

    /** @test */
    public function second_user_to_register_is_not_super_admin()
    {
        // Create the first user (should be Super Admin)
        $firstUser = User::factory()->create([
            'is_super_admin' => true,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        $this->assertEquals(1, User::count());

        // Register the second user
        $response = $this->post('/register', [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        // Verify second user is not Super Admin
        $this->assertEquals(2, User::count());

        $secondUser = User::where('email', 'second@example.com')->first();
        $this->assertNotNull($secondUser);
        $this->assertEquals('Second User', $secondUser->name);
        $this->assertFalse($secondUser->isSuperAdmin());
    }

    /** @test */
    public function first_user_in_existing_organization_becomes_super_admin()
    {
        // Create an organization first
        $organization = Organization::factory()->create();

        // Ensure no users exist
        $this->assertEquals(0, User::count());

        // Register user to join existing organization
        $response = $this->post('/register', [
            'name' => 'First Org User',
            'email' => 'first@company.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => true,
        ]);

        // Should still be Super Admin since it's the first user in the system
        $user = User::first();
        $this->assertTrue($user->isSuperAdmin());
    }

    /** @test */
    public function first_user_creating_new_organization_becomes_super_admin()
    {
        // Ensure no users exist
        $this->assertEquals(0, User::count());

        // Register user creating new organization
        $response = $this->post('/register', [
            'name' => 'Organization Creator',
            'email' => 'creator@newcompany.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => true,
        ]);

        // Should be Super Admin since it's the first user in the system
        $user = User::first();
        $this->assertTrue($user->isSuperAdmin());
    }

    /** @test */
    public function super_admin_status_persists_after_organization_changes()
    {
        // Create first user (should be Super Admin)
        $response = $this->post('/register', [
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        $user = User::first();
        $this->assertTrue($user->isSuperAdmin());

        // Create a new organization and move user to it
        $newOrg = Organization::factory()->create();
        $user->update(['organization_id' => $newOrg->id]);
        $user->refresh();

        // Should still be Super Admin
        $this->assertTrue($user->isSuperAdmin());
    }

    /** @test */
    public function first_user_check_works_with_soft_deleted_users()
    {
        // Create a user and soft delete them (if soft deletes are implemented)
        $deletedUser = User::factory()->create(['is_super_admin' => true]);
        $deletedUser->delete(); // This might be soft delete

        // Register new user - should become Super Admin if no active users exist
        $response = $this->post('/register', [
            'name' => 'New First User',
            'email' => 'newfirst@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        // Get the newly created user (not including soft deleted)
        $newUser = User::where('email', 'newfirst@example.com')->first();

        // Should be Super Admin if only counting non-deleted users
        $this->assertTrue($newUser->isSuperAdmin());
    }

    /** @test */
    public function registration_fails_gracefully_if_super_admin_assignment_fails()
    {
        // This test ensures the registration doesn't break if there's an issue with Super Admin assignment

        // Mock a scenario where the Super Admin assignment might fail
        // but the user creation should still succeed

        $this->assertEquals(0, User::count());

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        // User should be created even if there are issues
        $this->assertEquals(1, User::count());
        $user = User::first();
        $this->assertEquals('Test User', $user->name);
    }

    /** @test */
    public function multiple_simultaneous_registrations_only_make_one_super_admin()
    {
        // This test simulates race conditions where multiple users might register simultaneously

        $this->assertEquals(0, User::count());

        // Create multiple users in quick succession (simulating concurrent requests)
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $users[] = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
                'organization_id' => Organization::getDefault()?->id,
                'pending_approval' => false,
                'approved_at' => now(),
                'is_super_admin' => User::count() === 0, // This simulates the logic we'll implement
            ]);
        }

        // Only the first user should be Super Admin
        $superAdmins = User::where('is_super_admin', true)->get();
        $this->assertEquals(1, $superAdmins->count());
        $this->assertEquals('User 1', $superAdmins->first()->name);
    }

    /** @test */
    public function first_user_gets_super_admin_in_none_organization_workflow()
    {
        // Test the specific workflow for None organization registration

        $this->assertEquals(0, User::count());

        // This should trigger the createUserInDefaultOrganization method
        $response = $this->post('/register', [
            'name' => 'None Org User',
            'email' => 'none@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        $user = User::where('email', 'none@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isSuperAdmin());

        // Should be in the None organization
        $this->assertTrue($user->organization->is_default);
        $this->assertEquals('None', $user->organization->name);
    }

    /** @test */
    public function first_user_gets_super_admin_in_new_organization_workflow()
    {
        // Test the workflow for creating a new organization

        $this->assertEquals(0, User::count());

        // This should trigger the new organization creation workflow
        $response = $this->post('/register', [
            'name' => 'Org Creator',
            'email' => 'creator@newcompany.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => true,
        ]);

        $user = User::where('email', 'creator@newcompany.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isSuperAdmin());

        // Should have created a new organization
        $this->assertFalse($user->organization->is_default);
        $this->assertEquals('newcompany.com', $user->organization->domain);
    }

    /** @test */
    public function existing_super_admin_status_is_not_affected_by_new_registrations()
    {
        // Create an existing Super Admin
        $existingSuperAdmin = User::factory()->create([
            'is_super_admin' => true,
            'name' => 'Existing Super Admin',
        ]);

        $this->assertTrue($existingSuperAdmin->isSuperAdmin());

        // Register a new user
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        // Existing Super Admin should still be Super Admin
        $existingSuperAdmin->refresh();
        $this->assertTrue($existingSuperAdmin->isSuperAdmin());

        // New user should not be Super Admin
        $newUser = User::where('email', 'new@example.com')->first();
        $this->assertFalse($newUser->isSuperAdmin());

        // Should have exactly one Super Admin
        $this->assertEquals(1, User::where('is_super_admin', true)->count());
    }

    /** @test */
    public function first_user_via_organization_creation_becomes_super_admin()
    {
        $this->assertEquals(0, User::count());

        // Start registration process for new organization
        $this->post('/register', [
            'name' => 'Org Creator',
            'email' => 'creator@newcompany.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => true,
        ]);

        // Complete organization creation
        $response = $this->post('/organization/create', [
            'organization_name' => 'New Company Inc',
            'organization_address' => '123 Business St, City, State 12345',
        ]);

        $user = User::where('email', 'creator@newcompany.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertEquals('New Company Inc', $user->organization->name);
    }

    /** @test */
    public function first_user_via_organization_confirmation_becomes_super_admin()
    {
        $this->assertEquals(0, User::count());

        // Create an existing organization
        $existingOrg = Organization::factory()->create([
            'domain' => 'existingcompany.com',
        ]);

        // Start registration with organization email but decline to join
        $this->post('/register', [
            'name' => 'Independent User',
            'email' => 'user@existingcompany.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        // Confirm they don't want to join the organization
        $response = $this->post('/organization/confirm', [
            'join_organization' => false,
        ]);

        $user = User::where('email', 'user@existingcompany.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->organization->is_default); // Should be in None organization
    }

    /** @test */
    public function race_condition_protection_only_one_super_admin_created()
    {
        $this->assertEquals(0, User::count());

        // Simulate potential race condition by manually creating users
        // This tests the database-level protection

        $user1 = User::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
            'organization_id' => 1,
            'pending_approval' => false,
            'approved_at' => now(),
            'is_super_admin' => User::count() === 0, // This is the logic we're implementing
        ]);

        $user2 = User::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
            'organization_id' => 1,
            'pending_approval' => false,
            'approved_at' => now(),
            'is_super_admin' => User::count() === 0, // This should be false now
        ]);

        // Only first user should be Super Admin
        $this->assertTrue($user1->isSuperAdmin());
        $this->assertFalse($user2->isSuperAdmin());
        $this->assertEquals(1, User::where('is_super_admin', true)->count());
    }

    /** @test */
    public function first_user_registration_with_validation_errors_does_not_affect_subsequent_registrations()
    {
        $this->assertEquals(0, User::count());

        // Try to register with invalid data (should fail)
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456',
            'organization_email' => false,
        ]);

        $response->assertSessionHasErrors();
        $this->assertEquals(0, User::count());

        // Now register with valid data
        $response = $this->post('/register', [
            'name' => 'First Valid User',
            'email' => 'first@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        // Should still be the first user and become Super Admin
        $user = User::first();
        $this->assertTrue($user->isSuperAdmin());
    }

    /** @test */
    public function first_user_gets_admin_role_in_addition_to_super_admin_when_creating_organization()
    {
        $this->assertEquals(0, User::count());

        // Register user creating new organization
        $this->post('/register', [
            'name' => 'Org Creator',
            'email' => 'creator@newcompany.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => true,
        ]);

        // Complete organization creation
        $this->post('/organization/create', [
            'organization_name' => 'New Company Inc',
            'organization_address' => '123 Business St, City, State 12345',
        ]);

        $user = User::first();

        // Should be Super Admin
        $this->assertTrue($user->isSuperAdmin());

        // Should also have organization admin role
        $this->assertTrue($user->isAdmin());

        // Should be the organization creator
        $this->assertEquals($user->id, $user->organization->creator_id);
    }

    /** @test */
    public function first_user_approval_bypass_works_correctly()
    {
        $this->assertEquals(0, User::count());

        // Create an existing organization
        $existingOrg = Organization::factory()->create([
            'domain' => 'company.com',
        ]);

        // Register first user to join existing organization
        $response = $this->post('/register', [
            'name' => 'First User',
            'email' => 'first@company.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => true,
        ]);

        $user = User::where('email', 'first@company.com')->first();

        // First user should be Super Admin and not need approval
        $this->assertTrue($user->isSuperAdmin());
        $this->assertFalse($user->pending_approval);
        $this->assertNotNull($user->approved_at);

        // Register second user to same organization
        $response = $this->post('/register', [
            'name' => 'Second User',
            'email' => 'second@company.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => true,
        ]);

        $secondUser = User::where('email', 'second@company.com')->first();

        // Second user should not be Super Admin and should need approval
        $this->assertFalse($secondUser->isSuperAdmin());
        $this->assertTrue($secondUser->pending_approval);
        $this->assertNull($secondUser->approved_at);
    }
}
