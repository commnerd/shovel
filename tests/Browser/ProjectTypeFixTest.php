<?php

namespace Tests\Browser;

use App\Models\Project;
use App\Models\User;
use App\Models\Organization;
use App\Models\Group;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectTypeFixTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $user;
    protected $organization;
    protected $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test organization and group
        $this->organization = Organization::factory()->create(['name' => 'Test Org']);
        $this->group = Group::factory()->create([
            'organization_id' => $this->organization->id,
            'is_default' => true,
        ]);

        // Create test user
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_finite_project_creation_workflow()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')

                   // Fill in basic project details
                   ->type('title', 'Finite Project Test')
                   ->type('description', 'This is a test finite project description')

                   // Select finite project type
                   ->click('input[value="finite"]')

                   // Verify the finite option is selected
                   ->assertRadioSelected('input[name="project_type"]', 'finite')

                   // Verify the finite option has the correct styling
                   ->assertHasClass('div:has(input[value="finite"])', 'border-blue-500')

                   // Click generate tasks button
                   ->press('Generate Tasks with AI')

                   // Wait for the task generation page
                   ->waitForText('Suggested Tasks', 10)

                   // Verify we're on the task generation page
                   ->assertPathIs('/dashboard/projects/create/tasks')

                   // Verify the project type is correctly displayed (if shown on the page)
                   ->assertPresent('input[name="project_type"][value="finite"]')

                   // Accept all suggested tasks and create project
                   ->press('Accept All & Create Project')

                   // Wait for project creation
                   ->waitForText('Project created successfully', 15)

                   // Verify we're redirected to projects index
                   ->assertPathIs('/dashboard/projects')

                   // Verify the project appears in the list
                   ->assertSee('Finite Project Test');
        });

        // Verify project was created with correct type in database
        $project = Project::where('title', 'Finite Project Test')->first();
        $this->assertNotNull($project);
        $this->assertEquals('finite', $project->project_type);
    }

    public function test_iterative_project_creation_workflow()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')

                   // Fill in basic project details
                   ->type('title', 'Iterative Project Test')
                   ->type('description', 'This is a test iterative project description')

                   // Select iterative project type (should be default)
                   ->click('input[value="iterative"]')

                   // Verify the iterative option is selected
                   ->assertRadioSelected('input[name="project_type"]', 'iterative')

                   // Verify the iterative option has the correct styling
                   ->assertHasClass('div:has(input[value="iterative"])', 'border-blue-500')

                   // Click generate tasks button
                   ->press('Generate Tasks with AI')

                   // Wait for the task generation page
                   ->waitForText('Suggested Tasks', 10)

                   // Verify we're on the task generation page
                   ->assertPathIs('/dashboard/projects/create/tasks')

                   // Verify the project type is correctly displayed
                   ->assertPresent('input[name="project_type"][value="iterative"]')

                   // Accept all suggested tasks and create project
                   ->press('Accept All & Create Project')

                   // Wait for project creation
                   ->waitForText('Project created successfully', 15)

                   // Verify we're redirected to projects index
                   ->assertPathIs('/dashboard/projects')

                   // Verify the project appears in the list
                   ->assertSee('Iterative Project Test');
        });

        // Verify project was created with correct type in database
        $project = Project::where('title', 'Iterative Project Test')->first();
        $this->assertNotNull($project);
        $this->assertEquals('iterative', $project->project_type);
    }

    public function test_project_type_switching_ui()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')

                   // Verify default selection is iterative
                   ->assertRadioSelected('input[name="project_type"]', 'iterative')
                   ->assertHasClass('div:has(input[value="iterative"])', 'border-blue-500')

                   // Click on finite project option
                   ->click('div:has(input[value="finite"])')

                   // Verify finite is now selected
                   ->assertRadioSelected('input[name="project_type"]', 'finite')
                   ->assertHasClass('div:has(input[value="finite"])', 'border-blue-500')
                   ->assertDontHaveClass('div:has(input[value="iterative"])', 'border-blue-500')

                   // Click on iterative project option
                   ->click('div:has(input[value="iterative"])')

                   // Verify iterative is now selected
                   ->assertRadioSelected('input[name="project_type"]', 'iterative')
                   ->assertHasClass('div:has(input[value="iterative"])', 'border-blue-500')
                   ->assertDontHaveClass('div:has(input[value="finite"])', 'border-blue-500')

                   // Click on finite project option again
                   ->click('div:has(input[value="finite"])')

                   // Verify finite is selected again
                   ->assertRadioSelected('input[name="project_type"]', 'finite')
                   ->assertHasClass('div:has(input[value="finite"])', 'border-blue-500');
        });
    }

    public function test_project_type_form_submission_with_finite()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')

                   // Fill in project details
                   ->type('title', 'Form Submission Finite Test')
                   ->type('description', 'Testing form submission with finite project type')

                   // Select finite project type
                   ->click('input[value="finite"]')

                   // Verify selection
                   ->assertRadioSelected('input[name="project_type"]', 'finite')

                   // Submit form
                   ->press('Generate Tasks with AI')

                   // Wait for navigation
                   ->waitForText('Suggested Tasks', 10)

                   // Verify we're on the correct page with correct data
                   ->assertPathIs('/dashboard/projects/create/tasks')

                   // Check that the form data was correctly passed
                   ->assertPresent('input[name="project_type"][value="finite"]')

                   // Create the project
                   ->press('Accept All & Create Project')

                   // Wait for completion
                   ->waitForText('Project created successfully', 15);
        });

        // Verify in database
        $project = Project::where('title', 'Form Submission Finite Test')->first();
        $this->assertNotNull($project);
        $this->assertEquals('finite', $project->project_type);
    }

    public function test_project_type_form_submission_with_iterative()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')

                   // Fill in project details
                   ->type('title', 'Form Submission Iterative Test')
                   ->type('description', 'Testing form submission with iterative project type')

                   // Select iterative project type (explicitly)
                   ->click('input[value="iterative"]')

                   // Verify selection
                   ->assertRadioSelected('input[name="project_type"]', 'iterative')

                   // Submit form
                   ->press('Generate Tasks with AI')

                   // Wait for navigation
                   ->waitForText('Suggested Tasks', 10)

                   // Verify we're on the correct page with correct data
                   ->assertPathIs('/dashboard/projects/create/tasks')

                   // Check that the form data was correctly passed
                   ->assertPresent('input[name="project_type"][value="iterative"]')

                   // Create the project
                   ->press('Accept All & Create Project')

                   // Wait for completion
                   ->waitForText('Project created successfully', 15);
        });

        // Verify in database
        $project = Project::where('title', 'Form Submission Iterative Test')->first();
        $this->assertNotNull($project);
        $this->assertEquals('iterative', $project->project_type);
    }

    public function test_project_type_visual_feedback()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')

                   // Verify initial state - iterative should be selected by default
                   ->assertRadioSelected('input[name="project_type"]', 'iterative')
                   ->assertHasClass('div:has(input[value="iterative"])', 'border-blue-500')
                   ->assertHasClass('div:has(input[value="iterative"])', 'bg-blue-100')

                   // Verify finite is not selected
                   ->assertDontHaveClass('div:has(input[value="finite"])', 'border-blue-500')
                   ->assertDontHaveClass('div:has(input[value="finite"])', 'bg-blue-100')

                   // Click finite
                   ->click('input[value="finite"]')

                   // Verify finite is now selected with correct styling
                   ->assertRadioSelected('input[name="project_type"]', 'finite')
                   ->assertHasClass('div:has(input[value="finite"])', 'border-blue-500')
                   ->assertHasClass('div:has(input[value="finite"])', 'bg-blue-100')

                   // Verify iterative is no longer selected
                   ->assertDontHaveClass('div:has(input[value="iterative"])', 'border-blue-500')
                   ->assertDontHaveClass('div:has(input[value="iterative"])', 'bg-blue-100');
        });
    }

    public function test_project_type_with_ai_generation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')

                   // Fill in project details
                   ->type('title', 'AI Generation Test')
                   ->type('description', 'This project will test AI generation with finite project type')

                   // Select finite project type
                   ->click('input[value="finite"]')

                   // Verify selection
                   ->assertRadioSelected('input[name="project_type"]', 'finite')

                   // Submit for AI generation
                   ->press('Generate Tasks with AI')

                   // Wait for AI generation to complete
                   ->waitForText('Suggested Tasks', 15)

                   // Verify we're on the task generation page
                   ->assertPathIs('/dashboard/projects/create/tasks')

                   // Verify project type is preserved
                   ->assertPresent('input[name="project_type"][value="finite"]')

                   // Verify tasks were generated (should have some tasks)
                   ->assertPresent('div[data-testid="suggested-task"], .task-item, [class*="task"]')

                   // Create the project
                   ->press('Accept All & Create Project')

                   // Wait for completion
                   ->waitForText('Project created successfully', 15);
        });

        // Verify project was created with correct type and has tasks
        $project = Project::where('title', 'AI Generation Test')->first();
        $this->assertNotNull($project);
        $this->assertEquals('finite', $project->project_type);
        $this->assertGreaterThan(0, $project->tasks()->count());
    }
}
