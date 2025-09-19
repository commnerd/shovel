<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $organization;
    protected $otherOrganization;
    protected $adminRole;
    protected $userRole;
    protected $group1;
    protected $group2;
    protected $admin;
    protected $user1;
    protected $user2;
    protected $outsideUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up organizations
        $this->organization = Organization::factory()->create(['name' => 'Test Org']);
        $this->otherOrganization = Organization::factory()->create(['name' => 'Other Org']);
        
        // Create roles
        $orgRoles = $this->organization->createDefaultRoles();
        $this->adminRole = $orgRoles['admin'];
        $this->userRole = $orgRoles['user'];
        
        $otherOrgRoles = $this->otherOrganization->createDefaultRoles();
        
        // Create groups
        $this->group1 = Group::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Group 1',
            'is_default' => true,
        ]);
        
        $this->group2 = Group::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Group 2',
            'is_default' => false,
        ]);
        
        $otherGroup = Group::factory()->create([
            'organization_id' => $this->otherOrganization->id,
            'name' => 'Other Group',
            'is_default' => true,
        ]);
        
        // Create users
        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->admin->assignRole($this->adminRole);
        $this->admin->assignRole($this->userRole);
        $this->admin->joinGroup($this->group1);
        
        $this->user1 = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->user1->assignRole($this->userRole);
        $this->user1->joinGroup($this->group1);
        
        $this->user2 = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->user2->assignRole($this->userRole);
        $this->user2->joinGroup($this->group2);
        
        $this->outsideUser = User::factory()->create([
            'organization_id' => $this->otherOrganization->id,
            'pending_approval' => false,
        ]);
        $this->outsideUser->assignRole($otherOrgRoles['user']);
        $this->outsideUser->joinGroup($otherGroup);
    }

    public function test_user_can_access_own_project()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user1->id,
            'group_id' => $this->group1->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->get("/dashboard/projects/{$project->id}/edit");

        $response->assertOk();
    }

    public function test_user_can_access_project_in_same_group()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user2->id, // Different user
            'group_id' => $this->group1->id, // Same group as user1
        ]);

        // Add user1 to group1 (they should already be in it from setUp)
        $response = $this->actingAs($this->user1)
            ->get("/dashboard/projects/{$project->id}/edit");

        $response->assertOk();
    }

    public function test_user_cannot_access_project_in_different_group()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user2->id,
            'group_id' => $this->group2->id, // user1 is not in group2
        ]);

        $response = $this->actingAs($this->user1)
            ->get("/dashboard/projects/{$project->id}/edit");

        $response->assertStatus(403);
    }

    public function test_admin_can_access_any_project_in_organization()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user2->id,
            'group_id' => $this->group2->id, // Admin is not in group2
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/dashboard/projects/{$project->id}/edit");

        $response->assertOk();
    }

    public function test_user_cannot_access_project_from_different_organization()
    {
        $project = Project::factory()->create([
            'user_id' => $this->outsideUser->id,
            'group_id' => Group::factory()->create(['organization_id' => $this->otherOrganization->id])->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->get("/dashboard/projects/{$project->id}/edit");

        $response->assertStatus(403);
    }

    public function test_user_can_modify_own_project()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user1->id,
            'group_id' => $this->group1->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->put("/dashboard/projects/{$project->id}", [
                'title' => 'Updated Title',
                'description' => 'Updated description',
                'due_date' => '2025-12-31',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_user_cannot_modify_project_they_dont_own()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user2->id,
            'group_id' => $this->group1->id, // Same group but different owner
        ]);

        $response = $this->actingAs($this->user1)
            ->put("/dashboard/projects/{$project->id}", [
                'title' => 'Hacked Title',
                'description' => 'Updated description',
                'due_date' => '2025-12-31',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
            'title' => 'Hacked Title',
        ]);
    }

    public function test_admin_can_modify_any_project_in_organization()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user2->id,
            'group_id' => $this->group2->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/dashboard/projects/{$project->id}", [
                'title' => 'Admin Updated',
                'description' => 'Updated by admin',
                'due_date' => '2025-12-31',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Admin Updated',
        ]);
    }

    public function test_user_can_delete_own_project()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user1->id,
            'group_id' => $this->group1->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->delete("/dashboard/projects/{$project->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_user_cannot_delete_project_they_dont_own()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user2->id,
            'group_id' => $this->group1->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->delete("/dashboard/projects/{$project->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_admin_can_delete_any_project_in_organization()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user2->id,
            'group_id' => $this->group2->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/dashboard/projects/{$project->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_projects_index_only_shows_accessible_projects()
    {
        // Create projects in different groups and organizations
        $ownProject = Project::factory()->create([
            'user_id' => $this->user1->id,
            'group_id' => $this->group1->id,
            'title' => 'Own Project',
        ]);

        $sameGroupProject = Project::factory()->create([
            'user_id' => $this->user2->id,
            'group_id' => $this->group1->id,
            'title' => 'Same Group Project',
        ]);

        $differentGroupProject = Project::factory()->create([
            'user_id' => $this->user2->id,
            'group_id' => $this->group2->id,
            'title' => 'Different Group Project',
        ]);

        $outsideProject = Project::factory()->create([
            'user_id' => $this->outsideUser->id,
            'group_id' => Group::factory()->create(['organization_id' => $this->otherOrganization->id])->id,
            'title' => 'Outside Project',
        ]);

        $response = $this->actingAs($this->user1)
            ->get('/dashboard/projects');

        $response->assertOk();
        
        // Should see own project and same group project, but not others
        $projects = $response->viewData('page')['props']['projects'];
        
        $this->assertCount(2, $projects);
        
        $projectTitles = collect($projects)->pluck('title')->toArray();
        $this->assertContains('Own Project', $projectTitles);
        $this->assertContains('Same Group Project', $projectTitles);
        $this->assertNotContains('Different Group Project', $projectTitles);
        $this->assertNotContains('Outside Project', $projectTitles);
    }
}
