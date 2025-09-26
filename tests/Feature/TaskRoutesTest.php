<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Test Task',
            'description' => 'Test Description',
        ]);
        $this->actingAs($this->user);
    }

    public function test_task_update_route_exists()
    {
        $response = $this->put(route('projects.tasks.update', [$this->project->id, $this->task->id]), [
            'title' => 'Updated Task Title',
            'description' => 'Updated Description',
            'status' => 'pending',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'title' => 'Updated Task Title',
            'description' => 'Updated Description',
        ]);
    }

    public function test_task_delete_route_exists()
    {
        $response = $this->delete(route('projects.tasks.destroy', [$this->project->id, $this->task->id]));

        $response->assertRedirect(route('projects.tasks.index', $this->project->id));
        $response->assertSessionHas('message', 'Task deleted successfully!');
        $this->assertDatabaseMissing('tasks', ['id' => $this->task->id]);
    }

    public function test_task_status_update_route_exists()
    {
        $response = $this->patch(route('projects.tasks.update-status', [$this->project->id, $this->task->id]), [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Task marked as in_progress!',
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_task_sizing_update_route_exists()
    {
        $response = $this->patch(route('tasks.update', $this->task->id), [
            'size' => 'm',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'size' => 'm',
        ]);
    }

    public function test_task_story_points_update_route_exists()
    {
        // Create a subtask for story points testing
        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $this->task->id,
            'title' => 'Subtask',
            'description' => 'Subtask Description',
        ]);

        $response = $this->patch(route('tasks.update', $subtask->id), [
            'current_story_points' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'id' => $subtask->id,
            'current_story_points' => 5,
        ]);
    }

    public function test_task_reorder_route_exists()
    {
        $response = $this->post(route('projects.tasks.reorder', [$this->project->id, $this->task->id]), [
            'new_position' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_todays_tasks_status_update_route_exists()
    {
        $response = $this->patch(route('todays-tasks.tasks.update-status', $this->task->id), [
            'status' => 'completed',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Task status updated successfully');
        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'status' => 'completed',
        ]);
    }

    public function test_task_routes_require_authentication()
    {
        auth()->logout();

        $this->put(route('projects.tasks.update', [$this->project->id, $this->task->id]), [])
            ->assertRedirect('/login');

        $this->delete(route('projects.tasks.destroy', [$this->project->id, $this->task->id]))
            ->assertRedirect('/login');

        $this->patch(route('projects.tasks.update-status', [$this->project->id, $this->task->id]), [])
            ->assertRedirect('/login');

        $this->patch(route('tasks.update', $this->task->id), [])
            ->assertRedirect('/login');

        $this->post(route('projects.tasks.reorder', [$this->project->id, $this->task->id]), [])
            ->assertRedirect('/login');

        $this->patch(route('todays-tasks.tasks.update-status', $this->task->id), [])
            ->assertRedirect('/login');
    }

    public function test_task_routes_require_project_ownership()
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        $otherTask = Task::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($otherUser);

        $this->put(route('projects.tasks.update', [$this->project->id, $this->task->id]), [])
            ->assertStatus(403);

        $this->delete(route('projects.tasks.destroy', [$this->project->id, $this->task->id]))
            ->assertStatus(403);

        $this->patch(route('projects.tasks.update-status', [$this->project->id, $this->task->id]), [])
            ->assertStatus(403);

        $this->post(route('projects.tasks.reorder', [$this->project->id, $this->task->id]), [])
            ->assertStatus(403);
    }

    public function test_task_routes_validate_task_belongs_to_project()
    {
        $otherProject = Project::factory()->create(['user_id' => $this->user->id]);
        $otherTask = Task::factory()->create(['project_id' => $otherProject->id]);

        $this->put(route('projects.tasks.update', [$this->project->id, $otherTask->id]), [])
            ->assertStatus(404);

        $this->delete(route('projects.tasks.destroy', [$this->project->id, $otherTask->id]))
            ->assertStatus(404);

        $this->patch(route('projects.tasks.update-status', [$this->project->id, $otherTask->id]), [])
            ->assertStatus(404);

        $this->post(route('projects.tasks.reorder', [$this->project->id, $otherTask->id]), [])
            ->assertStatus(404);
    }

    public function test_task_sizing_route_works_without_project_context()
    {
        // The tasks.update route should work for any task the user owns
        $response = $this->patch(route('tasks.update', $this->task->id), [
            'size' => 'l',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'size' => 'l',
        ]);
    }

    public function test_task_sizing_route_requires_task_ownership()
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        $otherTask = Task::factory()->create(['project_id' => $otherProject->id]);

        $this->patch(route('tasks.update', $otherTask->id), [
            'size' => 'm',
            'current_story_points' => 5,
        ])->assertStatus(403);
    }

    public function test_all_task_routes_are_registered()
    {
        $this->assertTrue(route('projects.tasks.update', [1, 1]) !== null);
        $this->assertTrue(route('projects.tasks.destroy', [1, 1]) !== null);
        $this->assertTrue(route('projects.tasks.update-status', [1, 1]) !== null);
        $this->assertTrue(route('tasks.update', 1) !== null);
        $this->assertTrue(route('projects.tasks.reorder', [1, 1]) !== null);
        $this->assertTrue(route('todays-tasks.tasks.update-status', 1) !== null);
    }

    public function test_route_names_are_correct()
    {
        // Test that routes exist and return valid URLs
        $this->assertStringContainsString('dashboard/projects/1/tasks/1', route('projects.tasks.update', [1, 1]));
        $this->assertStringContainsString('dashboard/projects/1/tasks/1', route('projects.tasks.destroy', [1, 1]));
        $this->assertStringContainsString('dashboard/projects/1/tasks/1', route('projects.tasks.update-status', [1, 1]));
        $this->assertStringContainsString('dashboard/tasks/1', route('tasks.update', 1));
        $this->assertStringContainsString('dashboard/projects/1/tasks/1', route('projects.tasks.reorder', [1, 1]));
        $this->assertStringContainsString('dashboard/todays-tasks/tasks/1', route('todays-tasks.tasks.update-status', 1));
    }
}
