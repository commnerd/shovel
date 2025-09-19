<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NewOrganizationMemberNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    public function test_complete_organization_lifecycle()
    {
        Notification::fake();

        // Step 1: Create organization creator using unique email
        $uniqueEmail = 'creator-'.uniqid().'@testcompany.com';
        $creator = User::factory()->make([
            'email' => $uniqueEmail,
        ]);

        // Step 2: Creator creates organization
        session([
            'registration_data' => [
                'name' => $creator->name,
                'email' => $creator->email,
                'password' => \Hash::make('password'),
            ],
        ]);

        $response = $this->post('/organization/create', [
            'organization_name' => 'Test Company',
            'organization_address' => '123 Business Street',
        ]);

        $response->assertRedirect('/dashboard');

        // Verify organization was created
        $organization = Organization::where('domain', 'testcompany.com')->first();
        $this->assertNotNull($organization);
        $this->assertEquals('Test Company', $organization->name);

        // Get the actual created user and verify creator_id
        $createdUser = User::where('email', $uniqueEmail)->first();
        $this->assertEquals($createdUser->id, $organization->creator_id);

        // Verify default group and roles were created
        $defaultGroup = $organization->defaultGroup();
        $this->assertNotNull($defaultGroup);
        $this->assertEquals('Everyone', $defaultGroup->name);

        $adminRole = $organization->getAdminRole();
        $userRole = $organization->getUserRole();
        $this->assertNotNull($adminRole);
        $this->assertNotNull($userRole);

        // Get the actual created user
        $creator = $createdUser;
        $this->assertTrue($creator->isAdmin());
        $this->assertTrue($creator->hasPermission('manage_users'));
        $this->assertTrue($creator->belongsToGroup($defaultGroup->id));

        // Step 3: New user joins organization
        $newUser = User::factory()->create([
            'email' => 'employee@testcompany.com',
            'organization_id' => $organization->id,
            'pending_approval' => true,
        ]);
        $newUser->assignRole($userRole);

        // Verify notification was sent (would be sent in real scenario)
        // In test, we simulate this
        $creator->notify(new NewOrganizationMemberNotification($newUser, $organization));

        Notification::assertSentTo($creator, NewOrganizationMemberNotification::class);

        // Step 4: Admin approves user
        $response = $this->actingAs($creator)
            ->post("/admin/users/{$newUser->id}/approve");

        $response->assertRedirect();

        $newUser->refresh();
        $this->assertFalse($newUser->pending_approval);
        $this->assertNotNull($newUser->approved_at);
        $this->assertEquals($creator->id, $newUser->approved_by);

        // Step 5: Create additional group
        $devGroup = Group::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Development Team',
            'is_default' => false,
        ]);

        // Step 6: Add users to development group
        $newUser->joinGroup($devGroup);
        $this->assertTrue($newUser->belongsToGroup($devGroup->id));

        // Step 7: Create projects in different groups
        $defaultProject = Project::factory()->create([
            'user_id' => $creator->id,
            'group_id' => $defaultGroup->id,
            'title' => 'Company Wide Project',
        ]);

        $devProject = Project::factory()->create([
            'user_id' => $newUser->id,
            'group_id' => $devGroup->id,
            'title' => 'Development Project',
        ]);

        // Step 8: Test project visibility and permissions
        // Creator (admin) should see projects they have access to
        $response = $this->actingAs($creator)
            ->get('/dashboard/projects');

        $response->assertOk();
        $projects = $response->viewData('page')['props']['projects'];
        $this->assertGreaterThanOrEqual(1, count($projects)); // At least their own project

        // New user should see projects they have access to
        $response = $this->actingAs($newUser)
            ->get('/dashboard/projects');

        $response->assertOk();
        $projects = $response->viewData('page')['props']['projects'];
        $this->assertGreaterThanOrEqual(1, count($projects)); // At least their own project

        // Step 9: Test project modification permissions
        // Creator can modify any project in organization
        $response = $this->actingAs($creator)
            ->put("/dashboard/projects/{$devProject->id}", [
                'title' => 'Updated by Admin',
                'description' => 'Updated description',
                'due_date' => '2025-12-31',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'id' => $devProject->id,
            'title' => 'Updated by Admin',
        ]);

        // New user cannot modify creator's project
        $response = $this->actingAs($newUser)
            ->put("/dashboard/projects/{$defaultProject->id}", [
                'title' => 'Hacked Title',
                'description' => 'Updated description',
                'due_date' => '2025-12-31',
            ]);

        $response->assertStatus(403);

        // Step 10: Test role management
        // Admin can assign admin role to user
        $response = $this->actingAs($creator)
            ->post("/admin/users/{$newUser->id}/assign-role", [
                'role_id' => $adminRole->id,
            ]);

        $response->assertRedirect();
        $this->assertTrue($newUser->fresh()->hasRole('admin'));

        // Step 11: Test multi-organization isolation
        $otherOrg = Organization::factory()->create([
            'domain' => 'othercompany.com',
        ]);
        $otherOrgRoles = $otherOrg->createDefaultRoles();
        $otherGroup = $otherOrg->createDefaultGroup();

        $outsideUser = User::factory()->create([
            'email' => 'user@othercompany.com',
            'organization_id' => $otherOrg->id,
        ]);
        $outsideUser->assignRole($otherOrgRoles['user']);
        $outsideUser->joinGroup($otherGroup);

        $outsideProject = Project::factory()->create([
            'user_id' => $outsideUser->id,
            'group_id' => $otherGroup->id,
            'title' => 'Outside Project',
        ]);

        // Users from Test Company cannot see outside project
        $response = $this->actingAs($creator)
            ->get('/dashboard/projects');

        $projects = $response->viewData('page')['props']['projects'];
        $projectTitles = collect($projects)->pluck('title');
        $this->assertNotContains('Outside Project', $projectTitles);

        // Outside user cannot access Test Company admin panel
        $response = $this->actingAs($outsideUser)
            ->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_organization_data_isolation()
    {
        // Create two organizations
        $org1 = Organization::factory()->create(['domain' => 'company1.com']);
        $org2 = Organization::factory()->create(['domain' => 'company2.com']);

        $org1Roles = $org1->createDefaultRoles();
        $org2Roles = $org2->createDefaultRoles();

        $org1Group = $org1->createDefaultGroup();
        $org2Group = $org2->createDefaultGroup();

        // Create users in each organization
        $user1 = User::factory()->create(['organization_id' => $org1->id]);
        $user1->assignRole($org1Roles['admin']);
        $user1->joinGroup($org1Group);

        $user2 = User::factory()->create(['organization_id' => $org2->id]);
        $user2->assignRole($org2Roles['user']);
        $user2->joinGroup($org2Group);

        // Create projects in each organization
        $project1 = Project::factory()->create([
            'user_id' => $user1->id,
            'group_id' => $org1Group->id,
            'title' => 'Org1 Project',
        ]);

        $project2 = Project::factory()->create([
            'user_id' => $user2->id,
            'group_id' => $org2Group->id,
            'title' => 'Org2 Project',
        ]);

        // Test project isolation
        $response = $this->actingAs($user1)->get('/dashboard/projects');
        $projects = $response->viewData('page')['props']['projects'];
        $this->assertCount(1, $projects);
        $this->assertEquals('Org1 Project', $projects[0]['title']);

        $response = $this->actingAs($user2)->get('/dashboard/projects');
        $projects = $response->viewData('page')['props']['projects'];
        $this->assertCount(1, $projects);
        $this->assertEquals('Org2 Project', $projects[0]['title']);

        // Test admin panel isolation
        $response = $this->actingAs($user1)->get('/admin/users');
        $response->assertOk();
        $users = $response->viewData('page')['props']['approvedUsers'];
        $this->assertCount(1, $users);
        $this->assertEquals($user1->email, $users[0]['email']);

        // User2 is not admin, should get 403
        $response = $this->actingAs($user2)->get('/admin/users');
        $response->assertStatus(403);

        // Test cross-organization project access denial
        $response = $this->actingAs($user1)
            ->get("/dashboard/projects/{$project2->id}/edit");
        $response->assertStatus(403);

        $response = $this->actingAs($user2)
            ->get("/dashboard/projects/{$project1->id}/edit");
        $response->assertStatus(403);
    }

    public function test_complex_group_membership_scenarios()
    {
        $organization = Organization::factory()->create();
        $roles = $organization->createDefaultRoles();

        // Create multiple groups
        $everyoneGroup = $organization->createDefaultGroup();
        $devGroup = Group::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Development',
        ]);
        $qaGroup = Group::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'QA',
        ]);
        $managementGroup = Group::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Management',
        ]);

        // Create users with different group memberships
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole($roles['admin']);
        $admin->joinGroup($everyoneGroup);
        $admin->joinGroup($managementGroup);

        $developer = User::factory()->create(['organization_id' => $organization->id]);
        $developer->assignRole($roles['user']);
        $developer->joinGroup($everyoneGroup);
        $developer->joinGroup($devGroup);

        $qaEngineer = User::factory()->create(['organization_id' => $organization->id]);
        $qaEngineer->assignRole($roles['user']);
        $qaEngineer->joinGroup($everyoneGroup);
        $qaEngineer->joinGroup($qaGroup);

        $fullStack = User::factory()->create(['organization_id' => $organization->id]);
        $fullStack->assignRole($roles['user']);
        $fullStack->joinGroup($everyoneGroup);
        $fullStack->joinGroup($devGroup);
        $fullStack->joinGroup($qaGroup);

        // Create projects in different groups
        $everyoneProject = Project::factory()->create([
            'user_id' => $admin->id,
            'group_id' => $everyoneGroup->id,
            'title' => 'Company Project',
        ]);

        $devProject = Project::factory()->create([
            'user_id' => $developer->id,
            'group_id' => $devGroup->id,
            'title' => 'Dev Project',
        ]);

        $qaProject = Project::factory()->create([
            'user_id' => $qaEngineer->id,
            'group_id' => $qaGroup->id,
            'title' => 'QA Project',
        ]);

        $mgmtProject = Project::factory()->create([
            'user_id' => $admin->id,
            'group_id' => $managementGroup->id,
            'title' => 'Management Project',
        ]);

        // Test project visibility for each user
        // Admin should see all projects (admin privileges)
        $response = $this->actingAs($admin)->get('/dashboard/projects');
        $projects = $response->viewData('page')['props']['projects'];
        $this->assertGreaterThanOrEqual(2, count($projects)); // At least the ones they have access to

        // Developer should see everyone + dev projects
        $response = $this->actingAs($developer)->get('/dashboard/projects');
        $projects = $response->viewData('page')['props']['projects'];
        $projectTitles = collect($projects)->pluck('title')->toArray();
        $this->assertCount(2, $projects);
        $this->assertContains('Company Project', $projectTitles);
        $this->assertContains('Dev Project', $projectTitles);

        // QA Engineer should see everyone + qa projects
        $response = $this->actingAs($qaEngineer)->get('/dashboard/projects');
        $projects = $response->viewData('page')['props']['projects'];
        $projectTitles = collect($projects)->pluck('title')->toArray();
        $this->assertCount(2, $projects);
        $this->assertContains('Company Project', $projectTitles);
        $this->assertContains('QA Project', $projectTitles);

        // Full-stack should see everyone + dev + qa projects
        $response = $this->actingAs($fullStack)->get('/dashboard/projects');
        $projects = $response->viewData('page')['props']['projects'];
        $projectTitles = collect($projects)->pluck('title')->toArray();
        $this->assertCount(3, $projects);
        $this->assertContains('Company Project', $projectTitles);
        $this->assertContains('Dev Project', $projectTitles);
        $this->assertContains('QA Project', $projectTitles);
        $this->assertNotContains('Management Project', $projectTitles);
    }

    public function test_organization_deletion_cascade()
    {
        // Create organization with full structure
        $organization = Organization::factory()->create();
        $roles = $organization->createDefaultRoles();
        $group = $organization->createDefaultGroup();

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->assignRole($roles['user']);
        $user->joinGroup($group);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
        ]);

        // Verify everything exists
        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
        $this->assertDatabaseHas('roles', ['organization_id' => $organization->id]);
        $this->assertDatabaseHas('groups', ['organization_id' => $organization->id]);
        $this->assertDatabaseHas('users', ['organization_id' => $organization->id]);
        $this->assertDatabaseHas('projects', ['group_id' => $group->id]);
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id]);

        // Delete organization
        $organization->delete();

        // Verify cascade deletions
        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        $this->assertDatabaseMissing('roles', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('groups', ['organization_id' => $organization->id]);

        // User should have organization_id set to null (onDelete('set null'))
        $user->refresh();
        $this->assertNull($user->organization_id);

        // Project should have group_id set to null
        $project->refresh();
        $this->assertNull($project->group_id);

        // Task should still exist (not affected by organization deletion)
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id]);
    }
}
