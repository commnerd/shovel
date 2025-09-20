<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstUserIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function complete_first_user_registration_flow_creates_super_admin()
    {
        // Ensure clean state
        $this->assertEquals(0, User::count());

        // Register first user through complete flow
        $response = $this->post('/register', [
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'securepassword123',
            'password_confirmation' => 'securepassword123',
            'organization_email' => false,
        ]);

        // Should redirect to dashboard (successful registration)
        $response->assertRedirect('/dashboard');

        // Verify user was created with Super Admin privileges
        $this->assertEquals(1, User::count());

        $user = User::first();
        $this->assertEquals('System Administrator', $user->name);
        $this->assertEquals('admin@example.com', $user->email);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertFalse($user->pending_approval);
        $this->assertNotNull($user->approved_at);

        // Verify user can access Super Admin features
        $this->actingAs($user);

        // Should be able to access all AI settings
        $settingsResponse = $this->get('/settings/system');
        $settingsResponse->assertStatus(200);
        $settingsResponse->assertInertia(fn ($page) =>
            $page->where('permissions.canAccessProviderConfig', true)
                ->where('permissions.canAccessDefaultConfig', true)
        );

        // Should be able to access Super Admin routes
        $superAdminResponse = $this->get('/super-admin');
        $superAdminResponse->assertStatus(200);
    }

    /** @test */
    public function second_user_registration_does_not_get_super_admin()
    {
        // Create first user (manually to ensure they're Super Admin)
        $firstUser = User::factory()->create([
            'is_super_admin' => true,
            'name' => 'First User',
        ]);

        $this->assertEquals(1, User::count());
        $this->assertTrue($firstUser->isSuperAdmin());

        // Register second user
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
        $this->assertFalse($secondUser->isSuperAdmin());

        // Verify second user cannot access Super Admin features
        $this->actingAs($secondUser);

        $superAdminResponse = $this->get('/super-admin');
        $superAdminResponse->assertStatus(403);

        // Should only have limited AI settings access
        $settingsResponse = $this->get('/settings/system');
        $settingsResponse->assertStatus(200);
        $settingsResponse->assertInertia(fn ($page) =>
            $page->where('permissions.canAccessProviderConfig', false)
                ->where('permissions.canAccessDefaultConfig', true) // In None org
        );
    }

    /** @test */
    public function first_user_maintains_super_admin_after_login_logout_cycle()
    {
        $this->assertEquals(0, User::count());

        // Register and login first user
        $this->post('/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        $user = User::first();
        $this->assertTrue($user->isSuperAdmin());

        // Logout
        $this->post('/logout');
        $this->assertGuest();

        // Login again
        $loginResponse = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect('/dashboard');

        // User should still be Super Admin
        $user->refresh();
        $this->assertTrue($user->isSuperAdmin());

        // Should still have access to Super Admin features
        $this->actingAs($user);
        $superAdminResponse = $this->get('/super-admin');
        $superAdminResponse->assertStatus(200);
    }

    /** @test */
    public function system_bootstrapping_works_with_empty_database()
    {
        // Test that the system can bootstrap itself from completely empty state
        $this->assertEquals(0, User::count());
        $this->assertEquals(0, \App\Models\Organization::count());

        // Register first user (should create default org if needed)
        $response = $this->post('/register', [
            'name' => 'Bootstrap Admin',
            'email' => 'bootstrap@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_email' => false,
        ]);

        // Should have created user and default organization
        $this->assertEquals(1, User::count());
        $this->assertGreaterThan(0, \App\Models\Organization::count());

        $user = User::first();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertNotNull($user->organization);
        $this->assertTrue($user->organization->is_default);
        $this->assertEquals('None', $user->organization->name);
    }
}
