<?php

namespace Tests\Browser;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectTypeTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'test.com'
        ]);

        $this->group = Group::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Group',
            'is_default' => true
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'test@test.com'
        ]);

        $this->user->groups()->attach($this->group);
    }

    public function test_can_select_finite_project_type()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/projects/create')
                   ->waitForText('Create New Project')

                   // Fill in project details
                   ->type('description', 'This is a finite project for testing')

                   // Select finite project type
                   ->click('input[value="finite"]')
                   ->assertRadioSelected('project_type', 'finite')
                   ->assertSee('Finite Project')

                   // Verify iterative settings are hidden
                   ->assertDontSee('Default Iteration Length')

                   // Click generate tasks
                   ->click('button[type="submit"]')
                   ->waitForLocation('/projects/create/tasks')

                   // Verify we're on the tasks page and project type is preserved
                   ->assertSee('Suggested Tasks')
                   ->assertInputValue('project_type', 'finite');
        });
    }

    public function test_can_select_iterative_project_type()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/projects/create')
                   ->waitForText('Create New Project')

                   // Fill in project details
                   ->type('description', 'This is an iterative project for testing')

                   // Select iterative project type (should be default)
                   ->assertRadioSelected('project_type', 'iterative')
                   ->assertSee('Iterative Project')

                   // Verify iterative settings are visible
                   ->assertSee('Default Iteration Length')
                   ->assertSee('Auto-create iterations')

                   // Click generate tasks
                   ->click('button[type="submit"]')
                   ->waitForLocation('/projects/create/tasks')

                   // Verify we're on the tasks page and project type is preserved
                   ->assertSee('Suggested Tasks')
                   ->assertInputValue('project_type', 'iterative');
        });
    }

    public function test_project_type_selection_ui_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/projects/create')
                   ->waitForText('Create New Project')

                   // Test clicking on the finite project card
                   ->click('.grid .border-2:last-child') // Click on finite project card
                   ->assertRadioSelected('project_type', 'finite')
                   ->assertSee('Traditional project with defined scope')

                   // Test clicking on the iterative project card
                   ->click('.grid .border-2:first-child') // Click on iterative project card
                   ->assertRadioSelected('project_type', 'iterative')
                   ->assertSee('Agile project with sprints')

                   // Verify iterative settings appear/disappear
                   ->assertSee('Default Iteration Length')
                   ->click('.grid .border-2:last-child') // Back to finite
                   ->assertDontSee('Default Iteration Length');
        });
    }

    public function test_finite_project_creation_complete_flow()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/projects/create')
                   ->waitForText('Create New Project')

                   // Fill in project details
                   ->type('description', 'Complete finite project test')

                   // Select finite project type
                   ->click('input[value="finite"]')
                   ->assertRadioSelected('project_type', 'finite')

                   // Navigate to tasks page
                   ->click('button[type="submit"]')
                   ->waitForLocation('/projects/create/tasks')

                   // Verify project type is preserved
                   ->assertInputValue('project_type', 'finite')

                   // Create the project (assuming there are suggested tasks)
                   ->waitFor('.task-card', 10) // Wait for AI to generate tasks
                   ->click('button:contains("Create Project")')
                   ->waitForLocation('/projects')

                   // Verify project appears in finite projects section
                   ->assertSee('Complete finite project test')
                   ->assertSee('Finite Projects')
                   ->assertSee('Iterative Projects');
        });
    }

    public function test_iterative_project_creation_with_settings()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/projects/create')
                   ->waitForText('Create New Project')

                   // Fill in project details
                   ->type('description', 'Complete iterative project test')

                   // Select iterative project type
                   ->click('input[value="iterative"]')
                   ->assertRadioSelected('project_type', 'iterative')

                   // Configure iterative settings
                   ->select('default_iteration_length_weeks', '3')
                   ->check('auto_create_iterations')

                   // Navigate to tasks page
                   ->click('button[type="submit"]')
                   ->waitForLocation('/projects/create/tasks')

                   // Verify project type and settings are preserved
                   ->assertInputValue('project_type', 'iterative')
                   ->assertInputValue('default_iteration_length_weeks', '3')
                   ->assertChecked('auto_create_iterations')

                   // Create the project
                   ->waitFor('.task-card', 10)
                   ->click('button:contains("Create Project")')
                   ->waitForLocation('/projects')

                   // Verify project appears in iterative projects section
                   ->assertSee('Complete iterative project test')
                   ->assertSee('Finite Projects')
                   ->assertSee('Iterative Projects');
        });
    }

    public function test_project_type_persistence_in_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/projects/create')
                   ->waitForText('Create New Project')

                   // Select finite project type
                   ->click('input[value="finite"]')
                   ->assertRadioSelected('project_type', 'finite')

                   // Fill in description
                   ->type('description', 'Testing form persistence')

                   // Navigate to tasks page
                   ->click('button[type="submit"]')
                   ->waitForLocation('/projects/create/tasks')

                   // Go back to edit form (if there's a back button)
                   ->click('button:contains("Back")')
                   ->waitForLocation('/projects/create')

                   // Verify project type is still selected
                   ->assertRadioSelected('project_type', 'finite');
        });
    }

    public function test_project_type_validation_in_ui()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/projects/create')
                   ->waitForText('Create New Project')

                   // Try to submit without description (should show validation error)
                   ->click('input[value="finite"]')
                   ->click('button[type="submit"]')
                   ->assertSee('The description field is required')

                   // Fill in description and try again
                   ->type('description', 'Valid finite project')
                   ->click('button[type="submit"]')
                   ->waitForLocation('/projects/create/tasks')

                   // Verify we successfully navigated to tasks page
                   ->assertSee('Suggested Tasks');
        });
    }

    public function test_project_type_display_on_index_page()
    {
        // Create test projects
        Project::factory()->create([
            'title' => 'Test Finite Project',
            'project_type' => 'finite',
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        Project::factory()->create([
            'title' => 'Test Iterative Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/projects')
                   ->waitForText('Projects')

                   // Verify finite projects section
                   ->assertSee('Finite Projects')
                   ->assertSee('Test Finite Project')

                   // Verify iterative projects section
                   ->assertSee('Iterative Projects')
                   ->assertSee('Test Iterative Project')

                   // Verify projects are in correct sections
                   ->within('.finite-projects', function ($browser) {
                       $browser->assertSee('Test Finite Project')
                              ->assertDontSee('Test Iterative Project');
                   })

                   ->within('.iterative-projects', function ($browser) {
                       $browser->assertSee('Test Iterative Project')
                              ->assertDontSee('Test Finite Project');
                   });
        });
    }

    public function test_project_type_switching_in_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/projects/create')
                   ->waitForText('Create New Project')

                   // Start with finite project
                   ->click('input[value="finite"]')
                   ->assertRadioSelected('project_type', 'finite')
                   ->assertDontSee('Default Iteration Length')

                   // Switch to iterative
                   ->click('input[value="iterative"]')
                   ->assertRadioSelected('project_type', 'iterative')
                   ->assertSee('Default Iteration Length')

                   // Configure iterative settings
                   ->select('default_iteration_length_weeks', '4')
                   ->check('auto_create_iterations')

                   // Switch back to finite
                   ->click('input[value="finite"]')
                   ->assertRadioSelected('project_type', 'finite')
                   ->assertDontSee('Default Iteration Length')

                   // Switch back to iterative and verify settings are reset
                   ->click('input[value="iterative"]')
                   ->assertRadioSelected('project_type', 'iterative')
                   ->assertSee('Default Iteration Length')
                   ->assertInputValue('default_iteration_length_weeks', '2') // Should be default
                   ->assertNotChecked('auto_create_iterations'); // Should be unchecked
        });
    }
}
