<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Group;
use App\Models\Organization;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ProjectsIndexTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
    }

    /**
     * Test that the projects index page loads correctly when user has no projects.
     */
    public function test_projects_index_redirects_to_create_when_no_projects()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard/projects');

        $response->assertRedirect('/dashboard/projects/create');
    }

    /**
     * Test that the projects index page loads correctly with iterative projects.
     */
    public function test_projects_index_loads_with_iterative_projects()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create iterative projects
        Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => 'Iterative Project 1',
            'description' => 'This is an iterative project for testing'
        ]);

        Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => 'Iterative Project 2',
            'description' => 'Another iterative project'
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard/projects');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('iterativeProjects', 2)
            ->has('finiteProjects', 0)
        );

        // Check that both projects exist (order may vary in parallel tests)
        $iterativeProjects = $response->viewData('page')['props']['iterativeProjects'];
        $titles = collect($iterativeProjects)->pluck('title')->toArray();
        $this->assertContains('Iterative Project 1', $titles);
        $this->assertContains('Iterative Project 2', $titles);
    }

    /**
     * Test that the projects index page loads correctly with finite projects.
     */
    public function test_projects_index_loads_with_finite_projects()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create finite projects
        Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'finite',
            'title' => 'Finite Project 1',
            'description' => 'This is a finite project for testing',
            'due_date' => now()->addDays(30)
        ]);

        Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'finite',
            'title' => 'Finite Project 2',
            'description' => 'Another finite project',
            'due_date' => now()->addDays(15)
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard/projects');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('iterativeProjects', 0)
            ->has('finiteProjects', 2)
            ->where('finiteProjects.0.title', 'Finite Project 1') // First created
            ->where('finiteProjects.1.title', 'Finite Project 2')
        );
    }

    /**
     * Test that the projects index page loads correctly with mixed project types.
     */
    public function test_projects_index_loads_with_mixed_project_types()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create mixed projects
        Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => 'Iterative Project',
            'description' => 'This is an iterative project'
        ]);

        Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'finite',
            'title' => 'Finite Project',
            'description' => 'This is a finite project',
            'due_date' => now()->addDays(30)
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard/projects');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('iterativeProjects', 1)
            ->has('finiteProjects', 1)
            ->where('iterativeProjects.0.title', 'Iterative Project')
            ->where('finiteProjects.0.title', 'Finite Project')
        );
    }

    /**
     * Test that project data includes all required fields.
     */
    public function test_project_data_includes_all_required_fields()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create a project with tasks
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'finite',
            'title' => 'Test Project',
            'description' => 'Test project description',
            'due_date' => now()->addDays(30)
        ]);

        // Add some tasks to the project
        $project->tasks()->createMany([
            ['title' => 'Task 1', 'description' => 'First task', 'status' => 'pending'],
            ['title' => 'Task 2', 'description' => 'Second task', 'status' => 'completed'],
            ['title' => 'Task 3', 'description' => 'Third task', 'status' => 'in_progress']
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard/projects');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('finiteProjects', 1)
            ->where('finiteProjects.0.id', $project->id)
            ->where('finiteProjects.0.title', 'Test Project')
            ->where('finiteProjects.0.description', 'Test project description')
            ->where('finiteProjects.0.project_type', 'finite')
            ->where('finiteProjects.0.tasks_count', 3)
            ->has('finiteProjects.0.due_date')
            ->has('finiteProjects.0.created_at')
            ->has('finiteProjects.0.group_name')
        );
    }

    /**
     * Test that projects without titles are handled correctly.
     */
    public function test_projects_without_titles_are_handled_correctly()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create a project without a title
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => null,
            'description' => 'Project without title'
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard/projects');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('iterativeProjects', 1)
            ->where('iterativeProjects.0.title', null)
            ->where('iterativeProjects.0.description', 'Project without title')
        );
    }

    /**
     * Test that the controller handles database errors gracefully.
     */
    public function test_projects_index_handles_database_errors()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);

        // Mock a database error by temporarily dropping the projects table
        \DB::statement('DROP TABLE IF EXISTS projects');

        $response = $this->actingAs($user)
            ->get('/dashboard/projects');

        $response->assertRedirect('/dashboard/projects/create');

        // Restore the projects table by running migrations
        $this->artisan('migrate:fresh');
    }

    /**
     * Test that projects are ordered by creation date (latest first).
     */
    public function test_projects_are_ordered_by_creation_date()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create projects with different creation times
        $firstProject = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => 'First Project',
            'created_at' => now()->subHours(2)
        ]);

        $secondProject = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => 'Second Project',
            'created_at' => now()->subHours(1)
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard/projects');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('iterativeProjects', 2)
            ->where('iterativeProjects.0.title', 'Second Project') // Latest first
            ->where('iterativeProjects.1.title', 'First Project')
        );
    }
}
