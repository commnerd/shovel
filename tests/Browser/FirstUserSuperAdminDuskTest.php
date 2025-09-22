<?php

namespace Tests\Browser;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FirstUserSuperAdminDuskTest extends DuskTestCase
{
    use DatabaseMigrations, MocksAIServices;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock AI services to prevent real API calls
        $this->mockAIServices();
    }

    /**
     * Test that the first user to register becomes a Super Admin via browser.
     */
    public function test_first_user_registration_becomes_super_admin()
    {
        // Ensure no users exist initially
        $this->assertEquals(0, User::count());

        $this->browse(function (Browser $browser) {
            // Visit registration page
            $browser->visit('/register')
                    ->assertSee('Register')
                    ->assertSee('Name')
                    ->assertSee('Email')
                    ->assertSee('Password');

            // Fill out registration form
            $browser->type('name', 'First User')
                    ->type('email', 'first@example.com')
                    ->type('password', 'password')
                    ->type('password_confirmation', 'password')
                    ->uncheck('organization_email') // Don't use organization email
                    ->press('Register');

            // Should be redirected to dashboard
            $browser->assertPathIs('/dashboard');
        });

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
     * Test that subsequent users do not become Super Admin via browser.
     */
    public function test_subsequent_user_registration_does_not_become_super_admin()
    {
        // Create first user (Super Admin)
        User::factory()->create([
            'is_super_admin' => true,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        $this->browse(function (Browser $browser) {
            // Visit registration page
            $browser->visit('/register')
                    ->assertSee('Register');

            // Fill out registration form
            $browser->type('name', 'Second User')
                    ->type('email', 'second@example.com')
                    ->type('password', 'password')
                    ->type('password_confirmation', 'password')
                    ->uncheck('organization_email')
                    ->press('Register');

            // Should be redirected to dashboard
            $browser->assertPathIs('/dashboard');
        });

        // Verify second user was created
        $this->assertEquals(2, User::count());

        $secondUser = User::where('email', 'second@example.com')->first();
        $this->assertEquals('Second User', $secondUser->name);

        // Verify second user is NOT Super Admin
        $this->assertFalse($secondUser->is_super_admin);
        $this->assertFalse($secondUser->isSuperAdmin());
    }

    /**
     * Test that first user with organization email becomes Super Admin via browser.
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

        $this->browse(function (Browser $browser) {
            // Visit registration page
            $browser->visit('/register')
                    ->assertSee('Register');

            // Fill out registration form with organization email
            $browser->type('name', 'First Org User')
                    ->type('email', 'admin@testorg.com')
                    ->type('password', 'password')
                    ->type('password_confirmation', 'password')
                    ->check('organization_email') // Use organization email
                    ->press('Register');

            // Should be redirected to login (pending approval)
            $browser->assertPathIs('/login')
                    ->assertSee('registration-pending');
        });

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
     * Test that first user can access super admin features in the UI.
     */
    public function test_first_user_can_access_super_admin_features()
    {
        // Create first user (Super Admin)
        $user = User::factory()->create([
            'is_super_admin' => true,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            // Login as the super admin user
            $browser->loginAs($user)
                    ->visit('/dashboard')
                    ->assertSee('Dashboard');

            // Check if super admin features are visible
            // Note: This would depend on your actual UI implementation
            // You might need to check for specific super admin menu items or buttons
            $browser->assertSee('Dashboard'); // Basic check that user is logged in
        });

        // Verify user has super admin privileges
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->canManageOrganizations());
        $this->assertTrue($user->canLoginAsOtherUsers());
        $this->assertTrue($user->canAssignSuperAdmin());
    }
}
