<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Project, Task};
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubtaskParentContextRestrictionTest extends TestCase
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

    public function test_subtask_cannot_be_moved_outside_parent_context()
    {
        // Create a parent task with subtasks
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $subtask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'priority' => 'medium',
            'sort_order' => 2,
        ]);

        $subtask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'priority' => 'medium',
            'sort_order' => 3,
        ]);

        // Create another top-level task
        $otherTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 4,
        ]);

        // Try to move subtask1 to position 4 (after the other task, outside parent context)
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$subtask1->id}/reorder", [
                'new_position' => 4,
                'confirmed' => false,
                'filter' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'message' => 'Subtasks cannot be moved outside their parent task context. Use the edit form to change the parent.',
        ]);

        // Verify the subtask position didn't change
        $subtask1->refresh();
        $this->assertEquals(2, $subtask1->sort_order);
    }

    public function test_subtask_can_be_reordered_within_parent_context()
    {
        // Create a parent task with subtasks
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $subtask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'priority' => 'medium',
            'sort_order' => 2,
        ]);

        $subtask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'priority' => 'medium',
            'sort_order' => 3,
        ]);

        // Move subtask1 to position 3 (swap with subtask2, within parent context)
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$subtask1->id}/reorder", [
                'new_position' => 3,
                'confirmed' => false,
                'filter' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verify the subtask position changed
        $subtask1->refresh();
        $this->assertEquals(3, $subtask1->sort_order);
    }

    public function test_top_level_task_can_be_moved_freely()
    {
        // Create multiple top-level tasks
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

        // Move task1 to position 3 (should work fine for top-level tasks)
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$task1->id}/reorder", [
                'new_position' => 3,
                'confirmed' => false,
                'filter' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verify the task position changed
        $task1->refresh();
        $this->assertEquals(3, $task1->sort_order);
    }

    public function test_subtask_cannot_be_moved_before_parent()
    {
        // Create a parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 2,
        ]);

        // Create a top-level task before the parent
        $topLevelTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'priority' => 'medium',
            'sort_order' => 3,
        ]);

        // Try to move subtask to position 1 (before its parent)
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$subtask->id}/reorder", [
                'new_position' => 1,
                'confirmed' => false,
                'filter' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'message' => 'Subtasks cannot be moved outside their parent task context. Use the edit form to change the parent.',
        ]);

        // Verify the subtask position didn't change
        $subtask->refresh();
        $this->assertEquals(3, $subtask->sort_order);
    }

    public function test_subtask_cannot_be_moved_into_different_parent_subtasks()
    {
        // Create two parent tasks with their own subtasks
        $parent1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $parent1Subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent1->id,
            'priority' => 'medium',
            'sort_order' => 2,
        ]);

        $parent2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 3,
        ]);

        $parent2Subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent2->id,
            'priority' => 'medium',
            'sort_order' => 4,
        ]);

        // Try to move parent1's subtask into parent2's subtask area
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$parent1Subtask->id}/reorder", [
                'new_position' => 4, // Position of parent2's subtask
                'confirmed' => false,
                'filter' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'message' => 'Subtasks cannot be moved outside their parent task context. Use the edit form to change the parent.',
        ]);

        // Verify the subtask position didn't change
        $parent1Subtask->refresh();
        $this->assertEquals(2, $parent1Subtask->sort_order);
    }

    public function test_validation_error_message_is_clear_and_helpful()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'priority' => 'medium',
            'sort_order' => 2,
        ]);

        $otherTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 3,
        ]);

        // Try to move subtask after the other task
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$subtask->id}/reorder", [
                'new_position' => 3,
                'confirmed' => false,
                'filter' => 'all',
            ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Subtasks cannot be moved outside their parent task context', $responseData['message']);
        $this->assertStringContainsString('Use the edit form to change the parent', $responseData['message']);
    }
}
