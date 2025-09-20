<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Task, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskParentPriorityConstraintApiTest extends TestCase
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

    public function test_cannot_create_child_task_with_lower_priority_than_parent()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'Child Task',
            'description' => 'Test child task',
            'parent_id' => $parentTask->id,
            'priority' => 'low', // Lower than parent's 'high'
            'status' => 'pending',
        ]);

        $response->assertSessionHasErrors(['priority']);
        $this->assertStringContainsString('cannot have lower priority', session('errors')->first('priority'));
    }

    public function test_can_create_child_task_with_same_or_higher_priority()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        // Test same priority
        $response1 = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'Child Task Same Priority',
            'description' => 'Test child task',
            'parent_id' => $parentTask->id,
            'priority' => 'medium', // Same as parent
            'status' => 'pending',
        ]);

        $response1->assertRedirect();
        $response1->assertSessionHasNoErrors();

        // Test higher priority
        $response2 = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'Child Task Higher Priority',
            'description' => 'Test child task',
            'parent_id' => $parentTask->id,
            'priority' => 'high', // Higher than parent
            'status' => 'pending',
        ]);

        $response2->assertRedirect();
        $response2->assertSessionHasNoErrors();
    }

    public function test_cannot_update_child_task_to_lower_priority_than_parent()
    {
        $this->actingAs($this->user);

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

        $response = $this->put("/dashboard/projects/{$this->project->id}/tasks/{$childTask->id}", [
            'title' => $childTask->title,
            'description' => $childTask->description,
            'parent_id' => $parentTask->id,
            'priority' => 'medium', // Lower than parent's 'high'
            'status' => $childTask->status,
        ]);

        $response->assertSessionHasErrors(['priority']);
        $this->assertStringContainsString('cannot have lower priority', session('errors')->first('priority'));

        // Verify task wasn't updated
        $childTask->refresh();
        $this->assertEquals('high', $childTask->priority);
    }

    public function test_can_update_child_task_to_same_or_higher_priority()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => $parentTask->id,
        ]);

        // Update to higher priority
        $response = $this->put("/dashboard/projects/{$this->project->id}/tasks/{$childTask->id}", [
            'title' => $childTask->title,
            'description' => $childTask->description,
            'parent_id' => $parentTask->id,
            'priority' => 'high',
            'status' => $childTask->status,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $childTask->refresh();
        $this->assertEquals('high', $childTask->priority);
    }

    public function test_subtask_creation_validates_priority_constraints()
    {
        $this->actingAs($this->user);

        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'Parent Task',
            'description' => 'Test parent',
            'priority' => 'high',
            'status' => 'pending',
            'subtasks' => [
                [
                    'title' => 'Valid Subtask',
                    'priority' => 'high', // Same as parent - valid
                    'status' => 'pending',
                ],
                [
                    'title' => 'Invalid Subtask',
                    'priority' => 'low', // Lower than parent - invalid
                    'status' => 'pending',
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['subtasks.1.priority']);
        $this->assertStringContainsString('cannot have lower priority', session('errors')->first('subtasks.1.priority'));
    }

    public function test_changing_parent_validates_new_parent_priority_constraint()
    {
        $this->actingAs($this->user);

        // Create low priority parent
        $lowParent = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'parent_id' => null,
        ]);

        // Create high priority parent
        $highParent = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        // Create child under low parent with low priority
        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'parent_id' => $lowParent->id,
        ]);

        // Try to move child to high priority parent while keeping low priority
        $response = $this->put("/dashboard/projects/{$this->project->id}/tasks/{$childTask->id}", [
            'title' => $childTask->title,
            'description' => $childTask->description,
            'parent_id' => $highParent->id, // New parent with high priority
            'priority' => 'low', // Child priority lower than new parent
            'status' => $childTask->status,
        ]);

        $response->assertSessionHasErrors(['priority']);
        $this->assertStringContainsString('cannot have lower priority', session('errors')->first('priority'));
    }

    public function test_priority_adjustment_during_reordering_respects_parent_constraints()
    {
        // Create high priority parent
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        // Create child with high priority (valid)
        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
            'parent_id' => $parentTask->id,
        ]);

        // Create low priority sibling
        $lowSibling = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 2,
            'parent_id' => $parentTask->id,
        ]);

        // Move child to position near low priority sibling
        // Priority adjustment should be constrained by parent
        $result = $childTask->reorderTo(2, true);

        $this->assertTrue($result['success']);

        // Priority should not go below parent's priority
        $childTask->refresh();
        $this->assertGreaterThanOrEqual(
            $parentTask->getPriorityLevel(),
            $childTask->getPriorityLevel(),
            'Child priority should not be lower than parent priority'
        );
    }

    public function test_orphaned_task_has_no_priority_constraints()
    {
        $orphanTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $validation = $orphanTask->validateParentPriorityConstraint('low');

        $this->assertTrue($validation['valid']);
    }

    public function test_task_with_nonexistent_parent_has_no_constraints()
    {
        $task = new Task(['parent_id' => 99999]); // Non-existent parent

        $validation = $task->validateParentPriorityConstraint('low');

        $this->assertTrue($validation['valid']);
    }
}
