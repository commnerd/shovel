<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskSizingInertiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    /**
     * Test that updating task size returns an Inertia response instead of JSON.
     */
    public function test_updating_task_size_returns_inertia_response()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'project_type' => 'iterative'
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null, // Top-level task
            'title' => 'Test Task for Sizing',
            'size' => null
        ]);

        $response = $this->actingAs($user)
            ->patch("/dashboard/tasks/{$task->id}", [
                'size' => 'l'
            ]);

        // Should redirect back (Inertia response) instead of returning JSON
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Task updated successfully!');
    }

    /**
     * Test that updating story points returns an Inertia response instead of JSON.
     */
    public function test_updating_story_points_returns_inertia_response()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'project_type' => 'iterative'
        ]);

        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null, // Top-level task
            'title' => 'Parent Task',
            'size' => 'l'
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id, // Subtask
            'title' => 'Subtask',
            'current_story_points' => null
        ]);

        $response = $this->actingAs($user)
            ->patch("/dashboard/tasks/{$subtask->id}", [
                'current_story_points' => 5
            ]);

        // Should redirect back (Inertia response) instead of returning JSON
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Task updated successfully!');
    }

    /**
     * Test that validation errors return Inertia responses.
     */
    public function test_validation_errors_return_inertia_responses()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'project_type' => 'iterative'
        ]);

        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null, // Top-level task
            'title' => 'Parent Task',
            'size' => 'l'
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id, // Subtask
            'title' => 'Subtask',
            'current_story_points' => null
        ]);

        // Try to set size on subtask (should fail)
        $response = $this->actingAs($user)
            ->patch("/dashboard/tasks/{$subtask->id}", [
                'size' => 'l'
            ]);

        // Should redirect back with validation errors
        $response->assertRedirect();
        $response->assertSessionHasErrors(['size' => 'Only top-level tasks can have a T-shirt size.']);
    }

    /**
     * Test that story points validation errors return Inertia responses.
     */
    public function test_story_points_validation_errors_return_inertia_responses()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'project_type' => 'iterative'
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null, // Top-level task
            'title' => 'Top-level Task',
            'size' => 'l'
        ]);

        // Try to set story points on top-level task (should fail)
        $response = $this->actingAs($user)
            ->patch("/dashboard/tasks/{$task->id}", [
                'current_story_points' => 5
            ]);

        // Should redirect back with validation errors
        $response->assertRedirect();
        $response->assertSessionHasErrors(['current_story_points' => 'Only subtasks can have story points.']);
    }

    /**
     * Test that the task is actually updated in the database.
     */
    public function test_task_size_is_updated_in_database()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'project_type' => 'iterative'
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null, // Top-level task
            'title' => 'Test Task for Sizing',
            'size' => null
        ]);

        $this->actingAs($user)
            ->patch("/dashboard/tasks/{$task->id}", [
                'size' => 'l'
            ]);

        // Check that the task was updated in the database
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'size' => 'l'
        ]);
    }

    /**
     * Test that story points are updated in the database.
     */
    public function test_story_points_are_updated_in_database()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'project_type' => 'iterative'
        ]);

        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => null, // Top-level task
            'title' => 'Parent Task',
            'size' => 'l'
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id, // Subtask
            'title' => 'Subtask',
            'current_story_points' => null
        ]);

        $this->actingAs($user)
            ->patch("/dashboard/tasks/{$subtask->id}", [
                'current_story_points' => 8
            ]);

        // Check that the subtask was updated in the database
        $this->assertDatabaseHas('tasks', [
            'id' => $subtask->id,
            'current_story_points' => 8
        ]);
    }
}
