<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Task, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskFormPriorityConstraintTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_task_create_form_provides_parent_priority_data()
    {
        $this->actingAs($this->user);

        // Create a high priority parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/create");

        $response->assertStatus(200);

        // Check that parent tasks include priority information
        $response->assertInertia(fn ($page) =>
            $page->has('parentTasks.0.priority')
                 ->has('parentTasks.0.priority_level')
                 ->where('parentTasks.0.priority', 'high')
                 ->where('parentTasks.0.priority_level', 3)
        );
    }

    public function test_subtask_create_form_provides_parent_priority_data()
    {
        $this->actingAs($this->user);

        // Create a medium priority parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/subtasks/create");

        $response->assertStatus(200);

        // Check that parentTask includes priority information
        $response->assertInertia(fn ($page) =>
            $page->has('parentTask.priority')
                 ->has('parentTask.priority_level')
                 ->where('parentTask.priority', 'medium')
                 ->where('parentTask.priority_level', 2)
        );
    }

    public function test_task_edit_form_provides_parent_priority_data()
    {
        $this->actingAs($this->user);

        // Create parent and child tasks
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => $parentTask->id,
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/{$childTask->id}/edit");

        $response->assertStatus(200);

        // Check that parent tasks include priority information
        $response->assertInertia(fn ($page) =>
            $page->has('parentTasks.0.priority')
                 ->has('parentTasks.0.priority_level')
                 ->where('parentTasks.0.priority', 'high')
                 ->where('parentTasks.0.priority_level', 3)
        );
    }

    public function test_task_forms_provide_correct_priority_levels()
    {
        $this->actingAs($this->user);

        // Create tasks with different priorities
        $lowTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'parent_id' => null,
        ]);

        $mediumTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $highTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/create");

        $response->assertStatus(200);

        // Verify priority levels are correctly calculated
        $response->assertInertia(fn ($page) =>
            $page->where('parentTasks.0.priority_level', 1) // low
                 ->where('parentTasks.1.priority_level', 2) // medium
                 ->where('parentTasks.2.priority_level', 3) // high
        );
    }

    public function test_task_creation_form_accessibility()
    {
        $this->actingAs($this->user);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/create");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('project')
                 ->has('parentTasks')
                 ->component('Projects/Tasks/Create')
        );
    }

    public function test_subtask_creation_form_accessibility()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/subtasks/create");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('project')
                 ->has('parentTask')
                 ->has('parentTasks')
                 ->component('Projects/Tasks/Create')
        );
    }
}
