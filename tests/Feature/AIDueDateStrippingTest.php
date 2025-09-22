<?php

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\OrganizationSeeder::class);
});

test('AI returned due dates are stripped when parent task has no due date', function () {
    // Configure OpenAI provider for this test
    \App\Models\Setting::set('ai.openai.api_key', 'test-openai-key', 'string', 'OpenAI API Key');

    // Create user, organization, group, and project
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    // Associate user with organization
    $user->update(['organization_id' => $organization->id]);

    // Create project WITHOUT due date
    $project = Project::factory()->create([
        'user_id' => $user->id, // Ensure user owns the project
        'group_id' => $group->id,
        'ai_provider' => 'openai',
        'due_date' => null, // No project due date
    ]);

    // Create parent task WITHOUT due date
    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Parent Task',
        'description' => 'Parent task description',
        'due_date' => null, // No parent due date
        'status' => 'pending',
    ]);

    // Mock AI response that includes due dates (which should be stripped)
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'subtasks' => [
                                [
                                    'title' => 'Research Phase',
                                    'description' => 'Conduct initial research',
                                    'status' => 'pending',
                                    'due_date' => '2024-01-15', // AI provided due date - should be stripped
                                ],
                                [
                                    'title' => 'Implementation Phase',
                                    'description' => 'Implement the solution',
                                    'status' => 'pending',
                                    'due_date' => '2024-01-20', // AI provided due date - should be stripped
                                ],
                            ],
                            'notes' => ['AI generated breakdown'],
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);

    // Make the request
    $response = $this->actingAs($user)->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
        'title' => 'Test Task Breakdown',
        'description' => 'Test description',
        'parent_task_id' => $parentTask->id,
    ]);

    $response->assertStatus(200);

    $responseData = $response->json();
    expect($responseData['success'])->toBe(true);
    expect($responseData['subtasks'])->toHaveCount(2);

    // Verify that AI-returned due dates were stripped since parent has no due date
    foreach ($responseData['subtasks'] as $subtask) {
        expect($subtask)->not->toHaveKey('due_date', 'Subtasks should not have due dates when parent task has no due date');
    }
});

test('AI returned due dates are preserved when parent task has due date', function () {
    // Configure OpenAI provider for this test
    \App\Models\Setting::set('ai.openai.api_key', 'test-openai-key', 'string', 'OpenAI API Key');

    // Create user, organization, group, and project
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    // Associate user with organization
    $user->update(['organization_id' => $organization->id]);

    // Create project with due date
    $project = Project::factory()->create([
        'user_id' => $user->id, // Ensure user owns the project
        'group_id' => $group->id,
        'ai_provider' => 'openai',
        'due_date' => '2024-02-01',
    ]);

    // Create parent task WITH due date
    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Parent Task',
        'description' => 'Parent task description',
        'due_date' => '2024-01-25', // Parent has due date
        'status' => 'pending',
    ]);

    // Mock AI response that includes due dates (which should be preserved)
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'subtasks' => [
                                [
                                    'title' => 'Research Phase',
                                    'description' => 'Conduct initial research',
                                    'status' => 'pending',
                                    'due_date' => '2024-01-15', // AI provided due date - should be preserved
                                ],
                                [
                                    'title' => 'Implementation Phase',
                                    'description' => 'Implement the solution',
                                    'status' => 'pending',
                                    'due_date' => '2024-01-20', // AI provided due date - should be preserved
                                ],
                            ],
                            'notes' => ['AI generated breakdown'],
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);

    // Make the request
    $response = $this->actingAs($user)->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
        'title' => 'Test Task Breakdown',
        'description' => 'Test description',
        'parent_task_id' => $parentTask->id,
    ]);

    $response->assertStatus(200);

    $responseData = $response->json();
    expect($responseData['success'])->toBe(true);
    expect($responseData['subtasks'])->toHaveCount(2);

    // Verify that AI-returned due dates were preserved since parent has due date
    foreach ($responseData['subtasks'] as $subtask) {
        expect($subtask)->toHaveKey('due_date');
        expect($subtask['due_date'])->not->toBeNull();
    }
});

test('AI returned due dates are stripped when no parent task provided and project has no due date', function () {
    // Configure OpenAI provider for this test
    \App\Models\Setting::set('ai.openai.api_key', 'test-openai-key', 'string', 'OpenAI API Key');

    // Create user, organization, group, and project
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    // Associate user with organization
    $user->update(['organization_id' => $organization->id]);

    // Create project WITHOUT due date
    $project = Project::factory()->create([
        'user_id' => $user->id, // Ensure user owns the project
        'group_id' => $group->id,
        'ai_provider' => 'openai',
        'due_date' => null, // No project due date
    ]);

    // Mock AI response that includes due dates (which should be stripped)
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'subtasks' => [
                                [
                                    'title' => 'Research Phase',
                                    'description' => 'Conduct initial research',
                                    'status' => 'pending',
                                    'due_date' => '2024-01-15', // AI provided due date - should be stripped
                                ],
                                [
                                    'title' => 'Implementation Phase',
                                    'description' => 'Implement the solution',
                                    'status' => 'pending',
                                    'due_date' => '2024-01-20', // AI provided due date - should be stripped
                                ],
                            ],
                            'notes' => ['AI generated breakdown'],
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);

    // Make the request WITHOUT parent_task_id
    $response = $this->actingAs($user)->postJson("/dashboard/projects/{$project->id}/tasks/breakdown", [
        'title' => 'Test Task Breakdown',
        'description' => 'Test description',
        // No parent_task_id provided
    ]);

    $response->assertStatus(200);

    $responseData = $response->json();
    expect($responseData['success'])->toBe(true);
    expect($responseData['subtasks'])->toHaveCount(2);

    // Verify that AI-returned due dates were stripped since no parent task and no project due date
    foreach ($responseData['subtasks'] as $subtask) {
        expect($subtask)->not->toHaveKey('due_date', 'Subtasks should not have due dates when no parent task and project has no due date');
    }
});
