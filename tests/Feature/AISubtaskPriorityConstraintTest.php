<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Task, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

class AISubtaskPriorityConstraintTest extends TestCase
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

    public function test_ai_breakdown_includes_parent_task_context()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'High Priority Parent Task',
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Task to break down',
            'description' => 'Test task description',
            'parent_task_id' => $parentTask->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Should include subtasks in response
        $response->assertJsonStructure([
            'success',
            'subtasks',
            'notes',
            'summary',
            'problems',
            'suggestions',
            'priority_adjustments',
        ]);
    }

    public function test_ai_breakdown_validates_parent_task_belongs_to_project()
    {
        $this->actingAs($this->user);

        // Create task in different project
        $otherProject = Project::factory()->create(['user_id' => $this->user->id]);
        $taskInOtherProject = Task::factory()->create([
            'project_id' => $otherProject->id,
            'priority' => 'high',
        ]);

        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Task to break down',
            'description' => 'Test task description',
            'parent_task_id' => $taskInOtherProject->id,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'error' => 'Invalid parent task.',
        ]);
    }

    public function test_ai_breakdown_without_parent_task_works_normally()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Task to break down',
            'description' => 'Test task description',
            // No parent_task_id provided
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Should not include priority adjustments when no parent
        $data = $response->json();
        $this->assertNull($data['priority_adjustments']);
    }

    public function test_ai_breakdown_validates_request_parameters()
    {
        $this->actingAs($this->user);

        // Test missing title
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'description' => 'Test description',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);

        // Test invalid parent_task_id
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Valid title',
            'parent_task_id' => 99999, // Non-existent task
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_task_id']);
    }

    public function test_ai_breakdown_requires_authentication()
    {
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Task to break down',
        ]);

        $response->assertStatus(401);
    }

    public function test_ai_breakdown_requires_project_ownership()
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user);

        $response = $this->postJson("/dashboard/projects/{$otherProject->id}/tasks/breakdown", [
            'title' => 'Task to break down',
        ]);

        $response->assertStatus(403);
    }

    public function test_ai_breakdown_handles_user_feedback()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
        ]);

        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Task to break down',
            'description' => 'Test description',
            'user_feedback' => 'Please focus on security aspects',
            'parent_task_id' => $parentTask->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_breakdown_page_accessibility()
    {
        $this->actingAs($this->user);

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/breakdown");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Breakdown')
                 ->has('project')
                 ->has('task')
                 ->where('task.priority', 'medium')
        );
    }
}
