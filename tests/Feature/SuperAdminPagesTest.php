<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $regularUser;

    protected Organization $organization;

    protected Organization $secondOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure AI provider to prevent middleware redirects
        \App\Models\Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebrus API Key');

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $this->organization = Organization::getDefault();
        $group = $this->organization->createDefaultGroup();

        // Create second organization
        $this->secondOrganization = Organization::factory()->create([
            'name' => 'Second Organization',
            'domain' => 'second.com',
        ]);
        $secondGroup = $this->secondOrganization->createDefaultGroup();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => true,
        ]);
        $this->superAdmin->joinGroup($group);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => false,
        ]);
        $this->regularUser->joinGroup($group);

        // Create user in second organization
        $userInSecondOrg = User::factory()->create([
            'organization_id' => $this->secondOrganization->id,
            'pending_approval' => false,
        ]);
        $userInSecondOrg->joinGroup($secondGroup);
    }

    public function test_super_admin_can_access_users_management_page()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Users')
            ->has('users')
            ->has('users.data', 3) // superAdmin, regularUser, userInSecondOrg
            ->has('filters')
        );
    }

    public function test_super_admin_can_access_organizations_management_page()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/organizations');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Organizations')
            ->has('organizations')
            ->has('organizations.data', 2) // Default + Second organization
            ->has('filters')
        );
    }

    public function test_users_page_shows_correct_user_information()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Users')
            ->where('users.data', function ($users) {
                $regularUser = collect($users)->firstWhere('email', $this->regularUser->email);
                $this->assertNotNull($regularUser, 'Regular user should be in the users list');
                $this->assertEquals($this->regularUser->name, $regularUser['name']);
                $this->assertEquals($this->regularUser->email, $regularUser['email']);
                $this->assertFalse($regularUser['is_super_admin']);
                $this->assertEquals($this->organization->name, $regularUser['organization_name']);

                return true;
            })
        );
    }

    public function test_organizations_page_shows_correct_organization_information()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/organizations');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Organizations')
            ->where('organizations.data', function ($organizations) {
                $secondOrg = collect($organizations)->firstWhere('name', $this->secondOrganization->name);
                $this->assertNotNull($secondOrg, 'Second organization should be in the organizations list');
                $this->assertEquals($this->secondOrganization->name, $secondOrg['name']);
                $this->assertEquals('second.com', $secondOrg['domain_suffix']);
                $this->assertEquals(1, $secondOrg['users_count']);
                $this->assertEquals(1, $secondOrg['groups_count']);

                return true;
            })
        );
    }

    public function test_users_page_filtering_by_organization()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users?organization='.$this->organization->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Users')
            ->where('users.data', function ($users) {
                // All users should be from the filtered organization
                foreach ($users as $user) {
                    $this->assertEquals($this->organization->id, $user['organization_id']);
                }
                // Should include at least our test users
                $emails = collect($users)->pluck('email')->toArray();
                $this->assertContains($this->superAdmin->email, $emails);
                $this->assertContains($this->regularUser->email, $emails);

                return true;
            })
            ->where('filters.organization', (string) $this->organization->id)
        );
    }

    public function test_users_page_search_functionality()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users?search='.urlencode($this->regularUser->email));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Users')
            ->where('users.data', function ($users) {
                // Should contain the searched user
                $emails = collect($users)->pluck('email')->toArray();
                $this->assertContains($this->regularUser->email, $emails);
                // All users should match the search query (email or name contains the search term)
                foreach ($users as $user) {
                    $searchTerm = $this->regularUser->email;
                    $this->assertTrue(
                        str_contains($user['email'], $searchTerm) || str_contains($user['name'], $searchTerm),
                        "User {$user['email']} should match search term {$searchTerm}"
                    );
                }

                return true;
            })
            ->where('filters.search', $this->regularUser->email)
        );
    }

    public function test_organizations_page_search_functionality()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/organizations?search=Second');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Organizations')
            ->where('organizations.data', function ($organizations) {
                // Should contain the searched organization
                $names = collect($organizations)->pluck('name')->toArray();
                $this->assertContains('Second Organization', $names);
                // All organizations should match the search query
                foreach ($organizations as $org) {
                    $this->assertTrue(
                        str_contains($org['name'], 'Second') ||
                        str_contains($org['domain_suffix'] ?? '', 'Second') ||
                        str_contains($org['address'] ?? '', 'Second'),
                        "Organization {$org['name']} should match search term 'Second'"
                    );
                }

                return true;
            })
            ->where('filters.search', 'Second')
        );
    }

    public function test_regular_user_cannot_access_super_admin_pages()
    {
        $routes = [
            '/super-admin/users',
            '/super-admin/organizations',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->regularUser)->get($route);
            $response->assertStatus(403);
        }
    }

    public function test_guest_cannot_access_super_admin_pages()
    {
        $routes = [
            '/super-admin/users',
            '/super-admin/organizations',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    public function test_users_page_pagination()
    {
        // Create many users to test pagination
        User::factory()->count(60)->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Users')
            ->has('users.data') // Has users data
            ->where('users.current_page', 1)
            ->has('users.last_page') // Has pagination
        );

        // Test pagination exists if there are multiple pages
        $firstPageResponse = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users');

        $firstPageResponse->assertOk();
        $firstPageResponse->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Users')
            ->has('users.data')
            ->where('users.current_page', 1)
            ->has('users.last_page') // Has pagination info
        );
    }

    public function test_organizations_page_shows_user_and_group_counts()
    {
        // Add more users to organizations
        User::factory()->count(3)->create([
            'organization_id' => $this->secondOrganization->id,
            'pending_approval' => false,
        ]);

        // Add more groups
        Group::factory()->count(2)->create([
            'organization_id' => $this->secondOrganization->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/organizations');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Organizations')
            ->where('organizations.data', function ($organizations) {
                // Find the first organization and verify it has reasonable counts
                $firstOrg = $organizations[0] ?? null;
                $this->assertNotNull($firstOrg);
                $this->assertGreaterThan(0, $firstOrg['users_count']);
                $this->assertGreaterThan(0, $firstOrg['groups_count']);

                return true;
            })
        );
    }

    public function test_users_page_shows_pending_approval_status()
    {
        // Create pending user
        $pendingUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Users')
            ->where('users.data', function ($users) use ($pendingUser) {
                $pendingUserData = collect($users)->firstWhere('id', $pendingUser->id);
                $this->assertNotNull($pendingUserData, 'Pending user should be in the users list');
                $this->assertTrue($pendingUserData['pending_approval']);

                return true;
            })
        );
    }

    public function test_users_page_distinguishes_super_admins_and_admins()
    {
        // Create admin user
        $admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $admin->joinGroup($this->organization->groups()->first());
        $adminRole = $this->organization->roles()->where('name', 'admin')->first();
        $admin->assignRole($adminRole);

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Users')
            ->has('users.data', 4) // superAdmin, regularUser, userInSecondOrg, admin
        );

        // Find super admin and admin in response
        $usersData = $response->viewData('page')['props']['users']['data'];
        $superAdminData = collect($usersData)->firstWhere('id', $this->superAdmin->id);
        $adminData = collect($usersData)->firstWhere('id', $admin->id);

        $this->assertTrue($superAdminData['is_super_admin']);
        $this->assertTrue($adminData['is_admin']);
        $this->assertFalse($adminData['is_super_admin']);
    }

    public function test_organizations_page_shows_creator_information()
    {
        // Set creator for second organization
        $this->secondOrganization->update(['creator_id' => $this->superAdmin->id]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/organizations');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Organizations')
            ->where('organizations.data', function ($organizations) {
                $secondOrg = collect($organizations)->firstWhere('name', $this->secondOrganization->name);
                $this->assertNotNull($secondOrg, 'Second organization should be in the list');
                $this->assertEquals($this->superAdmin->name, $secondOrg['creator_name']);

                return true;
            })
        );
    }

    public function test_super_admin_pages_require_super_admin_role()
    {
        // Create regular admin
        $admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => false,
        ]);
        $admin->joinGroup($this->organization->groups()->first());
        $adminRole = $this->organization->roles()->where('name', 'admin')->first();
        $admin->assignRole($adminRole);

        $routes = [
            '/super-admin',
            '/super-admin/users',
            '/super-admin/organizations',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get($route);
            $response->assertStatus(403);
        }
    }

    public function test_super_admin_dashboard_shows_correct_statistics()
    {
        // Create additional data for stats
        User::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => true, // Pending users
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Index')
            ->where('stats.total_users', 8) // 3 original + 5 new
            ->where('stats.total_organizations', 2)
            ->where('stats.pending_users', 5)
            ->where('stats.super_admins', 1)
        );
    }
}
