<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Task, Project, User, Organization};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskPriorityAdjustmentApiTest extends TestCase
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

    public function test_api_promotes_task_priority_when_confirmed()
    {
        $this->actingAs($this->user);

        // Create tasks: low, high, high
        $lowTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        // First request - should require confirmation
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/{$lowTask->id}/reorder", [
            'new_position' => 3,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => false,
                     'requires_confirmation' => true,
                 ]);

        // Second request - with confirmation
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/{$lowTask->id}/reorder", [
            'new_position' => 3,
            'confirmed' => true,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'priority_changed' => true,
                     'old_priority' => 'low',
                     'new_priority' => 'high',
                 ]);

        $response->assertJsonFragment([
            'message' => 'Task reordered successfully. Priority changed from low to high.',
        ]);

        // Verify database changes
        $lowTask->refresh();
        $this->assertEquals('high', $lowTask->priority);
        $this->assertEquals(3, $lowTask->sort_order);
    }

    public function test_api_demotes_task_priority_when_confirmed()
    {
        $this->actingAs($this->user);

        // Create tasks: high, low, low
        $highTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        // Move high task to low priority area with confirmation
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/{$highTask->id}/reorder", [
            'new_position' => 3,
            'confirmed' => true,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'priority_changed' => true,
                     'old_priority' => 'high',
                     'new_priority' => 'low',
                 ]);

        // Verify database changes
        $highTask->refresh();
        $this->assertEquals('low', $highTask->priority);
        $this->assertEquals(3, $highTask->sort_order);
    }

    public function test_api_returns_no_priority_change_for_same_priority_neighbors()
    {
        $this->actingAs($this->user);

        // Create tasks: medium, medium, medium
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/{$task1->id}/reorder", [
            'new_position' => 3,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'priority_changed' => false,
                     'old_priority' => null,
                     'new_priority' => null,
                 ]);

        $response->assertJsonFragment([
            'message' => 'Task reordered successfully.',
        ]);

        // Verify priority unchanged
        $task1->refresh();
        $this->assertEquals('medium', $task1->priority);
    }

    public function test_api_handles_priority_adjustment_with_complex_scenarios()
    {
        $this->actingAs($this->user);

        // Create complex scenario: low, high, medium, high, low
        $tasks = [];
        $priorities = ['low', 'high', 'medium', 'high', 'low'];

        foreach ($priorities as $index => $priority) {
            $tasks[] = Task::factory()->create([
                'project_id' => $this->project->id,
                'priority' => $priority,
                'sort_order' => $index + 1,
                'parent_id' => null,
            ]);
        }

        // Move first low task (position 1) to position 4 (between high and low)
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/{$tasks[0]->id}/reorder", [
            'new_position' => 4,
            'confirmed' => true,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'priority_changed' => true,
                     'old_priority' => 'low',
                     'new_priority' => 'high', // Should adopt highest neighbor priority
                 ]);
    }

    public function test_api_requires_authentication()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 1,
        ]);

        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/reorder", [
            'new_position' => 2,
            'confirmed' => true,
        ]);

        $response->assertStatus(401); // Unauthorized
    }

    public function test_api_prevents_unauthorized_project_access()
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);

        $task = Task::factory()->create([
            'project_id' => $otherProject->id,
            'priority' => 'low',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson("/dashboard/projects/{$otherProject->id}/tasks/{$task->id}/reorder", [
            'new_position' => 2,
            'confirmed' => true,
        ]);

        $response->assertStatus(403); // Forbidden
    }

    public function test_api_validates_request_parameters()
    {
        $this->actingAs($this->user);

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 1,
        ]);

        // Test missing new_position
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/reorder", [
            'confirmed' => true,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['new_position']);

        // Test invalid new_position
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/reorder", [
            'new_position' => 0, // Invalid: must be >= 1
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['new_position']);
    }
}
