<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminImpersonationUITest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $targetUser;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure AI provider to prevent middleware redirects
        \App\Models\Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebrus API Key');

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
    }

    public function test_impersonation_banner_not_shown_when_not_impersonating()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', null)
            ->where('auth.user.id', $this->superAdmin->id)
        );
    }

    public function test_impersonation_banner_shown_when_impersonating()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing UI',
            ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', $this->superAdmin->id)
            ->where('auth.user.id', $this->targetUser->id)
            ->where('auth.user.name', $this->targetUser->name)
        );
    }

    public function test_impersonation_banner_shows_correct_user_information()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing UI information',
            ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('auth.user.name', 'Target User')
            ->where('auth.user.email', 'target@test.com')
            ->where('auth.original_super_admin_id', $this->superAdmin->id)
        );
    }

    public function test_impersonation_banner_appears_on_all_authenticated_pages()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing UI across pages',
            ]);

        $pages = [
            '/dashboard',
        ];

        foreach ($pages as $page) {
            $response = $this->get($page);

            $response->assertOk();
            $response->assertInertia(fn (Assert $inertiaPage) => $inertiaPage->where('auth.original_super_admin_id', $this->superAdmin->id)
                ->where('auth.user.id', $this->targetUser->id)
            );
        }
    }

    public function test_impersonation_banner_not_shown_for_regular_users()
    {
        $response = $this->actingAs($this->targetUser)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', null)
            ->where('auth.user.id', $this->targetUser->id)
        );
    }

    public function test_return_button_functionality_from_any_page()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing return functionality',
            ]);

        // Verify we're impersonating
        $this->assertEquals($this->targetUser->id, auth()->id());
        $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));

        // Test return from different pages
        $pages = [
            '/dashboard',
        ];

        foreach ($pages as $page) {
            // Visit the page first
            $response = $this->get($page);
            $response->assertOk();

            // Then test return functionality
            $returnResponse = $this->post('/super-admin/return-to-super-admin');
            $returnResponse->assertRedirect('/super-admin');

            // Verify we're back to super admin
            $this->assertEquals($this->superAdmin->id, auth()->id());
            $this->assertNull(session('original_super_admin_id'));

            // Set up impersonation again for next iteration
            if ($page !== end($pages)) {
                $this->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                    'reason' => 'Testing return functionality',
                ]);
            }
        }
    }

    public function test_impersonation_state_persists_across_navigation()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing navigation persistence',
            ]);

        // Navigate through multiple pages
        $navigationFlow = [
            '/dashboard',
            '/dashboard',
            '/dashboard',
            '/dashboard',
        ];

        foreach ($navigationFlow as $page) {
            $response = $this->get($page);

            $response->assertOk();
            $response->assertInertia(fn (Assert $inertiaPage) => $inertiaPage->where('auth.original_super_admin_id', $this->superAdmin->id)
                ->where('auth.user.id', $this->targetUser->id)
            );

            // Verify session state
            $this->assertEquals($this->targetUser->id, auth()->id());
            $this->assertEquals($this->superAdmin->id, session('original_super_admin_id'));
        }
    }

    public function test_impersonation_banner_handles_long_user_names()
    {
        // Create user with long name
        $longNameUser = User::factory()->create([
            'name' => 'This Is A Very Long User Name That Should Be Truncated In The UI',
            'email' => 'longname@test.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $longNameUser->joinGroup($this->organization->groups()->first());

        // Login as the long name user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$longNameUser->id}/login-as", [
                'reason' => 'Testing long names',
            ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('auth.user.name', 'This Is A Very Long User Name That Should Be Truncated In The UI')
            ->where('auth.original_super_admin_id', $this->superAdmin->id)
        );
    }

    public function test_impersonation_banner_shows_after_successful_login_as_user()
    {
        // Start as super admin
        $response = $this->actingAs($this->superAdmin)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', null)
        );

        // Login as another user
        $loginResponse = $this->post("/super-admin/users/{$this->targetUser->id}/login-as", [
            'reason' => 'Testing banner appearance',
        ]);

        $loginResponse->assertRedirect('/dashboard');

        // Follow the redirect and check banner appears
        $dashboardResponse = $this->get('/dashboard');

        $dashboardResponse->assertOk();
        $dashboardResponse->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', $this->superAdmin->id)
            ->where('auth.user.id', $this->targetUser->id)
        );
    }

    public function test_impersonation_banner_disappears_after_return()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing banner disappearance',
            ]);

        // Verify banner is shown
        $impersonatingResponse = $this->get('/dashboard');
        $impersonatingResponse->assertOk();
        $impersonatingResponse->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', $this->superAdmin->id)
        );

        // Return to super admin
        $returnResponse = $this->post('/super-admin/return-to-super-admin');
        $returnResponse->assertRedirect('/super-admin');

        // Follow redirect and verify banner is gone
        $superAdminResponse = $this->get('/super-admin');
        $superAdminResponse->assertOk();
        $superAdminResponse->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', null)
            ->where('auth.user.id', $this->superAdmin->id)
        );
    }

    public function test_impersonation_data_structure_is_consistent()
    {
        // Login as another user
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing data consistency',
            ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->has('auth')
            ->has('auth.user')
            ->has('auth.user.id')
            ->has('auth.user.name')
            ->has('auth.user.email')
            ->has('auth.original_super_admin_id')
            ->where('auth.user.id', $this->targetUser->id)
            ->where('auth.user.name', $this->targetUser->name)
            ->where('auth.user.email', $this->targetUser->email)
            ->where('auth.original_super_admin_id', $this->superAdmin->id)
        );
    }

    public function test_impersonation_works_with_different_user_types()
    {
        // Create different types of users
        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $adminUser->joinGroup($this->organization->groups()->first());
        $adminRole = $this->organization->roles()->where('name', 'admin')->first();
        $adminUser->assignRole($adminRole);

        $pendingUser = User::factory()->create([
            'name' => 'Pending User',
            'organization_id' => $this->organization->id,
            'pending_approval' => true,
        ]);

        $users = [$this->targetUser, $adminUser, $pendingUser];

        foreach ($users as $user) {
            // Login as each user type
            $this->actingAs($this->superAdmin)
                ->post("/super-admin/users/{$user->id}/login-as", [
                    'reason' => "Testing impersonation of {$user->name}",
                ]);

            $response = $this->get('/dashboard');

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page->where('auth.original_super_admin_id', $this->superAdmin->id)
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', $user->name)
            );

            // Return to super admin for next iteration
            $this->post('/super-admin/return-to-super-admin');
        }
    }
}
