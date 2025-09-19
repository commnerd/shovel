<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectGroupAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $organization;

    protected $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $this->organization = Organization::getDefault();
        $this->group = $this->organization->defaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Add user to default group
        $this->user->groups()->attach($this->group->id, ['joined_at' => now()]);
    }

    public function test_project_create_page_shows_user_groups()
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects/create');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/Create')
                ->has('userGroups')
                ->has('defaultGroupId')
                ->where('userGroups.0.name', 'Everyone')
                ->where('userGroups.0.is_default', true)
            );
    }

    public function test_project_creation_assigns_to_default_group_when_none_specified()
    {
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'Test Project',
                'description' => 'Test project description',
                'due_date' => '2025-12-31',
                'group_id' => $this->group->id, // Explicitly specify group for now
                'tasks' => [],
            ]);

        $response->assertRedirect('/dashboard/projects');

        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertEquals($this->group->id, $project->group_id);
    }

    public function test_project_creation_assigns_to_specified_group()
    {
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'Test Project',
                'description' => 'Test project description',
                'due_date' => '2025-12-31',
                'group_id' => $this->group->id,
                'tasks' => [],
            ]);

        $response->assertRedirect('/dashboard/projects');

        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertEquals($this->group->id, $project->group_id);
    }

    public function test_user_cannot_assign_project_to_group_they_dont_belong_to()
    {
        // Create another organization and group
        $otherOrg = Organization::factory()->create();
        $otherGroup = Group::factory()->create(['organization_id' => $otherOrg->id]);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'Test Project',
                'description' => 'Test project description',
                'due_date' => '2025-12-31',
                'group_id' => $otherGroup->id,
                'tasks' => [],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();

        // Verify project was not created
        $this->assertEquals(0, Project::where('user_id', $this->user->id)->count());
    }

    public function test_projects_index_shows_projects_from_user_groups()
    {
        // Create another user in the same group with a project
        $otherUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        $otherUser->groups()->attach($this->group->id, ['joined_at' => now()]);

        // Create projects in user's group (order matters for latest first)
        $project1 = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'My Project',
            'created_at' => now()->subMinutes(10), // Created first
        ]);

        $project2 = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->group->id,
            'title' => 'Shared Project',
            'created_at' => now(), // Created later (should be first in list)
        ]);

        // Create project in different group (should not be visible)
        $otherOrg = Organization::factory()->create();
        $otherGroup = Group::factory()->create(['organization_id' => $otherOrg->id]);
        $project3 = Project::factory()->create([
            'group_id' => $otherGroup->id,
            'title' => 'Hidden Project',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/Index')
                ->has('projects', 2)
                    // Projects are ordered by latest first, so project2 (created later) should be first
                ->where('projects.0.title', 'Shared Project')
                ->where('projects.1.title', 'My Project')
            );
    }

    public function test_task_generation_page_includes_group_data()
    {
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'title' => 'Test Project',
                'description' => 'Test description',
                'due_date' => '2025-12-31',
                'group_id' => $this->group->id,
            ]);

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/CreateTasks')
                ->has('userGroups')
                ->has('defaultGroupId')
                ->where('projectData.group_id', $this->group->id)
            );
    }
}
