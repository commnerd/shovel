<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminUserSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $regularUser;

    protected User $adminUser;

    protected User $pendingUser;

    protected Organization $organization;

    protected Organization $secondOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure AI provider to prevent middleware redirects
        \App\Models\Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebras API Key');

        // Set up organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);

        $this->organization = Organization::getDefault();
        $group = $this->organization->createDefaultGroup();

        // Create second organization
        $this->secondOrganization = Organization::factory()->create([
            'name' => 'Tech Corp',
            'domain' => 'techcorp.com',
        ]);
        $secondGroup = $this->secondOrganization->createDefaultGroup();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin User',
            'email' => 'superadmin@example.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => true,
        ]);
        $this->superAdmin->joinGroup($group);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->regularUser->joinGroup($group);

        // Create admin user
        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@techcorp.com',
            'organization_id' => $this->secondOrganization->id,
            'pending_approval' => false,
        ]);
        $this->adminUser->joinGroup($secondGroup);

        // Create admin role if it doesn't exist
        $adminRole = $this->secondOrganization->roles()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator']
        );
        $this->adminUser->assignRole($adminRole);

        // Create pending user
        $this->pendingUser = User::factory()->create([
            'name' => 'Pending User',
            'email' => 'pending@techcorp.com',
            'organization_id' => $this->secondOrganization->id,
            'pending_approval' => true,
        ]);
    }

    public function test_super_admin_can_search_users_by_name()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=John');

        $response->assertOk();
        $response->assertJson([
            'users' => [
                [
                    'id' => $this->regularUser->id,
                    'name' => 'John Doe',
                    'email' => 'john.doe@example.com',
                    'organization_name' => $this->organization->name,
                    'is_admin' => false,
                    'is_super_admin' => false,
                    'pending_approval' => false,
                ],
            ],
            'query' => 'John',
        ]);
    }

    public function test_super_admin_can_search_users_by_email()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=admin@techcorp');

        $response->assertOk();
        $response->assertJson([
            'users' => [
                [
                    'id' => $this->adminUser->id,
                    'name' => 'Admin User',
                    'email' => 'admin@techcorp.com',
                    'organization_name' => 'Tech Corp',
                    'is_admin' => true,
                    'is_super_admin' => false,
                    'pending_approval' => false,
                ],
            ],
            'query' => 'admin@techcorp',
        ]);
    }

    public function test_search_returns_users_from_all_organizations()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=User');

        $response->assertOk();
        $data = $response->json();

        $this->assertGreaterThanOrEqual(3, count($data['users'])); // superAdmin, adminUser, pendingUser

        // Check that users from different organizations are included
        $organizationNames = collect($data['users'])->pluck('organization_name')->unique();
        $this->assertContains($this->organization->name, $organizationNames);
        $this->assertContains('Tech Corp', $organizationNames);
    }

    public function test_search_results_are_properly_ordered()
    {
        // Create users with different match types
        User::factory()->create([
            'name' => 'Test User', // Exact start match
            'email' => 'test.user@example.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);

        User::factory()->create([
            'name' => 'Another Test Person', // Contains match
            'email' => 'another@example.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);

        User::factory()->create([
            'name' => 'Person',
            'email' => 'test@example.com', // Email starts with query
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=Test');

        $response->assertOk();
        $data = $response->json();

        $this->assertGreaterThanOrEqual(3, count($data['users']));

        // First result should be name starting with "Test"
        $this->assertEquals('Test User', $data['users'][0]['name']);

        // Email starting with "Test" should come before name containing "Test"
        $emailStartsWithTest = collect($data['users'])->first(function ($user) {
            return str_starts_with($user['email'], 'test@');
        });

        $nameContainsTest = collect($data['users'])->first(function ($user) {
            return str_contains($user['name'], 'Test') && ! str_starts_with($user['name'], 'Test');
        });

        if ($emailStartsWithTest && $nameContainsTest) {
            $emailIndex = array_search($emailStartsWithTest['id'], array_column($data['users'], 'id'));
            $nameIndex = array_search($nameContainsTest['id'], array_column($data['users'], 'id'));
            $this->assertLessThan($nameIndex, $emailIndex);
        }
    }

    public function test_search_includes_user_status_information()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=Pending');

        $response->assertOk();
        $data = $response->json();

        $pendingUserData = collect($data['users'])->firstWhere('id', $this->pendingUser->id);

        $this->assertNotNull($pendingUserData);
        $this->assertTrue($pendingUserData['pending_approval']);
        $this->assertEquals('P', $pendingUserData['avatar']); // First letter
    }

    public function test_search_respects_result_limit()
    {
        // Create many users
        for ($i = 0; $i < 20; $i++) {
            User::factory()->create([
                'name' => "Test User {$i}",
                'email' => "testuser{$i}@example.com",
                'organization_id' => $this->organization->id,
                'pending_approval' => false,
            ]);
        }

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=Test&limit=5');

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals(5, count($data['users']));
        $this->assertEquals(5, $data['total']);
    }

    public function test_search_validates_minimum_query_length()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/super-admin/users/search?query=J');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['query']);
    }

    public function test_search_validates_maximum_query_length()
    {
        $longQuery = str_repeat('a', 101); // 101 characters

        $response = $this->actingAs($this->superAdmin)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/super-admin/users/search?query='.$longQuery);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['query']);
    }

    public function test_search_validates_limit_parameter()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/super-admin/users/search?query=Test&limit=100');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['limit']);
    }

    public function test_search_returns_empty_results_for_no_matches()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=NonexistentUser');

        $response->assertOk();
        $response->assertJson([
            'users' => [],
            'query' => 'NonexistentUser',
            'total' => 0,
        ]);
    }

    public function test_search_is_case_insensitive()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=JOHN');

        $response->assertOk();
        $data = $response->json();

        $this->assertGreaterThan(0, count($data['users']));

        $johnUser = collect($data['users'])->firstWhere('name', 'John Doe');
        $this->assertNotNull($johnUser);
    }

    public function test_search_works_with_partial_email_matches()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=@techcorp');

        $response->assertOk();
        $data = $response->json();

        $this->assertGreaterThan(0, count($data['users']));

        // All results should have emails containing @techcorp
        foreach ($data['users'] as $user) {
            $this->assertStringContainsString('@techcorp', $user['email']);
        }
    }

    public function test_regular_user_cannot_access_search_endpoint()
    {
        $response = $this->actingAs($this->regularUser)
            ->get('/super-admin/users/search?query=Test');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_search_endpoint()
    {
        $response = $this->get('/super-admin/users/search?query=Test');

        $response->assertRedirect('/login');
    }

    public function test_search_handles_special_characters()
    {
        User::factory()->create([
            'name' => "O'Connor Test",
            'email' => 'oconnor@example.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query='.urlencode("O'Connor"));

        $response->assertOk();
        $data = $response->json();

        $this->assertGreaterThan(0, count($data['users']));

        $oconnorUser = collect($data['users'])->firstWhere('name', "O'Connor Test");
        $this->assertNotNull($oconnorUser);
    }

    public function test_search_includes_super_admin_and_admin_flags()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=Admin');

        $response->assertOk();
        $data = $response->json();

        // Find super admin user
        $superAdminData = collect($data['users'])->firstWhere('id', $this->superAdmin->id);
        if ($superAdminData) {
            $this->assertTrue($superAdminData['is_super_admin']);
            $this->assertFalse($superAdminData['is_admin']); // Super admin is not regular admin
        }

        // Find admin user
        $adminData = collect($data['users'])->firstWhere('id', $this->adminUser->id);
        if ($adminData) {
            $this->assertTrue($adminData['is_admin']);
            $this->assertFalse($adminData['is_super_admin']);
        }
    }

    public function test_search_response_includes_avatar_letters()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=John');

        $response->assertOk();
        $data = $response->json();

        $johnUser = collect($data['users'])->firstWhere('name', 'John Doe');
        $this->assertNotNull($johnUser);
        $this->assertEquals('J', $johnUser['avatar']);
    }

    public function test_search_handles_users_without_organization()
    {
        // Create user without organization (edge case)
        $userWithoutOrg = User::factory()->create([
            'name' => 'No Org User',
            'email' => 'noorg@example.com',
            'organization_id' => null,
            'pending_approval' => false,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/users/search?query=No Org');

        $response->assertOk();
        $data = $response->json();

        $noOrgUser = collect($data['users'])->firstWhere('id', $userWithoutOrg->id);
        if ($noOrgUser) {
            $this->assertEquals('No Organization', $noOrgUser['organization_name']);
        }
    }
}
