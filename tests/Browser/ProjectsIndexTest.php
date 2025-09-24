<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Project;
use App\Models\Group;
use App\Models\Organization;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectsIndexTest extends DuskTestCase
{
    use DatabaseMigrations, MocksAIServices;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock AI services to prevent real API calls
        $this->mockAIServices();

        // Set up default organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
    }

    /**
     * Test that the projects page loads correctly when user has no projects.
     */
    public function test_projects_index_loads_with_no_projects()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/projects')
                ->assertPathIs('/dashboard/projects/create')
                ->assertSee('Create New Project')
                ->screenshot('projects-index-no-projects-redirect');
        });
    }

    /**
     * Test that the projects page displays iterative projects correctly.
     */
    public function test_projects_index_displays_iterative_projects()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create iterative projects
        $iterativeProject1 = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => 'Iterative Project 1',
            'description' => 'This is an iterative project for testing'
        ]);

        $iterativeProject2 = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => 'Iterative Project 2',
            'description' => 'Another iterative project'
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/projects')
                ->assertPathIs('/dashboard/projects')
                ->assertSee('Projects')
                ->assertSee('🔄 Iterative Projects')
                ->assertSee('Iterative Project 1')
                ->assertSee('Iterative Project 2')
                ->assertSee('This is an iterative project for testing')
                ->assertSee('Another iterative project')
                ->screenshot('projects-index-iterative-projects');
        });
    }

    /**
     * Test that the projects page displays finite projects correctly.
     */
    public function test_projects_index_displays_finite_projects()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create finite projects
        $finiteProject1 = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'finite',
            'title' => 'Finite Project 1',
            'description' => 'This is a finite project for testing',
            'due_date' => now()->addDays(30)
        ]);

        $finiteProject2 = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'finite',
            'title' => 'Finite Project 2',
            'description' => 'Another finite project',
            'due_date' => now()->addDays(15)
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/projects')
                ->assertPathIs('/dashboard/projects')
                ->assertSee('Projects')
                ->assertSee('🎯 Finite Projects')
                ->assertSee('Finite Project 1')
                ->assertSee('Finite Project 2')
                ->assertSee('This is a finite project for testing')
                ->assertSee('Another finite project')
                ->assertSee('Due:')
                ->screenshot('projects-index-finite-projects');
        });
    }

    /**
     * Test that the projects page displays both iterative and finite projects correctly.
     */
    public function test_projects_index_displays_mixed_project_types()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create mixed projects
        $iterativeProject = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => 'Iterative Project',
            'description' => 'This is an iterative project'
        ]);

        $finiteProject = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'finite',
            'title' => 'Finite Project',
            'description' => 'This is a finite project',
            'due_date' => now()->addDays(30)
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/projects')
                ->assertPathIs('/dashboard/projects')
                ->assertSee('Projects')
                ->assertSee('🔄 Iterative Projects')
                ->assertSee('🎯 Finite Projects')
                ->assertSee('Iterative Project')
                ->assertSee('Finite Project')
                ->assertSee('This is an iterative project')
                ->assertSee('This is a finite project')
                ->assertSee('New Project') // Should show the create button
                ->screenshot('projects-index-mixed-projects');
        });
    }

    /**
     * Test that project cards display correct information.
     */
    public function test_project_cards_display_correct_information()
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

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                ->visit('/dashboard/projects')
                ->assertPathIs('/dashboard/projects')
                ->assertSee('Test Project')
                ->assertSee('Test project description')
                ->assertSee('3 tasks') // Should show task count
                ->assertSee('Due:') // Should show due date
                ->assertSee('View Tasks') // Should show action buttons
                ->assertSee('Edit')
                ->screenshot('projects-index-project-card-details');
        });
    }

    /**
     * Test that the "New Project" button is visible when projects exist.
     */
    public function test_new_project_button_is_visible_with_projects()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create a project
        Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'title' => 'Test Project'
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/projects')
                ->assertPathIs('/dashboard/projects')
                ->assertSee('New Project')
                ->clickLink('New Project')
                ->assertPathIs('/dashboard/projects/create')
                ->assertSee('Create New Project')
                ->screenshot('projects-index-new-project-button');
        });
    }

    /**
     * Test that project action buttons work correctly.
     */
    public function test_project_action_buttons_work()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        // Create a project
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'finite',
            'title' => 'Test Project'
        ]);

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                ->visit('/dashboard/projects')
                ->assertPathIs('/dashboard/projects')
                ->assertSee('Test Project')
                ->clickLink('View Tasks')
                ->assertPathIs("/dashboard/projects/{$project->id}/tasks")
                ->assertSee('Project Tasks')
                ->back()
                ->assertPathIs('/dashboard/projects')
                ->clickLink('Edit')
                ->assertPathIs("/dashboard/projects/{$project->id}/edit")
                ->assertSee('Edit Project')
                ->screenshot('projects-index-action-buttons');
        });
    }

    /**
     * Test that projects page handles projects without titles correctly.
     */
    public function test_projects_without_titles_display_correctly()
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

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                ->visit('/dashboard/projects')
                ->assertPathIs('/dashboard/projects')
                ->assertSee('Untitled Project')
                ->assertSee("#{$project->id}")
                ->assertSee('Project without title')
                ->screenshot('projects-index-untitled-project');
        });
    }

    /**
     * Test that the projects page handles database errors gracefully.
     */
    public function test_projects_index_handles_database_errors()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email_verified_at' => now()
        ]);

        // Mock a database error by temporarily dropping the projects table
        // This test ensures the controller handles exceptions gracefully
        \DB::statement('DROP TABLE IF EXISTS projects');

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/projects')
                ->assertPathIs('/dashboard/projects/create')
                ->assertSee('Create New Project')
                ->screenshot('projects-index-database-error-handling');
        });

        // Restore the projects table
        $this->artisan('migrate');
    }
}
