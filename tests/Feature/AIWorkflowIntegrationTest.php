<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AITaskResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AIWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $organization = \App\Models\Organization::getDefault();
        $group = $organization->defaultGroup();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Add user to default group
        $this->user->groups()->attach($group->id, ['joined_at' => now()]);
    }

    public function test_complete_ai_powered_project_creation_workflow()
    {
        // Mock AI service to return realistic tasks
        $mockTaskResponse = AITaskResponse::success([
            [
                'title' => 'Project Setup & Environment Configuration',
                'description' => 'Set up development environment, install dependencies',
                'priority' => 'high',
                'status' => 'pending',
            ],
            [
                'title' => 'Database Design & Migration Setup',
                'description' => 'Design database schema for e-commerce',
                'priority' => 'high',
                'status' => 'pending',
            ],
            [
                'title' => 'Stripe Payment Integration',
                'description' => 'Integrate Stripe payment processing',
                'priority' => 'high',
                'status' => 'pending',
            ],
        ]);

        $mockAI = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->withAnyArgs()
            ->andReturn($mockTaskResponse);

        $this->app->instance('ai', $mockAI);

        // Step 1: User requests task generation
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a comprehensive e-commerce platform',
                'due_date' => '2026-06-30',
                'group_id' => $this->user->groups->first()->id,
            ]);

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Projects/CreateTasks')
                ->has('suggestedTasks', 3)
                ->where('suggestedTasks.0.title', 'Project Setup & Environment Configuration')
                ->where('suggestedTasks.2.title', 'Stripe Payment Integration')
                ->where('aiUsed', true)
            );

        // Step 2: User creates the project with tasks
        $defaultGroup = $this->user->getDefaultGroup();

        $projectResponse = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'E-Commerce Platform',
                'description' => 'Build a comprehensive e-commerce platform',
                'due_date' => '2026-06-30',
                'group_id' => $defaultGroup->id,
                'tasks' => [
                    [
                        'title' => 'Project Setup & Environment Configuration',
                        'description' => 'Set up development environment',
                        'priority' => 'high',
                        'status' => 'pending',
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Database Design & Migration Setup',
                        'description' => 'Design database schema',
                        'priority' => 'high',
                        'status' => 'pending',
                        'sort_order' => 2,
                    ],
                ],
            ]);

        $projectResponse->assertStatus(302)
            ->assertRedirect('/dashboard/projects');

        // Step 3: Verify project and tasks were created
        $this->assertDatabaseHas('projects', [
            'user_id' => $this->user->id,
            'description' => 'Build a comprehensive e-commerce platform',
            'status' => 'active',
        ]);

        $project = $this->user->projects()->first();
        $this->assertEquals(2, $project->tasks()->count());
    }

    public function test_workflow_handles_ai_failure_gracefully()
    {
        // Mock AI failure
        $failedResponse = AITaskResponse::failed('API connection failed');

        $this->mock(AIManager::class, function ($mock) use ($failedResponse) {
            $mock->shouldReceive('generateTasks')
                ->andReturn($failedResponse);
        });

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a simple blog application',
                'due_date' => '2025-12-31',
            ]);

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Projects/CreateTasks')
                ->has('suggestedTasks') // Should have fallback tasks
                ->where('aiUsed', false)
            );
    }

    public function test_workflow_with_empty_task_list()
    {
        $defaultGroup = $this->user->getDefaultGroup();

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'Simple Project',
                'description' => 'Simple project with no tasks',
                'due_date' => '2025-12-31',
                'group_id' => $defaultGroup->id,
                'tasks' => [],
            ]);

        $response->assertStatus(302)
            ->assertRedirect('/dashboard/projects');

        $this->assertDatabaseHas('projects', [
            'user_id' => $this->user->id,
            'description' => 'Simple project with no tasks',
        ]);

        $project = $this->user->projects()->first();
        $this->assertEquals(0, $project->tasks()->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
