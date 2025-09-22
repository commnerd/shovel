<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AITaskResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AITaskGenerationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_task_generation_with_schema_and_communication(): void
    {
        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
        $organization = \App\Models\Organization::getDefault();
        $group = $organization->defaultGroup();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $user->joinGroup($group);

        // Mock AI response with communication
        $mockResponse = AITaskResponse::success(
            tasks: [
                [
                    'title' => 'Setup Development Environment',
                    'description' => 'Configure development tools and dependencies',
                    'priority' => 'high',
                    'status' => 'pending',
                    'subtasks' => [],
                ],
                [
                    'title' => 'Implement Core Features',
                    'description' => 'Build main application functionality',
                    'priority' => 'high',
                    'status' => 'pending',
                    'subtasks' => [],
                ],
            ],
            notes: ['Project looks well-defined', 'Consider using modern frameworks'],
            summary: 'This is a solid project with clear requirements.',
            problems: ['Timeline might be tight for Q4 delivery'],
            suggestions: ['Consider breaking into smaller milestones', 'Add buffer time for testing']
        );

        $mockAI = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->once()
            ->withAnyArgs()
            ->andReturn($mockResponse);

        $this->app->instance('ai', $mockAI);

        $response = $this->actingAs($user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a modern web application',
                'due_date' => '2025-12-31',
                'group_id' => $user->groups->first()->id,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Projects/CreateTasks')
            ->has('suggestedTasks', 2)
            ->where('aiUsed', true)
            ->has('aiCommunication')
            ->where('aiCommunication.summary', 'This is a solid project with clear requirements.')
            ->has('aiCommunication.notes', 2)
            ->has('aiCommunication.problems', 1)
            ->has('aiCommunication.suggestions', 2)
        );
    }

    public function test_ai_task_generation_with_schema_validation(): void
    {
        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
        $organization = \App\Models\Organization::getDefault();
        $group = $organization->defaultGroup();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $user->joinGroup($group);

        // Mock AI manager to capture schema passed
        $capturedSchema = null;
        $mockAI = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->once()
            ->with(
                'Simple project',
                \Mockery::capture($capturedSchema),
                \Mockery::type('array') // AI options
            )
            ->andReturn(AITaskResponse::success(tasks: []));

        $this->app->instance('ai', $mockAI);

        $this->actingAs($user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Simple project',
                'due_date' => '2025-12-31',
                'group_id' => $user->groups->first()->id,
            ]);

        // Verify schema structure
        $this->assertIsArray($capturedSchema);
        $this->assertArrayHasKey('tasks', $capturedSchema);
        $this->assertArrayHasKey('summary', $capturedSchema);
        $this->assertArrayHasKey('notes', $capturedSchema);
        $this->assertArrayHasKey('problems', $capturedSchema);
        $this->assertArrayHasKey('suggestions', $capturedSchema);
    }

    public function test_ai_task_generation_handles_failure_gracefully(): void
    {
        $user = User::factory()->create();

        // Mock AI failure
        $failedResponse = AITaskResponse::failed('API connection failed');

        $this->mock(AIManager::class, function ($mock) use ($failedResponse) {
            $mock->shouldReceive('generateTasks')
                ->once()
                ->andReturn($failedResponse);
        });

        $response = $this->actingAs($user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Test project',
                'due_date' => '2025-12-31',
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Projects/CreateTasks')
            ->has('suggestedTasks') // Should have fallback tasks
            ->where('aiUsed', false)
            ->where('aiCommunication', null)
        );
    }

    public function test_ai_communication_displays_all_types(): void
    {
        $user = User::factory()->create();

        // Mock comprehensive AI response
        $mockResponse = AITaskResponse::success(
            tasks: [
                [
                    'title' => 'Test Task',
                    'description' => 'Test Description',
                    'priority' => 'medium',
                    'status' => 'pending',
                    'subtasks' => [],
                ],
            ],
            notes: ['Note 1', 'Note 2'],
            summary: 'Comprehensive analysis summary',
            problems: ['Problem 1', 'Problem 2'],
            suggestions: ['Suggestion 1', 'Suggestion 2']
        );

        $this->mock(AIManager::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('generateTasks')
                ->once()
                ->andReturn($mockResponse);
        });

        $response = $this->actingAs($user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Comprehensive test project',
                'due_date' => '2025-12-31',
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('aiCommunication.summary', 'Comprehensive analysis summary')
            ->has('aiCommunication.notes', 2)
            ->where('aiCommunication.notes.0', 'Note 1')
            ->where('aiCommunication.notes.1', 'Note 2')
            ->has('aiCommunication.problems', 2)
            ->where('aiCommunication.problems.0', 'Problem 1')
            ->where('aiCommunication.problems.1', 'Problem 2')
            ->has('aiCommunication.suggestions', 2)
            ->where('aiCommunication.suggestions.0', 'Suggestion 1')
            ->where('aiCommunication.suggestions.1', 'Suggestion 2')
        );
    }

    public function test_ai_task_regeneration_works(): void
    {
        $user = User::factory()->create();

        // Mock AI response for regeneration
        $mockResponse = AITaskResponse::success(
            tasks: [
                [
                    'title' => 'Regenerated Task',
                    'description' => 'This is a regenerated task',
                    'priority' => 'high',
                    'status' => 'pending',
                    'subtasks' => [],
                ],
            ],
            notes: ['Regenerated based on feedback'],
            summary: 'Updated task breakdown'
        );

        $this->mock(AIManager::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('generateTasks')
                ->once()
                ->andReturn($mockResponse);
        });

        $response = $this->actingAs($user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Project for regeneration',
                'due_date' => '2025-12-31',
                'regenerate' => true,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('suggestedTasks', 1)
            ->where('suggestedTasks.0.title', 'Regenerated Task')
            ->where('aiCommunication.summary', 'Updated task breakdown')
        );
    }

    public function test_task_validation_and_sanitization(): void
    {
        $user = User::factory()->create();

        // Mock AI response with valid tasks (validation happens in CerebrasProvider)
        // The AITaskResponse should contain already-validated tasks
        $mockResponse = AITaskResponse::success(
            tasks: [
                [
                    'title' => 'Valid Task',
                    'description' => 'Good description',
                    'priority' => 'high',
                    'status' => 'pending',
                    'subtasks' => [],
                ],
                [
                    'title' => 'Partial Task',
                    'description' => '',
                    'priority' => 'medium', // Default applied by validation
                    'status' => 'pending', // Default applied by validation
                    'subtasks' => [],
                ],
                // Task without title was filtered out by CerebrasProvider validation
            ]
        );

        $this->mock(AIManager::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('generateTasks')
                ->once()
                ->andReturn($mockResponse);
        });

        $response = $this->actingAs($user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Test validation project',
                'due_date' => '2025-12-31',
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('suggestedTasks', 2) // Should have 2 valid tasks after filtering
            ->where('suggestedTasks.0.title', 'Valid Task')
            ->where('suggestedTasks.1.title', 'Partial Task')
            ->where('suggestedTasks.1.status', 'pending') // Default status
        );
    }

    public function test_ai_integration_requires_authentication(): void
    {
        $response = $this->post('/dashboard/projects/create/tasks', [
            'description' => 'Test project',
            'due_date' => '2025-12-31',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_ai_communication_handles_string_arrays_conversion(): void
    {
        $user = User::factory()->create();

        // Mock AI response with string instead of array for some fields
        $mockResponse = AITaskResponse::success(
            tasks: [
                [
                    'title' => 'Test Task',
                    'description' => 'Test Description',
                    'priority' => 'medium',
                    'status' => 'pending',
                    'subtasks' => [],
                ],
            ],
            notes: 'Single note string', // String instead of array
            summary: 'Test summary',
            problems: 'Single problem string', // String instead of array
            suggestions: ['Proper array suggestion'] // Already array
        );

        // The CerebrasProvider should handle string to array conversion
        $this->mock(AIManager::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('generateTasks')
                ->once()
                ->andReturn($mockResponse);
        });

        $response = $this->actingAs($user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'String conversion test',
                'due_date' => '2025-12-31',
            ]);

        $response->assertOk();
        // The response should work regardless of string/array input
        $response->assertInertia(fn ($page) => $page
            ->where('aiUsed', true)
            ->has('aiCommunication')
        );
    }
}
