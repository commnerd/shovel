<?php

namespace Tests\Browser;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectTypeSummaryTest extends DuskTestCase
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

    public function test_project_type_selection_ui_exists()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')
                   ->waitForText('Create New Project')

                   // Verify project type selection exists
                   ->assertSee('Iterative Project')
                   ->assertSee('Finite Project')

                   // Verify radio buttons exist
                   ->assertPresent('input[value="iterative"]')
                   ->assertPresent('input[value="finite"]')

                   // Verify default selection is iterative
                   ->assertRadioSelected('input[name="project_type"]', 'iterative');
        });
    }

    public function test_can_select_finite_project_type_in_ui()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')
                   ->waitForText('Create New Project')

                   // Select finite project type
                   ->click('input[value="finite"]')
                   ->assertRadioSelected('input[name="project_type"]', 'finite')

                   // Verify iterative settings are hidden for finite projects
                   ->assertDontSee('Default Iteration Length')
                   ->assertDontSee('Auto-create iterations');
        });
    }

    public function test_can_select_iterative_project_type_in_ui()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')
                   ->waitForText('Create New Project')

                   // Select iterative project type
                   ->click('input[value="iterative"]')
                   ->assertRadioSelected('input[name="project_type"]', 'iterative')

                   // Verify iterative settings are visible
                   ->assertSee('Default Iteration Length')
                   ->assertSee('Auto-create iterations');
        });
    }

    public function test_project_type_switching_in_ui()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                   ->visit('/dashboard/projects/create')
                   ->waitForText('Create New Project')

                   // Start with iterative (default)
                   ->assertRadioSelected('input[name="project_type"]', 'iterative')
                   ->assertSee('Default Iteration Length')

                   // Switch to finite
                   ->click('input[value="finite"]')
                   ->assertRadioSelected('input[name="project_type"]', 'finite')
                   ->assertDontSee('Default Iteration Length')

                   // Switch back to iterative
                   ->click('input[value="iterative"]')
                   ->assertRadioSelected('input[name="project_type"]', 'iterative')
                   ->assertSee('Default Iteration Length');
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
                   ->visit('/dashboard/projects')
                   ->waitForText('Projects')

                   // Verify sections exist
                   ->assertSee('Finite Projects')
                   ->assertSee('Iterative Projects')

                   // Verify projects are displayed
                   ->assertSee('Test Finite Project')
                   ->assertSee('Test Iterative Project');
        });
    }
}
