<?php

namespace Tests\Browser;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TaskReturnNavigationDuskTest extends DuskTestCase
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

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
        ]);
    }

    /**
     * Test that creating a task from board view returns to board view.
     */
    public function test_creating_task_from_board_view_returns_to_board_view(): void
    {
        $this->browse(function (Browser $browser) {
            // Navigate to board view
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->waitForText('Board')
                ->assertSee('Board');

            // Click "New Task" button
            $browser->clickLink('New Task')
                ->waitForText('Create Task')
                ->assertSee('Create Task');

            // Fill out the form
            $browser->type('title', 'Test Task from Board View')
                ->type('description', 'This task was created from the board view')
                ->select('status', 'pending')
                ->press('Create Task')
                ->waitForText('Test Task from Board View')
                ->assertSee('Test Task from Board View');

            // Verify we're back in board view
            $browser->assertUrlIs('http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->assertSee('Board');
        });
    }

    /**
     * Test that creating a task from list view returns to list view.
     */
    public function test_creating_task_from_list_view_returns_to_list_view(): void
    {
        $this->browse(function (Browser $browser) {
            // Navigate to list view (top-level)
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=top-level')
                ->waitForText('List')
                ->assertSee('List');

            // Click "New Task" button
            $browser->clickLink('New Task')
                ->waitForText('Create Task')
                ->assertSee('Create Task');

            // Fill out the form
            $browser->type('title', 'Test Task from List View')
                ->type('description', 'This task was created from the list view')
                ->select('status', 'pending')
                ->press('Create Task')
                ->waitForText('Test Task from List View')
                ->assertSee('Test Task from List View');

            // Verify we're back in list view
            $browser->assertUrlIs('http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=top-level')
                ->assertSee('List');
        });
    }

    /**
     * Test that editing a task from board view returns to board view.
     */
    public function test_editing_task_from_board_view_returns_to_board_view(): void
    {
        // Create a task first
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task to Edit',
            'status' => 'pending',
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            // Navigate to board view
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->waitForText('Board')
                ->assertSee('Board');

            // Click edit button on the task
            $browser->click('@edit-task-' . $task->id)
                ->waitForText('Edit Task')
                ->assertSee('Edit Task');

            // Update the task
            $browser->type('title', 'Updated Task Title')
                ->type('description', 'Updated task description')
                ->select('status', 'in_progress')
                ->press('Update Task')
                ->waitForText('Updated Task Title')
                ->assertSee('Updated Task Title');

            // Verify we're back in board view
            $browser->assertUrlIs('http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->assertSee('Board');
        });
    }

    /**
     * Test that AI breakdown from board view returns to board view.
     */
    public function test_ai_breakdown_from_board_view_returns_to_board_view(): void
    {
        // Create a task first
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task for AI Breakdown',
            'status' => 'pending',
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            // Navigate to board view
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->waitForText('Board')
                ->assertSee('Board');

            // Click AI breakdown button
            $browser->click('@breakdown-task-' . $task->id)
                ->waitForText('AI Task Breakdown')
                ->assertSee('AI Task Breakdown');

            // Generate AI breakdown (mock the AI response)
            $browser->press('Generate AI Subtasks')
                ->waitForText('Generated Subtasks')
                ->assertSee('Generated Subtasks');

            // Create the subtasks
            $browser->press('Create All Subtasks')
                ->waitForText('Task for AI Breakdown')
                ->assertSee('Task for AI Breakdown');

            // Verify we're back in board view
            $browser->assertUrlIs('http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->assertSee('Board');
        });
    }

    /**
     * Test that creating a subtask from board view returns to board view.
     */
    public function test_creating_subtask_from_board_view_returns_to_board_view(): void
    {
        // Create a parent task first
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'status' => 'pending',
        ]);

        $this->browse(function (Browser $browser) use ($parentTask) {
            // Navigate to board view
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->waitForText('Board')
                ->assertSee('Board');

            // Click add subtask button
            $browser->click('@add-subtask-' . $parentTask->id)
                ->waitForText('Create Subtask')
                ->assertSee('Create Subtask');

            // Fill out the subtask form
            $browser->type('title', 'Subtask from Board View')
                ->type('description', 'This subtask was created from the board view')
                ->select('status', 'pending')
                ->press('Create Subtask')
                ->waitForText('Subtask from Board View')
                ->assertSee('Subtask from Board View');

            // Verify we're back in board view
            $browser->assertUrlIs('http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->assertSee('Board');
        });
    }

    /**
     * Test that filter state is preserved across navigation.
     */
    public function test_filter_state_is_preserved_across_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            // Navigate to board view
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=board')
                ->waitForText('Board')
                ->assertSee('Board');

            // Click "New Task" button
            $browser->clickLink('New Task')
                ->waitForText('Create Task')
                ->assertSee('Create Task');

            // Verify the return URL is in the form
            $browser->assertInputValue('return_url', 'http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=board');

            // Go back to tasks index
            $browser->clickLink('Back to Tasks')
                ->waitForText('Board')
                ->assertSee('Board')
                ->assertUrlIs('http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=board');
        });
    }

    /**
     * Test that multiple query parameters are preserved.
     */
    public function test_multiple_query_parameters_are_preserved(): void
    {
        $this->browse(function (Browser $browser) {
            // Navigate with multiple query parameters
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=board&view=kanban&sort=title&order=asc')
                ->waitForText('Board')
                ->assertSee('Board');

            // Click "New Task" button
            $browser->clickLink('New Task')
                ->waitForText('Create Task')
                ->assertSee('Create Task');

            // Verify the return URL includes all parameters
            $browser->assertInputValue('return_url', 'http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=board&view=kanban&sort=title&order=asc');
        });
    }

    /**
     * Test that navigation works correctly with different filter types.
     */
    public function test_navigation_works_with_different_filter_types(): void
    {
        $filters = ['top-level', 'all', 'leaf', 'board'];

        foreach ($filters as $filter) {
            $this->browse(function (Browser $browser) use ($filter) {
                // Navigate to the specific filter view
                $browser->loginAs($this->user)
                    ->visit('/dashboard/projects/' . $this->project->id . '/tasks?filter=' . $filter)
                    ->waitForText(ucfirst(str_replace('-', ' ', $filter)))
                    ->assertSee(ucfirst(str_replace('-', ' ', $filter)));

                // Click "New Task" button
                $browser->clickLink('New Task')
                    ->waitForText('Create Task')
                    ->assertSee('Create Task');

                // Verify the return URL includes the correct filter
                $browser->assertInputValue('return_url', 'http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=' . $filter);

                // Go back
                $browser->clickLink('Back to Tasks')
                    ->waitForText(ucfirst(str_replace('-', ' ', $filter)))
                    ->assertSee(ucfirst(str_replace('-', ' ', $filter)))
                    ->assertUrlIs('http://localhost/dashboard/projects/' . $this->project->id . '/tasks?filter=' . $filter);
            });
        }
    }
}

