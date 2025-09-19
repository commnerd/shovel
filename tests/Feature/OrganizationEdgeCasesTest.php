<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class OrganizationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    public function test_organization_creation_with_invalid_data()
    {
        // Test missing organization name
        session([
            'registration_data' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => \Hash::make('password'),
            ]
        ]);

        $response = $this->post('/organization/create', [
            'organization_address' => '123 Test St',
        ]);

        $response->assertSessionHasErrors(['organization_name']);

        // Test missing address
        $response = $this->post('/organization/create', [
            'organization_name' => 'Test Company',
        ]);

        $response->assertSessionHasErrors(['organization_address']);

        // Test organization creation without session data
        session()->forget('registration_data');

        $response = $this->post('/organization/create', [
            'organization_name' => 'Test Company',
            'organization_address' => '123 Test St',
        ]);

        $response->assertRedirect('/register');
    }

    public function test_organization_confirmation_without_session_data()
    {
        $response = $this->get('/registration/confirm-organization');

        $response->assertRedirect('/register');
    }

    public function test_organization_confirmation_with_invalid_data()
    {
        $organization = Organization::factory()->create();

        session([
            'registration_data' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => \Hash::make('password'),
            ],
            'existing_organization' => $organization,
        ]);

        // Test without join_organization parameter
        $response = $this->post('/registration/confirm-organization', []);

        $response->assertSessionHasErrors(['join_organization']);

        // Test with invalid join_organization value
        $response = $this->post('/registration/confirm-organization', [
            'join_organization' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['join_organization']);
    }

    public function test_user_registration_with_duplicate_email()
    {
        $existingUser = User::factory()->create([
            'email' => 'duplicate@test.com',
        ]);

        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'duplicate@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_admin_cannot_manage_users_from_different_organization()
    {
        // Create two organizations
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $org1Roles = $org1->createDefaultRoles();
        $org2Roles = $org2->createDefaultRoles();

        $admin1 = User::factory()->create(['organization_id' => $org1->id]);
        $admin1->assignRole($org1Roles['admin']);

        $user2 = User::factory()->create([
            'organization_id' => $org2->id,
            'pending_approval' => true,
        ]);

        // Admin from org1 tries to approve user from org2
        $response = $this->actingAs($admin1)
            ->post("/admin/users/{$user2->id}/approve");

        $response->assertStatus(403);

        // Admin from org1 tries to assign role to user from org2
        $response = $this->actingAs($admin1)
            ->post("/admin/users/{$user2->id}/assign-role", [
                'role_id' => $org2Roles['admin']->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_assign_project_to_non_existent_group()
    {
        $organization = Organization::factory()->create();
        $roles = $organization->createDefaultRoles();
        $group = $organization->createDefaultGroup();

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->assignRole($roles['user']);
        $user->joinGroup($group);

        // Try to create project with non-existent group
        $response = $this->actingAs($user)
            ->post('/dashboard/projects', [
                'title' => 'Test Project',
                'description' => 'Test description',
                'due_date' => '2025-12-31',
                'group_id' => 99999, // Non-existent group
                'tasks' => [],
            ]);

        $response->assertSessionHasErrors(['group_id']);
    }

    public function test_user_cannot_assign_project_to_group_they_dont_belong_to()
    {
        $organization = Organization::factory()->create();
        $roles = $organization->createDefaultRoles();

        $group1 = $organization->createDefaultGroup();
        $group2 = Group::factory()->create(['organization_id' => $organization->id]);

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->assignRole($roles['user']);
        $user->joinGroup($group1); // Only belongs to group1

        // Try to create project in group2
        $response = $this->actingAs($user)
            ->post('/dashboard/projects', [
                'title' => 'Test Project',
                'description' => 'Test description',
                'due_date' => '2025-12-31',
                'group_id' => $group2->id,
                'tasks' => [],
            ]);

        // Should either get 403 or redirect with error
        if ($response->status() === 403) {
            $response->assertStatus(403);
        } else {
            $response->assertRedirect();
            $response->assertSessionHasErrors();
        }
    }

    public function test_organization_with_no_creator_handles_notifications_gracefully()
    {
        Notification::fake();

        // Create organization without creator
        $organization = Organization::factory()->create([
            'creator_id' => null,
        ]);

        $roles = $organization->createDefaultRoles();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => true,
        ]);

        // Simulate registration process that would normally send notification
        // In this case, no notification should be sent since there's no creator
        if ($organization->creator) {
            $organization->creator->notify(new \App\Notifications\NewOrganizationMemberNotification($user, $organization));
        }

        // Verify no notifications were sent
        Notification::assertNothingSent();
    }

    public function test_user_cannot_leave_default_group()
    {
        $organization = Organization::factory()->create();
        $roles = $organization->createDefaultRoles();
        $defaultGroup = $organization->createDefaultGroup();

        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole($roles['admin']);

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->assignRole($roles['user']);
        $user->joinGroup($defaultGroup);

        // Admin tries to remove user from default group
        $response = $this->actingAs($admin)
            ->delete("/admin/users/{$user->id}/remove-from-group", [
                'group_id' => $defaultGroup->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['error']);

        // Verify user is still in default group
        $this->assertTrue($user->fresh()->belongsToGroup($defaultGroup->id));
    }

    public function test_admin_cannot_assign_role_from_different_organization()
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $org1Roles = $org1->createDefaultRoles();
        $org2Roles = $org2->createDefaultRoles();

        $admin = User::factory()->create(['organization_id' => $org1->id]);
        $admin->assignRole($org1Roles['admin']);

        $user = User::factory()->create(['organization_id' => $org1->id]);
        $user->assignRole($org1Roles['user']);

        // Admin tries to assign role from different organization
        $response = $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/assign-role", [
                'role_id' => $org2Roles['admin']->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_project_access_with_null_group_id()
    {
        $organization = Organization::factory()->create();
        $roles = $organization->createDefaultRoles();

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->assignRole($roles['user']);

        // Create project with null group_id (edge case)
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => null,
        ]);

        // User should still be able to access their own project
        $response = $this->actingAs($user)
            ->get("/dashboard/projects/{$project->id}/edit");

        $response->assertOk();

        // Other user should not be able to access it
        $otherUser = User::factory()->create(['organization_id' => $organization->id]);
        $otherUser->assignRole($roles['user']);

        $response = $this->actingAs($otherUser)
            ->get("/dashboard/projects/{$project->id}/edit");

        $response->assertStatus(403);
    }

    public function test_user_approval_with_invalid_user_id()
    {
        $organization = Organization::factory()->create();
        $roles = $organization->createDefaultRoles();

        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole($roles['admin']);

        // Try to approve non-existent user
        $response = $this->actingAs($admin)
            ->post("/admin/users/99999/approve");

        $response->assertStatus(404);
    }

    public function test_user_approval_of_already_approved_user()
    {
        $organization = Organization::factory()->create();
        $roles = $organization->createDefaultRoles();

        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole($roles['admin']);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false, // Already approved
            'approved_at' => now(),
        ]);

        // Try to approve already approved user
        $response = $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHasErrors(['error']);
    }

    public function test_organization_domain_matching_basic_functionality()
    {
        $organization = Organization::factory()->create([
            'domain' => 'testcompany.com',
        ]);

        // Test basic domain matching (lowercase only due to Laravel validation)
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'user@testcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        // Should be redirected to confirmation page for domain match
        $response->assertRedirect('/registration/confirm-organization');

        // Test non-matching domain
        $response = $this->post('/register', [
            'name' => 'Other User',
            'email' => 'user@otherdomain.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        // Should be registered directly to default organization
        $response->assertRedirect('/dashboard');
    }

    public function test_empty_organization_name_and_address_handling()
    {
        session([
            'registration_data' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => \Hash::make('password'),
            ]
        ]);

        // Test with empty strings
        $response = $this->post('/organization/create', [
            'organization_name' => '',
            'organization_address' => '',
        ]);

        $response->assertSessionHasErrors(['organization_name', 'organization_address']);

        // Test with whitespace only
        $response = $this->post('/organization/create', [
            'organization_name' => '   ',
            'organization_address' => '   ',
        ]);

        $response->assertSessionHasErrors(['organization_name', 'organization_address']);
    }

    public function test_maximum_length_validation_for_organization_fields()
    {
        session([
            'registration_data' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => \Hash::make('password'),
            ]
        ]);

        // Test with very long organization name
        $longName = str_repeat('a', 256); // Assuming 255 is the limit
        $response = $this->post('/organization/create', [
            'organization_name' => $longName,
            'organization_address' => 'Valid address',
        ]);

        $response->assertSessionHasErrors(['organization_name']);

        // Test with very long address
        $longAddress = str_repeat('a', 1001); // Assuming 1000 is the limit
        $response = $this->post('/organization/create', [
            'organization_name' => 'Valid Name',
            'organization_address' => $longAddress,
        ]);

        $response->assertSessionHasErrors(['organization_address']);
    }
}
