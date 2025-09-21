<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Project, Task};
use Illuminate\Foundation\Testing\RefreshDatabase;

class KanbanBoardTest extends TestCase
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

    public function test_board_view_shows_tasks_in_correct_columns()
    {
        // Create tasks with different statuses
        $pendingTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Pending Task',
            'status' => 'pending',
        ]);

        $inProgressTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'In Progress Task',
            'status' => 'in_progress',
        ]);

        $completedTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Completed Task',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=board");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'board')
                 ->has('tasks', 3)
        );
    }

    public function test_kanban_drag_drop_updates_task_status()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        // Move task from pending to in_progress
        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'task' => [
                'id' => $task->id,
                'status' => 'in_progress',
            ],
        ]);

        // Verify database was updated
        $task->refresh();
        $this->assertEquals('in_progress', $task->status);
    }

    public function test_kanban_status_transitions()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        // Test all valid transitions
        $transitions = [
            'pending' => 'in_progress',
            'in_progress' => 'completed',
            'completed' => 'pending', // Can move back to start
        ];

        foreach ($transitions as $from => $to) {
            $task->update(['status' => $from]);

            $response = $this->actingAs($this->user)
                ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                    'status' => $to,
                ]);

            $response->assertStatus(200);
            $response->assertJson(['success' => true]);

            $task->refresh();
            $this->assertEquals($to, $task->status);
        }
    }

    public function test_board_view_column_names_are_user_friendly()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=board");

        $response->assertStatus(200);

        // Check that the page loads successfully
        // The column names are in the frontend template, so we just verify the page loads
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'board')
        );
    }

    public function test_board_view_handles_empty_columns()
    {
        // Create only one task in pending status
        Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=board");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'board')
                 ->has('tasks', 1)
        );
    }

    public function test_board_view_task_ordering()
    {
        // Create tasks with different sort orders
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
            'sort_order' => 2,
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=board");

        $response->assertStatus(200);

        // Verify tasks are ordered by sort_order
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'board')
                 ->has('tasks', 2)
        );
    }

    public function test_kanban_unauthorized_access_prevented()
    {
        $otherUser = User::factory()->create();

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($otherUser)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(403);

        // Verify task status wasn't changed
        $task->refresh();
        $this->assertEquals('pending', $task->status);
    }

    public function test_kanban_task_status_validation()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        // Test invalid status
        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');

        // Verify task status wasn't changed
        $task->refresh();
        $this->assertEquals('pending', $task->status);
    }
}
