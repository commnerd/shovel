<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubtaskDueDateBugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
    }

    public function test_subtasks_should_not_get_due_dates_when_parent_task_has_no_due_date_and_project_has_no_due_date()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => null, // No project due date
        ]);
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => null, // No parent task due date
        ]);

        // Mock AI response for task breakdown that doesn't include due dates
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'subtasks' => [
                                [
                                    'title' => 'Research Phase',
                                    'description' => 'Research requirements',
                                    'status' => 'pending',
                                    // No due_date in AI response
                                ],
                                [
                                    'title' => 'Implementation Phase',
                                    'description' => 'Implement features',
                                    'status' => 'pending',
                                    // No due_date in AI response
                                ],
                            ],
                        ]),
                    ],
                ]],
            ], 200)
        ]);

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'parent_task_id' => $parentTask->id,
            'title' => 'Break down this task',
            'description' => 'Need to break this into smaller tasks',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('subtasks', $responseData);

        // Verify that NO subtasks have due dates
        foreach ($responseData['subtasks'] as $subtask) {
            $this->assertNull($subtask['due_date'] ?? null,
                "Subtask '{$subtask['title']}' should NOT have a due date when neither parent task nor project has due dates. Got: " . ($subtask['due_date'] ?? 'null'));
        }
    }

    public function test_subtasks_should_not_get_due_dates_when_parent_task_has_no_due_date_even_if_project_has_due_date()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => now()->addDays(30), // Project has due date
        ]);
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => null, // No parent task due date
        ]);

        // Mock AI response for task breakdown that doesn't include due dates
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'subtasks' => [
                                [
                                    'title' => 'Research Phase',
                                    'description' => 'Research requirements',
                                    'status' => 'pending',
                                    // No due_date in AI response
                                ],
                                [
                                    'title' => 'Implementation Phase',
                                    'description' => 'Implement features',
                                    'status' => 'pending',
                                    // No due_date in AI response
                                ],
                            ],
                        ]),
                    ],
                ]],
            ], 200)
        ]);

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'parent_task_id' => $parentTask->id,
            'title' => 'Break down this task',
            'description' => 'Need to break this into smaller tasks',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('subtasks', $responseData);

        // The current logic assigns due dates from project if parent task doesn't have one
        // But according to the bug report, this should NOT happen - subtasks should only
        // get due dates if their DIRECT parent task has a due date
        foreach ($responseData['subtasks'] as $subtask) {
            $this->assertNull($subtask['due_date'] ?? null,
                "Subtask '{$subtask['title']}' should NOT have a due date when parent task has no due date, even if project has due date. Got: " . ($subtask['due_date'] ?? 'null'));
        }
    }

    public function test_subtasks_should_get_due_dates_only_when_parent_task_has_due_date()
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

        // Mock AI response for task breakdown that doesn't include due dates
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'subtasks' => [
                                [
                                    'title' => 'Research Phase',
                                    'description' => 'Research requirements',
                                    'status' => 'pending',
                                    // No due_date in AI response - should be calculated from parent
                                ],
                                [
                                    'title' => 'Implementation Phase',
                                    'description' => 'Implement features',
                                    'status' => 'pending',
                                    // No due_date in AI response - should be calculated from parent
                                ],
                            ],
                        ]),
                    ],
                ]],
            ], 200)
        ]);

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'parent_task_id' => $parentTask->id,
            'title' => 'Break down this task',
            'description' => 'Need to break this into smaller tasks',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('subtasks', $responseData);

        // Subtasks should get due dates calculated from parent task
        foreach ($responseData['subtasks'] as $subtask) {
            $this->assertNotNull($subtask['due_date'] ?? null,
                "Subtask '{$subtask['title']}' should have a calculated due date when parent task has a due date");

            // Due date should be before or equal to parent task due date
            $this->assertLessThanOrEqual(
                $parentTask->due_date->format('Y-m-d'),
                $subtask['due_date'],
                "Subtask due date should not exceed parent task due date"
            );
        }
    }

    public function test_addDueDatesToSubtasks_method_only_uses_parent_task_due_date()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => now()->addDays(30), // Project has due date
        ]);
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => null, // Parent task has NO due date
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

        // Should return subtasks unchanged (no due dates added) because parent task has no due date
        // Even though project has a due date, subtasks should only inherit from direct parent
        foreach ($result as $subtask) {
            $this->assertArrayNotHasKey('due_date', $subtask,
                "Subtask should not get due date when parent task has no due date, even if project has due date");
        }
    }

    public function test_addDueDatesToSubtasks_method_uses_parent_task_due_date_when_available()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => now()->addDays(60), // Project has due date
        ]);
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => now()->addDays(20), // Parent task has due date (earlier than project)
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
        ];

        $result = $method->invoke($controller, $subtasks, $parentTask, $project);

        // Should get due dates calculated from parent task, not project
        $this->assertArrayHasKey('due_date', $result[0]);
        $this->assertNotNull($result[0]['due_date']);
        $this->assertLessThanOrEqual(
            $parentTask->due_date->format('Y-m-d'),
            $result[0]['due_date'],
            "Subtask due date should be calculated from parent task, not project"
        );
    }
}
