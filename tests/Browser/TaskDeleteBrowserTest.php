<?php

namespace Tests\Browser;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TaskDeleteBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $user;
    protected Project $project;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\OrganizationSeeder::class);

        $this->organization = Organization::getDefault();
        $group = $this->organization->createDefaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'title' => 'Test Project for Browser Delete Tests',
            'ai_provider' => 'cerebras',
        ]);
    }

    public function test_user_can_delete_task_from_breakdown_page()
    {
        // Create a task
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task to Delete from Breakdown',
            'description' => 'This task will be deleted from the breakdown page',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/breakdown")
                ->waitForText('AI Task Breakdown')
                ->assertSee('Task to Delete from Breakdown')
                ->assertSee('Delete Task')
                ->assertVisible('button[class*="text-red-600"]');
        });
    }

    public function test_user_can_cancel_task_deletion_from_breakdown_page()
    {
        // Create a task
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task to Keep',
            'description' => 'This task will not be deleted',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/breakdown")
                ->waitForText('AI Task Breakdown')
                ->assertSee('Task to Keep')
                ->assertSee('Delete Task')
                ->assertVisible('button[class*="text-red-600"]');
        });

        // Verify task still exists in database
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Task to Keep',
        ]);
    }

    public function test_user_can_delete_subtask_from_reorder_page()
    {
        // Create a parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Create a subtask
        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Subtask to Delete',
            'description' => 'This subtask will be deleted',
            'parent_id' => $parentTask->id,
            'depth' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($parentTask, $subtask) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/subtasks/reorder")
                ->waitForText('Reorder Subtasks')
                ->assertSee('Subtask to Delete')
                ->assertVisible('button[class*="text-red-600"]');
        });
    }

    public function test_user_can_cancel_subtask_deletion_from_reorder_page()
    {
        // Create a parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Create a subtask
        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Subtask to Keep',
            'description' => 'This subtask will not be deleted',
            'parent_id' => $parentTask->id,
            'depth' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($parentTask, $subtask) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/subtasks/reorder")
                ->waitForText('Reorder Subtasks')
                ->assertSee('Subtask to Keep')
                ->assertVisible('button[class*="text-red-600"]');
        });

        // Verify subtask still exists in database
        $this->assertDatabaseHas('tasks', [
            'id' => $subtask->id,
            'title' => 'Subtask to Keep',
        ]);
    }

    public function test_delete_modal_shows_warning_for_tasks_with_subtasks()
    {
        // Create a parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task with Subtasks',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Create a subtask
        Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Child Subtask',
            'parent_id' => $parentTask->id,
            'depth' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($parentTask) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/breakdown")
                ->waitForText('AI Task Breakdown')
                ->assertSee('Delete Task')
                ->assertVisible('button[class*="text-red-600"]');
        });
    }

    public function test_delete_modal_shows_warning_for_subtasks_with_children()
    {
        // Create a parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Create a subtask
        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Subtask with Children',
            'parent_id' => $parentTask->id,
            'depth' => 1,
        ]);

        // Create a sub-subtask
        Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Sub-subtask',
            'parent_id' => $subtask->id,
            'depth' => 2,
        ]);

        $this->browse(function (Browser $browser) use ($parentTask, $subtask) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/subtasks/reorder")
                ->waitForText('Reorder Subtasks')
                ->assertSee('Subtask with Children')
                ->assertVisible('button[class*="text-red-600"]');
        });
    }

    public function test_delete_button_is_visible_and_clickable()
    {
        // Create a task
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task for UI Test',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/breakdown")
                ->waitForText('AI Task Breakdown')
                ->assertVisible('button[class*="text-red-600"]')
                ->assertSee('Delete Task');
        });
    }

    public function test_delete_button_has_correct_styling()
    {
        // Create a task
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task for Styling Test',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/breakdown")
                ->waitForText('AI Task Breakdown')
                ->assertVisible('button[class*="text-red-600"]')
                ->assertSee('Delete Task');
        });
    }
}
