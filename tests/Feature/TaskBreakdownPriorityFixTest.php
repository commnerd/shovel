<?php

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Facades\AI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('task breakdown handles missing priority field gracefully', function () {
    // Create a user, organization, and project
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'title' => 'Test Project',
        'description' => 'A test project for priority testing',
    ]);

    // Create a parent task
    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Parent Task',
        'description' => 'A parent task for testing',
        'status' => 'pending',
    ]);

    // Mock AI response that doesn't include priority field
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'tasks' => [
                                [
                                    'title' => 'Subtask 1',
                                    'description' => 'First subtask',
                                    'status' => 'pending',
                                    // Note: priority field is intentionally omitted
                                ],
                                [
                                    'title' => 'Subtask 2',
                                    'description' => 'Second subtask',
                                    'status' => 'pending',
                                    // Note: priority field is intentionally omitted
                                ],
                            ],
                            'summary' => 'Generated subtasks for parent task',
                            'notes' => ['Priority was redacted from response'],
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);

    // Act as the user and make the request
    $response = $this->actingAs($user)
        ->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'title' => 'Test Task Breakdown',
            'description' => 'Testing task breakdown with missing priority',
            'parent_task_id' => $parentTask->id,
        ]);

    // Assert the response is successful (not 500 error)
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'subtasks' => [
            [
                'title' => 'Subtask 1',
                'description' => 'First subtask',
                'status' => 'pending',
            ],
            [
                'title' => 'Subtask 2',
                'description' => 'Second subtask',
                'status' => 'pending',
            ],
        ],
        'ai_used' => true,
    ]);

    // Verify the response was successful (no 500 error)
    expect($response->status())->toBe(200);
});

test('task breakdown handles parent task without priority gracefully', function () {
    // Create a user, organization, and project
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'title' => 'Test Project',
        'description' => 'A test project for priority testing',
    ]);

    // Create a parent task
    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Parent Task',
        'description' => 'A parent task for testing',
        'status' => 'pending',
    ]);

    // Mock AI response
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'tasks' => [
                                [
                                    'title' => 'Child Task',
                                    'description' => 'A child task',
                                    'status' => 'pending',
                                ],
                            ],
                            'summary' => 'Generated child task',
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);

    // Act as the user and make the request
    $response = $this->actingAs($user)
        ->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'title' => 'Test Task Breakdown',
            'description' => 'Testing task breakdown with parent task missing priority',
            'parent_task_id' => $parentTask->id,
        ]);

    // Assert the response is successful (not 500 error)
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'ai_used' => true,
    ]);

    // Verify the response contains the expected subtasks
    $responseData = $response->json();
    expect($responseData['subtasks'])->toHaveCount(1);
    expect($responseData['subtasks'][0]['title'])->toBe('Child Task');
});

test('task breakdown works with complete task data including priority', function () {
    // Create a user, organization, and project
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'title' => 'Test Project',
        'description' => 'A test project for priority testing',
    ]);

    // Create a parent task
    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Parent Task',
        'description' => 'A parent task for testing',
        'status' => 'pending',
    ]);

    // Mock AI response with complete data including priority
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'tasks' => [
                                [
                                    'title' => 'High Priority Subtask',
                                    'description' => 'A high priority subtask',
                                    'status' => 'pending',
                                    'priority' => 'high',
                                ],
                                [
                                    'title' => 'Medium Priority Subtask',
                                    'description' => 'A medium priority subtask',
                                    'status' => 'pending',
                                    'priority' => 'medium',
                                ],
                            ],
                            'summary' => 'Generated subtasks with priorities',
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);

    // Act as the user and make the request
    $response = $this->actingAs($user)
        ->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'title' => 'Test Task Breakdown',
            'description' => 'Testing task breakdown with complete data',
            'parent_task_id' => $parentTask->id,
        ]);

    // Assert the response is successful
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'ai_used' => true,
    ]);

    // Verify the response contains the expected subtasks
    $responseData = $response->json();
    expect($responseData['subtasks'])->toHaveCount(2);
    expect($responseData['subtasks'][0]['title'])->toBe('High Priority Subtask');
    expect($responseData['subtasks'][1]['title'])->toBe('Medium Priority Subtask');
});

test('task breakdown handles mixed data scenarios', function () {
    // Create a user, organization, and project
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'title' => 'Test Project',
        'description' => 'A test project for mixed data testing',
    ]);

    // Mock AI response with mixed data (some tasks have priority, some don't)
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'tasks' => [
                                [
                                    'title' => 'Task With Priority',
                                    'description' => 'A task with priority',
                                    'status' => 'pending',
                                    'priority' => 'high',
                                ],
                                [
                                    'title' => 'Task Without Priority',
                                    'description' => 'A task without priority',
                                    'status' => 'pending',
                                    // Note: priority field is intentionally omitted
                                ],
                                [
                                    'title' => 'Another Task With Priority',
                                    'description' => 'Another task with priority',
                                    'status' => 'pending',
                                    'priority' => 'low',
                                ],
                            ],
                            'summary' => 'Generated mixed subtasks',
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);

    // Act as the user and make the request
    $response = $this->actingAs($user)
        ->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'title' => 'Test Mixed Data Breakdown',
            'description' => 'Testing task breakdown with mixed data',
        ]);

    // Assert the response is successful (not 500 error)
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'ai_used' => true,
    ]);

    // Verify the response contains all expected subtasks
    $responseData = $response->json();
    expect($responseData['subtasks'])->toHaveCount(3);

    // Verify tasks with priority in their names are preserved
    expect($responseData['subtasks'][0]['title'])->toBe('Task With Priority');
    expect($responseData['subtasks'][2]['title'])->toBe('Another Task With Priority');

    // Verify task without priority doesn't cause errors
    expect($responseData['subtasks'][1])->toHaveKey('title', 'Task Without Priority');
    expect($responseData['subtasks'][1])->not->toHaveKey('priority');
});

test('task breakdown error handling when AI service fails', function () {
    // Create a user, organization, and project
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'title' => 'Test Project',
        'description' => 'A test project for error testing',
    ]);

    // Mock AI service failure
    Http::fake([
        '*' => Http::response([], 500)
    ]);

    // Act as the user and make the request
    $response = $this->actingAs($user)
        ->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'title' => 'Test Task Breakdown',
            'description' => 'Testing task breakdown with AI failure',
        ]);

    // Assert the response handles the error gracefully
    // The response might be 200 with fallback tasks or 500 with error
    $status = $response->status();
    expect($status)->toBeIn([200, 500]);

    $responseData = $response->json();
    if ($status === 500) {
        expect($responseData['success'])->toBe(false);
        expect($responseData['ai_used'])->toBe(false);
    } else {
        // AI service might return fallback tasks instead of failing
        expect($responseData)->toHaveKey('success');
    }
});

test('task breakdown validation works correctly', function () {
    // Create a user, organization, and project
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'title' => 'Test Project',
        'description' => 'A test project for validation testing',
    ]);

    // Test missing required title field
    $response = $this->actingAs($user)
        ->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'description' => 'Testing without title',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['title']);

    // Test with valid data
    $response = $this->actingAs($user)
        ->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'title' => 'Valid Task Title',
            'description' => 'Valid description',
        ]);

    // Should not be a validation error (might be AI service error, but not validation)
    expect($response->status())->not->toBe(422);
});
