<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Project, Task};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskReorderPriorityUITest extends TestCase
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

    public function test_reorder_api_returns_priority_change_information()
    {
        // Create tasks with different priorities to trigger priority adjustment
        $highPriorityTask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
        ]);

        $lowPriorityTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 2,
        ]);

        $highPriorityTask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 3,
        ]);

        // Move the low priority task to position 1 (between high priority tasks, should trigger promotion)
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$lowPriorityTask->id}/reorder", [
                'new_position' => 1,
                'confirmed' => true, // Confirm the priority change
                'filter' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'priority_changed' => true,
            'old_priority' => 'low',
            'new_priority' => 'high',
        ]);

        // Verify the response includes updated tasks array
        $responseData = $response->json();
        $this->assertArrayHasKey('tasks', $responseData);
        $this->assertIsArray($responseData['tasks']);
        $this->assertNotEmpty($responseData['tasks']);

        // Verify the moved task has the new priority in the returned data
        $updatedTask = collect($responseData['tasks'])->firstWhere('id', $lowPriorityTask->id);
        $this->assertNotNull($updatedTask);
        $this->assertEquals('high', $updatedTask['priority']);
    }

    public function test_reorder_api_returns_no_priority_change_for_same_priority_neighbors()
    {
        // Create tasks with same priority
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 2,
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 3,
        ]);

        // Move task2 to position 1 (no priority change expected)
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$task2->id}/reorder", [
                'new_position' => 1,
                'confirmed' => false,
                'filter' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'priority_changed' => false,
        ]);

        // Verify old_priority and new_priority are null when no change
        $responseData = $response->json();
        $this->assertNull($responseData['old_priority']);
        $this->assertNull($responseData['new_priority']);
    }

    public function test_reorder_confirmation_dialog_includes_priority_information()
    {
        // Create tasks that will require confirmation
        $highPriorityTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
        ]);

        $lowPriorityTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 2,
        ]);

        // Try to move high priority task to low priority area (should require confirmation)
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$highPriorityTask->id}/reorder", [
                'new_position' => 2,
                'confirmed' => false,
                'filter' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'requires_confirmation' => true,
        ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('confirmation_data', $responseData);
        $this->assertArrayHasKey('task_priority', $responseData['confirmation_data']);
        $this->assertArrayHasKey('neighbor_priorities', $responseData['confirmation_data']);
        $this->assertEquals('high', $responseData['confirmation_data']['task_priority']);
    }

    public function test_task_list_includes_all_priority_related_fields()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->has('tasks.0.priority')
                 ->where('tasks.0.priority', 'medium')
                 ->has('tasks.0.id')
                 ->where('tasks.0.id', $task->id)
        );
    }
}
