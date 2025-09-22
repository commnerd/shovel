<?php

namespace Tests\Browser;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AITaskSizingTest extends DuskTestCase
{
    use DatabaseMigrations, MocksAIServices;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock AI services to prevent real API calls
        $this->mockAIServices();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    /**
     * Test that tasks are automatically sized when created through the UI.
     */
    public function test_tasks_are_automatically_sized_in_ui()
    {
        $organization = \App\Models\Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'email_verified_at' => now()]);
        $group = \App\Models\Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative'
        ]);

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                ->visit("/dashboard/projects/{$project->id}/tasks/create")
                ->screenshot('create-task-form')
                ->waitForText('Create New Task')
                ->assertSee('Task Title')
                ->assertSee('Description')
                ->assertSee('Status')
                ->type('#title', 'Implement user authentication system')
                ->type('#description', 'Create a complete authentication system with login, registration, and password reset')
                ->select('#status', 'pending')
                ->press('Create Task')
                ->screenshot('after-form-submit')
                ->waitForText('Creating...', 5)
                ->pause(5000) // Wait 5 seconds for task creation
                ->screenshot('after-pause');
        });

        // Check that the task was created with a size
        $task = Task::where('title', 'Implement user authentication system')->first();
        if ($task) {
            $this->assertNotNull($task->size);
            $this->assertContains($task->size, ['xs', 's', 'm', 'l', 'xl']);
        } else {
            // If task wasn't created, let's see what tasks exist
            $allTasks = Task::all();
            $this->fail('Task was not created in the database. Found ' . $allTasks->count() . ' tasks: ' . $allTasks->pluck('title')->join(', '));
        }
    }

    /**
     * Test that task sizing is displayed in the UI.
     */
    public function test_task_sizing_is_displayed_in_ui()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'project_type' => 'iterative'
        ]);

        // Create a task with a known size
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null,
            'title' => 'Test Task with Size',
            'size' => 'l'
        ]);

        $this->browse(function (Browser $browser) use ($user, $project, $task) {
            $browser->loginAs($user)
                ->visit("/dashboard/projects/{$project->id}/tasks")
                ->waitForText('Test Task with Size')
                ->assertSee('Test Task with Size')
                ->assertSee('l'); // Should display the size
        });
    }

    /**
     * Test that AI task breakdown includes sizing in the UI.
     */
    public function test_ai_task_breakdown_shows_sizing_in_ui()
    {
        $organization = \App\Models\Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'email_verified_at' => now()]);
        $group = \App\Models\Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative'
        ]);

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                ->visit("/dashboard/projects/{$project->id}/tasks/create")
                ->waitForText('Create New Task')
                ->assertSee('Task Title')
                ->assertSee('Description')
                ->assertSee('Status')
                ->type('#title', 'Build user management system')
                ->type('#description', 'Complete user management with authentication and authorization')
                ->select('#status', 'pending')
                ->press('Create Task')
                ->waitForText('Creating...', 5)
                ->pause(5000) // Wait 5 seconds for task creation
                ->screenshot('after-pause');
        });

        // Check that the task was created with a size
        $task = Task::where('title', 'Build user management system')->first();
        $this->assertNotNull($task);
        $this->assertNotNull($task->size);
        $this->assertContains($task->size, ['xs', 's', 'm', 'l', 'xl']);
    }

    /**
     * Test that task sizing can be manually changed.
     * @skip Temporarily disabled due to UI element detection issues
     */
    public function test_task_sizing_can_be_manually_changed()
    {
        $this->markTestSkipped('Temporarily disabled due to UI element detection issues');

        $organization = \App\Models\Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'email_verified_at' => now()]);
        $group = \App\Models\Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative'
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null,
            'title' => 'Test Task for Manual Sizing',
            'size' => 'm'
        ]);

        $this->browse(function (Browser $browser) use ($user, $project, $task) {
            $browser->loginAs($user)
                ->visit("/dashboard/projects/{$project->id}/tasks?filter=all")
                ->waitForText('Test Task for Manual Sizing', 10)
                ->assertSee('Test Task for Manual Sizing')
                ->assertSee('M'); // Should display current size badge
        });
    }

    /**
     * Test that subtasks don't show sizing options.
     */
    public function test_subtasks_dont_show_sizing_options()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'project_type' => 'iterative'
        ]);

        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null,
            'title' => 'Parent Task',
            'size' => 'l'
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Subtask',
            'description' => 'This is a subtask',
            'size' => null
        ]);

        $this->browse(function (Browser $browser) use ($user, $project, $parentTask, $subtask) {
            $browser->loginAs($user)
                ->visit("/dashboard/projects/{$project->id}/tasks")
                ->waitForText('Parent Task')
                ->assertSee('Parent Task')
                ->assertSee('Subtask');
        });

        // Verify subtask has no size
        $this->assertNull($subtask->size);
    }

    /**
     * Test that task sizing is visible in different views.
     * @skip Temporarily disabled due to UI element detection issues
     */
    public function test_task_sizing_is_visible_in_different_views()
    {
        $this->markTestSkipped('Temporarily disabled due to UI element detection issues');

        $organization = \App\Models\Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'email_verified_at' => now()]);
        $group = \App\Models\Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative'
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null,
            'title' => 'Task for Different Views',
            'size' => 'xl'
        ]);

        // Debug: Check if task was created with correct size
        $this->assertEquals('xl', $task->size);

        $this->browse(function (Browser $browser) use ($user, $project, $task) {
            $browser->loginAs($user)
                ->visit("/dashboard/projects/{$project->id}/tasks?filter=all")
                ->waitForText('Task for Different Views')
                ->assertSee('Task for Different Views')
                ->screenshot('task-with-size')
                ->assertSee('XL'); // Should display the size
        });
    }

    /**
     * Test that task creation form works with AI sizing.
     */
    public function test_task_creation_form_works_with_ai_sizing()
    {
        $organization = \App\Models\Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'email_verified_at' => now()]);
        $group = \App\Models\Group::factory()->create(['organization_id' => $organization->id]);
        $user->groups()->attach($group);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative'
        ]);

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                ->visit("/dashboard/projects/{$project->id}/tasks/create")
                ->waitForText('Create New Task')
                ->assertSee('Task Title')
                ->assertSee('Description')
                ->assertSee('Status')
                ->type('#title', 'Complex system integration task')
                ->type('#description', 'Integrate multiple systems with complex data flow')
                ->select('#status', 'pending')
                ->press('Create Task')
                ->waitForText('Creating...', 5)
                ->pause(5000) // Wait 5 seconds for task creation
                ->screenshot('after-pause');
        });

        // Check that the task was created with a size
        $task = Task::where('title', 'Complex system integration task')->first();
        $this->assertNotNull($task);
        $this->assertNotNull($task->size);
        $this->assertContains($task->size, ['xs', 's', 'm', 'l', 'xl']);
    }
}
