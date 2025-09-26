<?php

namespace Tests\Browser;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EmptyStateAITaskGenerationDuskTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        // Assign user to organization
        $this->user->update(['organization_id' => $organization->id]);

        // Add user to group
        $this->user->joinGroup($group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
        ]);
    }

    /**
     * Test that the empty state shows the AI task generation button.
     */
    public function test_empty_state_shows_ai_task_generation_button(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks')
                ->waitForText('Create First Task')
                ->assertSee('Generate tasks with AI');
        });
    }

    /**
     * Test that clicking the AI generation button navigates to the correct page.
     */
    public function test_ai_generation_button_navigates_correctly(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks')
                ->waitForText('Create First Task')
                ->clickLink('Generate tasks with AI')
                ->waitForText('Generate Tasks')
                ->assertUrlIs('http://localhost/dashboard/projects/create/tasks');
        });
    }

    /**
     * Test that the AI generation page shows project information when project_id is provided.
     */
    public function test_ai_generation_page_shows_project_information(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/create/tasks?project_id=' . $this->project->id)
                ->waitForText('Generate Tasks')
                ->assertSee($this->project->title)
                ->assertSee($this->project->description);
        });
    }

    /**
     * Test that the AI generation button preserves filter state in the return URL.
     */
    public function test_ai_generation_button_preserves_filter_state(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->waitForText('Create First Task')
                ->clickLink('Generate tasks with AI')
                ->waitForText('Generate Tasks')
                ->assertUrlIs('http://localhost/dashboard/projects/create/tasks');
        });
    }

    /**
     * Test that the empty state is hidden when tasks exist.
     */
    public function test_empty_state_hidden_when_tasks_exist(): void
    {
        // Create a task for the project
        $this->project->tasks()->create([
            'title' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'pending',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks')
                ->waitForText('Test Task')
                ->assertDontSee('No tasks yet')
                ->assertDontSee('Generate tasks with AI');
        });
    }

    /**
     * Test that the AI generation page works without project_id.
     */
    public function test_ai_generation_page_works_without_project_id(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/create/tasks')
                ->waitForText('Generate Tasks')
                ->assertSee('Create Project') // Should show "Create Project" in breadcrumbs
                ->assertDontSee($this->project->title); // Should not show project name
        });
    }

    /**
     * Test that the AI generation button works with different filter states.
     */
    public function test_ai_generation_button_works_with_different_filters(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->waitForText('Create First Task')
                ->clickLink('Generate tasks with AI')
                ->waitForText('Generate Tasks')
                ->assertUrlIs('http://localhost/dashboard/projects/create/tasks');
        });
    }

    /**
     * Test that the AI generation button works with multiple query parameters.
     */
    public function test_ai_generation_button_works_with_multiple_query_parameters(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=board&view=kanban&sort=title&order=asc')
                ->waitForText('Create First Task')
                ->clickLink('Generate tasks with AI')
                ->waitForText('Generate Tasks')
                ->assertUrlIs('http://localhost/dashboard/projects/create/tasks');
        });
    }
}
