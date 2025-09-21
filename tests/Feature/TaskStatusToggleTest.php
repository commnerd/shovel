<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Project, Task};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskStatusToggleTest extends TestCase
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

    public function test_can_toggle_task_status_to_completed()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Task marked as completed!',
            'task' => [
                'id' => $task->id,
                'status' => 'completed',
                'title' => $task->title,
            ],
        ]);

        // Verify database was updated
        $task->refresh();
        $this->assertEquals('completed', $task->status);
    }

    public function test_can_toggle_task_status_to_pending()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'pending',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Task marked as pending!',
            'task' => [
                'id' => $task->id,
                'status' => 'pending',
                'title' => $task->title,
            ],
        ]);

        // Verify database was updated
        $task->refresh();
        $this->assertEquals('pending', $task->status);
    }

    public function test_can_set_task_status_to_in_progress()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Task marked as in_progress!',
        ]);

        // Verify database was updated
        $task->refresh();
        $this->assertEquals('in_progress', $task->status);
    }

    public function test_status_update_requires_authentication()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(401); // Unauthorized for API endpoint
    }

    public function test_status_update_requires_project_ownership()
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);

        $task = Task::factory()->create([
            'project_id' => $otherProject->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$otherProject->id}/tasks/{$task->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(403);
    }

    public function test_status_update_validates_task_belongs_to_project()
    {
        $otherProject = Project::factory()->create(['user_id' => $this->user->id]);

        $task = Task::factory()->create([
            'project_id' => $otherProject->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(404);
    }

    public function test_status_update_validation_rules()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        // Test required status
        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');

        // Test invalid status
        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_status_toggle_works_for_subtasks()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$subtask->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'task' => [
                'id' => $subtask->id,
                'status' => 'completed',
            ],
        ]);

        // Verify database was updated
        $subtask->refresh();
        $this->assertEquals('completed', $subtask->status);
    }

    public function test_multiple_rapid_status_toggles()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        // Toggle to completed
        $response1 = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'completed',
            ]);

        $response1->assertStatus(200);
        $response1->assertJson(['success' => true]);

        // Toggle back to pending
        $response2 = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/status", [
                'status' => 'pending',
            ]);

        $response2->assertStatus(200);
        $response2->assertJson(['success' => true]);

        // Verify final state
        $task->refresh();
        $this->assertEquals('pending', $task->status);
    }
}
