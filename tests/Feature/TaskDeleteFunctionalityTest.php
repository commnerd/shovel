<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDeleteFunctionalityTest extends TestCase
{
    use RefreshDatabase;

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
            'title' => 'Test Project for Task Deletion',
            'ai_provider' => 'cerebras',
        ]);
    }

    public function test_user_can_delete_task_from_breakdown_page()
    {
        // Create a task
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task to Delete',
            'description' => 'This task will be deleted',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Make DELETE request to destroy the task
        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$this->project->id}/tasks/{$task->id}");

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");
        $response->assertSessionHas('message', 'Task deleted successfully!');

        // Verify task is deleted from database
        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
            'title' => 'Task to Delete',
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

        // Make DELETE request to destroy the subtask
        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$this->project->id}/tasks/{$subtask->id}");

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");
        $response->assertSessionHas('message', 'Task deleted successfully!');

        // Verify subtask is deleted from database
        $this->assertDatabaseMissing('tasks', [
            'id' => $subtask->id,
            'title' => 'Subtask to Delete',
        ]);

        // Verify parent task still exists
        $this->assertDatabaseHas('tasks', [
            'id' => $parentTask->id,
            'title' => 'Parent Task',
        ]);
    }

    public function test_deleting_task_with_subtasks_deletes_all_subtasks()
    {
        // Create a parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task with Subtasks',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Create subtasks
        $subtask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Subtask 1',
            'parent_id' => $parentTask->id,
            'depth' => 1,
        ]);

        $subtask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Subtask 2',
            'parent_id' => $parentTask->id,
            'depth' => 1,
        ]);

        // Create a sub-subtask
        $subSubtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Sub-subtask',
            'parent_id' => $subtask1->id,
            'depth' => 2,
        ]);

        // Make DELETE request to destroy the parent task
        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}");

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");
        $response->assertSessionHas('message', 'Task deleted successfully!');

        // Verify all tasks are deleted from database
        $this->assertDatabaseMissing('tasks', ['id' => $parentTask->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $subtask1->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $subtask2->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $subSubtask->id]);
    }

    public function test_user_cannot_delete_task_from_different_project()
    {
        // Create another user and project
        $otherUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $otherUser->joinGroup($this->organization->createDefaultGroup());

        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->organization->createDefaultGroup()->id,
            'title' => 'Other Project',
        ]);

        $otherTask = Task::factory()->create([
            'project_id' => $otherProject->id,
            'title' => 'Other Task',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Try to delete task from different project
        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$otherProject->id}/tasks/{$otherTask->id}");

        $response->assertStatus(403);
        $response->assertSee('Unauthorized access to this project.');

        // Verify task still exists
        $this->assertDatabaseHas('tasks', [
            'id' => $otherTask->id,
            'title' => 'Other Task',
        ]);
    }

    public function test_user_cannot_delete_nonexistent_task()
    {
        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$this->project->id}/tasks/99999");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_delete_task()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task to Delete',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $response = $this->delete("/dashboard/projects/{$this->project->id}/tasks/{$task->id}");

        $response->assertRedirect('/login');

        // Verify task still exists
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Task to Delete',
        ]);
    }

    public function test_task_deletion_preserves_other_tasks_in_project()
    {
        // Create multiple tasks
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task 1',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task 2',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task 3',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Delete task2
        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$this->project->id}/tasks/{$task2->id}");

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");
        $response->assertSessionHas('message', 'Task deleted successfully!');

        // Verify task2 is deleted
        $this->assertDatabaseMissing('tasks', ['id' => $task2->id]);

        // Verify other tasks still exist
        $this->assertDatabaseHas('tasks', ['id' => $task1->id, 'title' => 'Task 1']);
        $this->assertDatabaseHas('tasks', ['id' => $task3->id, 'title' => 'Task 3']);
    }

    public function test_task_deletion_with_story_points()
    {
        // Create a task with story points (iterative project)
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task with Story Points',
            'parent_id' => null,
            'depth' => 0,
            'size' => 'm',
            'initial_story_points' => 8,
            'current_story_points' => 8,
            'story_points_change_count' => 0,
        ]);

        // Make DELETE request
        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$this->project->id}/tasks/{$task->id}");

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");
        $response->assertSessionHas('message', 'Task deleted successfully!');

        // Verify task is deleted
        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
            'title' => 'Task with Story Points',
        ]);
    }

    public function test_task_deletion_with_due_date()
    {
        // Create a task with due date
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task with Due Date',
            'parent_id' => null,
            'depth' => 0,
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        // Make DELETE request
        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$this->project->id}/tasks/{$task->id}");

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");
        $response->assertSessionHas('message', 'Task deleted successfully!');

        // Verify task is deleted
        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
            'title' => 'Task with Due Date',
        ]);
    }

    public function test_task_deletion_updates_project_task_count()
    {
        // Create multiple tasks
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task 1',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task 2',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Verify initial task count
        $this->assertEquals(2, $this->project->tasks()->count());

        // Delete one task
        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$this->project->id}/tasks/{$task1->id}");

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        // Verify task count is updated
        $this->assertEquals(1, $this->project->fresh()->tasks()->count());
    }
}

