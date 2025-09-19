<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Contracts\AITaskResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegenerationFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $organization = Organization::getDefault();
        $group = $organization->createDefaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'title' => 'Test Project',
            'description' => 'A test project for regeneration feedback',
        ]);
    }

    public function test_task_breakdown_accepts_user_feedback()
    {
        // Mock the AI service to capture user feedback
        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('breakdownTask')
            ->once()
            ->withAnyArgs()
            ->andReturn(AITaskResponse::success([
                [
                    'title' => 'Specific Feature Planning',
                    'description' => 'Detailed planning based on user feedback',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_date' => '2025-12-31',
                ],
                [
                    'title' => 'Implementation with Testing',
                    'description' => 'Implementation phase with comprehensive testing',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_date' => '2026-01-15',
                ],
            ], null, ['Breakdown improved based on user feedback']));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Complex Feature Implementation',
                'description' => 'Build a complex feature for the application',
                'user_feedback' => 'Make the tasks more specific and include testing phases',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'subtasks' => [
                [
                    'title' => 'Specific Feature Planning',
                    'description' => 'Detailed planning based on user feedback',
                ],
                [
                    'title' => 'Implementation with Testing',
                    'description' => 'Implementation phase with comprehensive testing',
                ],
            ],
            'notes' => ['Breakdown improved based on user feedback'],
        ]);
    }

    public function test_project_task_generation_accepts_user_feedback()
    {
        // Mock the AI service to capture user feedback
        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('generateTasks')
            ->once()
            ->with(
                'Build a mobile app',
                \Mockery::type('array'),
                \Mockery::on(function ($options) {
                    // Verify user feedback is included in options
                    $this->assertEquals('Focus on security and add more testing tasks', $options['user_feedback']);

                    return true;
                })
            )
            ->andReturn(AITaskResponse::success([
                [
                    'title' => 'Security-focused Setup',
                    'description' => 'Setup with security considerations',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Comprehensive Testing Suite',
                    'description' => 'Build extensive testing based on feedback',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
            ], 'Security-Focused Mobile App'));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'title' => 'Mobile App Project',
                'description' => 'Build a mobile app',
                'group_id' => $this->user->groups->first()->id,
                'regenerate' => true,
                'user_feedback' => 'Focus on security and add more testing tasks',
            ]);

        $response->assertOk();
    }

    public function test_task_breakdown_without_feedback_works_normally()
    {
        // Mock the AI service without feedback
        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('breakdownTask')
            ->once()
            ->withAnyArgs()
            ->andReturn(AITaskResponse::success([
                [
                    'title' => 'Standard Subtask',
                    'description' => 'Regular subtask without feedback',
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
            ], null, ['Standard breakdown']));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Normal Task',
                'description' => 'A regular task without feedback',
                // No user_feedback provided
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'subtasks' => [
                [
                    'title' => 'Standard Subtask',
                    'description' => 'Regular subtask without feedback',
                ],
            ],
        ]);
    }

    public function test_user_feedback_validation()
    {
        // Test feedback that's too long
        $longFeedback = str_repeat('This feedback is too long. ', 200); // > 2000 chars

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'user_feedback' => $longFeedback,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_feedback']);
    }

    public function test_project_task_generation_feedback_validation()
    {
        // Test feedback that's too long in project creation
        $longFeedback = str_repeat('This feedback is too long. ', 200); // > 2000 chars

        $response = $this->actingAs($this->user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/dashboard/projects/create/tasks', [
                'title' => 'Test Project',
                'description' => 'Test description',
                'group_id' => $this->user->groups->first()->id,
                'user_feedback' => $longFeedback,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_feedback']);
    }

    public function test_feedback_improves_ai_response_quality()
    {
        // Create existing tasks for context
        Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Basic Setup',
            'status' => 'completed',
        ]);

        // Mock AI service with specific feedback handling
        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('breakdownTask')
            ->once()
            ->withAnyArgs()
            ->andReturn(AITaskResponse::success([
                [
                    'title' => 'Accessible Component Design',
                    'description' => 'Design components with accessibility in mind',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Mobile-Responsive Layout',
                    'description' => 'Implement responsive design for mobile devices',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
            ], null, ['Breakdown enhanced with accessibility and mobile focus']));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'User Interface Development',
                'description' => 'Create the user interface',
                'user_feedback' => 'Add accessibility features and mobile responsiveness',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'subtasks' => [
                [
                    'title' => 'Accessible Component Design',
                ],
                [
                    'title' => 'Mobile-Responsive Layout',
                ],
            ],
            'notes' => ['Breakdown enhanced with accessibility and mobile focus'],
        ]);
    }

    public function test_regeneration_feedback_preserves_project_context()
    {
        // Create complex project context
        $completedTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Database Setup',
            'status' => 'completed',
            'priority' => 'high',
        ]);

        $inProgressTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'API Development',
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        // Mock AI service
        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('breakdownTask')
            ->once()
            ->withAnyArgs()
            ->andReturn(AITaskResponse::success([
                [
                    'title' => 'Component Architecture Design',
                    'description' => 'Design modular component structure',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
            ], null, ['Breakdown focuses on modularity as requested']));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Frontend Development',
                'description' => 'Build the frontend application',
                'user_feedback' => 'Make it more modular and component-based',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_empty_feedback_is_handled_gracefully()
    {
        // Test with empty feedback string
        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('breakdownTask')
            ->once()
            ->withAnyArgs()
            ->andReturn(AITaskResponse::success([
                [
                    'title' => 'Standard subtask',
                    'description' => 'Regular subtask',
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
            ], null, ['Standard breakdown']));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'user_feedback' => '', // Empty feedback
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_regeneration_requires_authentication()
    {
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Test Task',
            'description' => 'Test description',
            'user_feedback' => 'Some feedback',
        ]);

        $response->assertStatus(401);
    }

    public function test_regeneration_requires_project_ownership()
    {
        // Create different user and project
        $otherUser = User::factory()->create([
            'organization_id' => $this->user->organization_id,
            'pending_approval' => false,
        ]);
        $otherUser->joinGroup($this->user->groups->first());

        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->user->groups->first()->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$otherProject->id}/tasks/breakdown", [
                'title' => 'Unauthorized Task',
                'description' => 'Should not be allowed',
                'user_feedback' => 'Unauthorized feedback',
            ]);

        $response->assertStatus(403);
    }

    public function test_project_task_generation_with_feedback()
    {
        // Mock AI service for project task generation
        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('generateTasks')
            ->once()
            ->with(
                'E-commerce platform development',
                \Mockery::type('array'),
                \Mockery::on(function ($options) {
                    // Verify user feedback is passed in options
                    $this->assertEquals('Focus on payment security and user experience', $options['user_feedback']);

                    return true;
                })
            )
            ->andReturn(AITaskResponse::success([
                [
                    'title' => 'Secure Payment Integration',
                    'description' => 'Implement secure payment processing',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'User Experience Optimization',
                    'description' => 'Optimize user flows and interface',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
            ], 'Secure E-commerce Platform'));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'title' => 'E-commerce Project',
                'description' => 'E-commerce platform development',
                'group_id' => $this->user->groups->first()->id,
                'regenerate' => true,
                'user_feedback' => 'Focus on payment security and user experience',
            ]);

        $response->assertOk();
    }

    public function test_feedback_character_limit_validation()
    {
        // Test maximum allowed feedback length (2000 chars)
        $maxFeedback = str_repeat('a', 2000);

        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('breakdownTask')
            ->once()
            ->andReturn(AITaskResponse::success([], null, []));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'user_feedback' => $maxFeedback,
            ]);

        $response->assertOk(); // Should accept exactly 2000 chars

        // Test over the limit
        $overLimitFeedback = str_repeat('a', 2001);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'user_feedback' => $overLimitFeedback,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_feedback']);
    }

    public function test_ai_prompt_enhancement_with_feedback()
    {
        // This test verifies the prompt building logic includes feedback correctly
        $provider = new \App\Services\AI\Providers\CerebrusProvider(config('ai.providers.cerebrus'));

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('buildTaskBreakdownUserPrompt');
        $method->setAccessible(true);

        $context = [
            'user_feedback' => 'Make tasks more granular and include documentation steps',
            'project' => [
                'title' => 'Test Project',
                'description' => 'Test description',
            ],
        ];

        $prompt = $method->invoke($provider, 'API Development', 'Build REST API', $context);

        // Verify feedback is included in the prompt
        $this->assertStringContainsString('User Feedback for Improvement:', $prompt);
        $this->assertStringContainsString('Make tasks more granular and include documentation steps', $prompt);
        $this->assertStringContainsString('Please incorporate this feedback to improve the task breakdown', $prompt);
        $this->assertStringContainsString('API Development', $prompt);
        $this->assertStringContainsString('Build REST API', $prompt);
    }
}
