<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDueDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_can_be_created_with_due_date()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $futureDate = now()->addDays(30)->format('Y-m-d');

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks", [
            'title' => 'Test Task',
            'description' => 'Test description',
            'priority' => 'high',
            'status' => 'pending',
            'due_date' => $futureDate,
        ]);


        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Test Task',
        ]);

        // Check that the due_date is set correctly (allowing for datetime format)
        $task = \App\Models\Task::where('title', 'Test Task')->first();
        $this->assertNotNull($task->due_date);
        $this->assertEquals($futureDate, $task->due_date->format('Y-m-d'));
    }

    public function test_task_can_be_created_without_due_date()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks", [
            'title' => 'Test Task',
            'description' => 'Test description',
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Test Task',
            'due_date' => null,
        ]);
    }

    public function test_task_due_date_validation_requires_future_date()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks", [
            'title' => 'Test Task',
            'description' => 'Test description',
            'priority' => 'high',
            'status' => 'pending',
            'due_date' => now()->subDays(1)->format('Y-m-d'), // Past date
        ]);

        $response->assertSessionHasErrors(['due_date']);
    }

    public function test_task_due_date_can_be_updated()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => now()->addDays(30),
        ]);

        $newDueDate = now()->addDays(45)->format('Y-m-d');

        $response = $this->actingAs($user)->put("/dashboard/projects/{$project->id}/tasks/{$task->id}", [
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'due_date' => $newDueDate,
        ]);

        $response->assertRedirect();
        $task->refresh();
        $this->assertEquals($newDueDate, $task->due_date->format('Y-m-d'));
    }

    public function test_task_due_date_can_be_cleared()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)->put("/dashboard/projects/{$project->id}/tasks/{$task->id}", [
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'due_date' => '', // Empty due date
        ]);

        $response->assertRedirect();
        $task->refresh();
        $this->assertNull($task->due_date);
    }

    public function test_project_with_due_date_can_create_tasks_with_due_dates()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => now()->addDays(60),
        ]);

        $taskDueDate = now()->addDays(45)->format('Y-m-d');

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks", [
            'title' => 'Test Task',
            'description' => 'Test description',
            'priority' => 'high',
            'status' => 'pending',
            'due_date' => $taskDueDate,
        ]);

        $response->assertRedirect();
        $task = \App\Models\Task::where('title', 'Test Task')->first();
        $this->assertNotNull($task);
        $this->assertEquals($taskDueDate, $task->due_date->format('Y-m-d'));
    }

    public function test_subtask_can_inherit_due_date_from_parent_task()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => now()->addDays(60),
        ]);

        $subtaskDueDate = now()->addDays(45)->format('Y-m-d');

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks", [
            'title' => 'Subtask',
            'description' => 'Subtask description',
            'priority' => 'high',
            'status' => 'pending',
            'parent_id' => $parentTask->id,
            'due_date' => $subtaskDueDate,
        ]);

        $response->assertRedirect();
        $subtask = \App\Models\Task::where('title', 'Subtask')->first();
        $this->assertNotNull($subtask);
        $this->assertEquals($parentTask->id, $subtask->parent_id);
        $this->assertEquals($subtaskDueDate, $subtask->due_date->format('Y-m-d'));
    }

    public function test_task_due_date_is_displayed_in_task_list()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $dueDate = now()->addDays(30)->format('Y-m-d');
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => $dueDate,
        ]);

        $response = $this->actingAs($user)->get("/dashboard/projects/{$project->id}/tasks");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                ->has('tasks')
                ->where('tasks.0.due_date', $dueDate)
        );
    }

    public function test_task_without_due_date_shows_null_in_task_list()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => null,
        ]);

        $response = $this->actingAs($user)->get("/dashboard/projects/{$project->id}/tasks");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                ->has('tasks')
                ->where('tasks.0.due_date', null)
        );
    }
}
