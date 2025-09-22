<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Providers\CerebrasProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DueDateApplicationLogicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    public function test_add_due_dates_to_subtasks_without_reference_due_date_returns_unchanged()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => null, // No project due date
        ]);
        $parentTask = null; // No parent task

        // Create a TasksController instance to test the method
        $controller = new \App\Http\Controllers\TasksController();

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('addDueDatesToSubtasks');
        $method->setAccessible(true);

        $subtasks = [
            [
                'title' => 'Research Phase',
                'description' => 'Research requirements',
                'status' => 'pending',
                // No due_date
            ],
            [
                'title' => 'Implementation Phase',
                'description' => 'Implement features',
                'status' => 'pending',
                // No due_date
            ],
        ];

        $result = $method->invoke($controller, $subtasks, $parentTask, $project);

        // Should return subtasks unchanged (no due dates added)
        $this->assertEquals($subtasks, $result);

        foreach ($result as $subtask) {
            $this->assertArrayNotHasKey('due_date', $subtask);
        }
    }

    public function test_add_due_dates_to_subtasks_with_project_due_date_but_no_parent_task_does_not_calculate_dates()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => now()->addDays(30), // Project has due date
        ]);
        $parentTask = null; // No parent task

        // Create a TasksController instance to test the method
        $controller = new \App\Http\Controllers\TasksController();

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('addDueDatesToSubtasks');
        $method->setAccessible(true);

        $subtasks = [
            [
                'title' => 'Research Phase',
                'description' => 'Research requirements',
                'status' => 'pending',
                // No due_date
            ],
            [
                'title' => 'Implementation Phase',
                'description' => 'Implement features',
                'status' => 'pending',
                // No due_date
            ],
        ];

        $result = $method->invoke($controller, $subtasks, $parentTask, $project);

        // Should NOT have due dates calculated because there's no parent task
        // Even though project has due date, subtasks should only inherit from direct parent
        foreach ($result as $subtask) {
            $this->assertArrayNotHasKey('due_date', $subtask,
                "Subtask should not get due date when there's no parent task, even if project has due date");
        }
    }

    public function test_add_due_dates_to_subtasks_with_parent_task_due_date_calculates_dates()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => null, // No project due date
        ]);
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => now()->addDays(20), // Parent task has due date
        ]);

        // Create a TasksController instance to test the method
        $controller = new \App\Http\Controllers\TasksController();

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('addDueDatesToSubtasks');
        $method->setAccessible(true);

        $subtasks = [
            [
                'title' => 'Research Phase',
                'description' => 'Research requirements',
                'status' => 'pending',
                // No due_date - should be calculated from parent task
            ],
            [
                'title' => 'Implementation Phase',
                'description' => 'Implement features',
                'status' => 'pending',
                // No due_date - should be calculated from parent task
            ],
        ];

        $result = $method->invoke($controller, $subtasks, $parentTask, $project);

        // Should have due dates calculated from parent task
        foreach ($result as $subtask) {
            $this->assertArrayHasKey('due_date', $subtask);
            $this->assertNotNull($subtask['due_date']);
            $this->assertLessThanOrEqual(
                $parentTask->due_date->format('Y-m-d'),
                $subtask['due_date'],
                "Subtask due date should not exceed parent task due date"
            );
        }
    }

    public function test_add_due_dates_to_subtasks_preserves_existing_due_dates()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => now()->addDays(30), // Project has due date
        ]);
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => now()->addDays(20), // Parent task has due date
        ]);

        // Create a TasksController instance to test the method
        $controller = new \App\Http\Controllers\TasksController();

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('addDueDatesToSubtasks');
        $method->setAccessible(true);

        $existingDueDate = now()->addDays(15)->format('Y-m-d');
        $subtasks = [
            [
                'title' => 'Research Phase',
                'description' => 'Research requirements',
                'status' => 'pending',
                'due_date' => $existingDueDate, // Already has a due date
            ],
            [
                'title' => 'Implementation Phase',
                'description' => 'Implement features',
                'status' => 'pending',
                // No due_date - should be calculated from parent task
            ],
        ];

        $result = $method->invoke($controller, $subtasks, $parentTask, $project);

        // First subtask should keep its existing due date
        $this->assertEquals($existingDueDate, $result[0]['due_date']);

        // Second subtask should get a calculated due date from parent task
        $this->assertArrayHasKey('due_date', $result[1]);
        $this->assertNotNull($result[1]['due_date']);
        $this->assertNotEquals($existingDueDate, $result[1]['due_date']);
    }

    public function test_cerebras_fallback_tasks_without_project_due_date_have_no_due_dates()
    {
        $provider = new CerebrasProvider([
            'api_key' => 'test-key',
            'base_url' => 'http://test.com',
            'model' => 'test-model',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('createFallbackTaskBreakdown');
        $method->setAccessible(true);

        // Call without project due date
        $result = $method->invoke($provider, 'Test Task', 'Test Description', null);

        $this->assertTrue($result->isSuccessful());
        $tasks = $result->getTasks();

        foreach ($tasks as $task) {
            $this->assertNull($task['due_date'] ?? null,
                "Fallback task '{$task['title']}' should not have a due date when no project due date is provided");
        }
    }

    public function test_cerebras_fallback_tasks_with_project_due_date_have_calculated_due_dates()
    {
        $provider = new CerebrasProvider([
            'api_key' => 'test-key',
            'base_url' => 'http://test.com',
            'model' => 'test-model',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('createFallbackTaskBreakdown');
        $method->setAccessible(true);

        $projectDueDate = now()->addDays(30)->format('Y-m-d');

        // Call with project due date
        $result = $method->invoke($provider, 'Test Task', 'Test Description', $projectDueDate);

        $this->assertTrue($result->isSuccessful());
        $tasks = $result->getTasks();

        foreach ($tasks as $task) {
            $this->assertNotNull($task['due_date'] ?? null,
                "Fallback task '{$task['title']}' should have a calculated due date when project due date is provided");

            // Due date should be before or equal to project due date
            $this->assertLessThanOrEqual(
                $projectDueDate,
                $task['due_date'],
                "Fallback task due date should not exceed project due date"
            );
        }
    }

    public function test_openai_fallback_tasks_respect_project_due_date_parameter()
    {
        $provider = new OpenAIProvider([
            'api_key' => 'test-key',
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('createFallbackTaskBreakdown');
        $method->setAccessible(true);

        // Test without project due date
        $resultWithoutDueDate = $method->invoke($provider, 'Test Task', 'Test Description', null);
        $this->assertTrue($resultWithoutDueDate->isSuccessful());
        $tasksWithoutDueDate = $resultWithoutDueDate->getTasks();

        foreach ($tasksWithoutDueDate as $task) {
            $this->assertNull($task['due_date'] ?? null,
                "OpenAI fallback task should not have a due date when no project due date is provided");
        }

        // Test with project due date (OpenAI provider doesn't automatically calculate due dates in fallback)
        $projectDueDate = now()->addDays(30)->format('Y-m-d');
        $resultWithDueDate = $method->invoke($provider, 'Test Task', 'Test Description', $projectDueDate);
        $this->assertTrue($resultWithDueDate->isSuccessful());
        $tasksWithDueDate = $resultWithDueDate->getTasks();

        // OpenAI provider doesn't add due dates in fallback even with project due date
        // This is the current expected behavior
        foreach ($tasksWithDueDate as $task) {
            $this->assertNull($task['due_date'] ?? null,
                "OpenAI fallback task should not have a due date (current expected behavior)");
        }
    }

    public function test_project_creation_without_due_date_creates_tasks_without_due_dates()
    {
        // Set up user with organization and group
        $organization = Organization::getDefault();
        $group = $organization->createDefaultGroup();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $user->joinGroup($group);

        $response = $this->actingAs($user)->post('/dashboard/projects', [
            'title' => 'Test Project Without Due Date',
            'description' => 'A project to test task creation without due dates',
            'due_date' => null, // No project due date
            'group_id' => $group->id,
            'tasks' => [
                [
                    'title' => 'Setup',
                    'description' => 'Set up the project',
                    'status' => 'pending',
                    'sort_order' => 1,
                    // No due_date provided - should remain null
                ],
                [
                    'title' => 'Development',
                    'description' => 'Develop features',
                    'status' => 'pending',
                    'sort_order' => 2,
                    // No due_date provided - should remain null
                ],
            ],
        ]);

        $response->assertRedirect();

        // Check if project was created
        $project = Project::where('title', 'Test Project Without Due Date')->first();
        $this->assertNotNull($project);
        $this->assertNull($project->due_date);

        $tasks = $project->tasks;
        $this->assertCount(2, $tasks);

        // Check that tasks don't have due dates
        foreach ($tasks as $task) {
            $this->assertNull($task->due_date,
                "Task '{$task->title}' should not have a due date when project has no due date");
        }
    }

    public function test_project_creation_with_due_date_allows_tasks_with_due_dates()
    {
        // Set up user with organization and group
        $organization = Organization::getDefault();
        $group = $organization->createDefaultGroup();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $user->joinGroup($group);

        $projectDueDate = now()->addDays(60)->format('Y-m-d');

        $response = $this->actingAs($user)->post('/dashboard/projects', [
            'title' => 'Test Project With Due Date',
            'description' => 'A project to test task creation with due dates',
            'due_date' => $projectDueDate,
            'group_id' => $group->id,
            'tasks' => [
                [
                    'title' => 'Setup',
                    'description' => 'Set up the project',
                    'status' => 'pending',
                    'due_date' => now()->addDays(15)->format('Y-m-d'),
                    'sort_order' => 1,
                ],
                [
                    'title' => 'Development',
                    'description' => 'Develop features',
                    'status' => 'pending',
                    'due_date' => now()->addDays(45)->format('Y-m-d'),
                    'sort_order' => 2,
                ],
            ],
        ]);

        $response->assertRedirect();

        // Check if project was created
        $project = Project::where('title', 'Test Project With Due Date')->first();
        $this->assertNotNull($project);
        $this->assertEquals($projectDueDate, $project->due_date->format('Y-m-d'));

        $tasks = $project->tasks;
        $this->assertCount(2, $tasks);

        // Check that tasks have due dates
        foreach ($tasks as $task) {
            $this->assertNotNull($task->due_date,
                "Task '{$task->title}' should have a due date when explicitly provided");
        }
    }

    public function test_manual_task_creation_respects_explicit_due_dates_regardless_of_project()
    {
        $user = User::factory()->create();

        // Test with project without due date
        $projectWithoutDueDate = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => null,
        ]);

        $taskDueDate = now()->addDays(30)->format('Y-m-d');

        $response = $this->actingAs($user)->post("/dashboard/projects/{$projectWithoutDueDate->id}/tasks", [
            'title' => 'Manual Task',
            'description' => 'Manually created task',
            'status' => 'pending',
            'due_date' => $taskDueDate, // Explicit due date
        ]);

        $response->assertRedirect();

        $task = Task::where('title', 'Manual Task')->first();
        $this->assertNotNull($task);
        $this->assertEquals($taskDueDate, $task->due_date->format('Y-m-d'));

        // Test with project with due date
        $projectWithDueDate = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => now()->addDays(60),
        ]);

        $response = $this->actingAs($user)->post("/dashboard/projects/{$projectWithDueDate->id}/tasks", [
            'title' => 'Another Manual Task',
            'description' => 'Another manually created task',
            'status' => 'pending',
            'due_date' => null, // Explicitly no due date
        ]);

        $response->assertRedirect();

        $anotherTask = Task::where('title', 'Another Manual Task')->first();
        $this->assertNotNull($anotherTask);
        $this->assertNull($anotherTask->due_date);
    }
}
