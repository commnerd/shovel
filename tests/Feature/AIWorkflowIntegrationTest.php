<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Services\AI\AIManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AIWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Mock AI configuration
        config([
            'ai.default' => 'cerebrus',
            'ai.providers.cerebrus' => [
                'api_key' => 'test-key',
                'base_url' => 'https://api.cerebrus.ai',
                'model' => 'gpt-4',
                'max_tokens' => 4000,
                'temperature' => 0.7,
                'timeout' => 30,
            ],
        ]);
    }

    public function test_complete_ai_powered_project_creation_workflow()
    {
        // Step 1: Mock AI service to return realistic tasks
        $mockAI = Mockery::mock(AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->with('Build a comprehensive e-commerce platform with Vue.js, Laravel, and Stripe integration')
            ->andReturn([
                [
                    'title' => 'Project Setup & Environment Configuration',
                    'description' => 'Set up development environment, install dependencies, configure Laravel and Vue.js, set up database',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Database Design & Migration Setup',
                    'description' => 'Design database schema for users, products, orders, payments. Create and run migrations',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'User Authentication & Authorization',
                    'description' => 'Implement user registration, login, password reset, email verification, role-based access',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Product Catalog Management',
                    'description' => 'Create product CRUD operations, categories, inventory management, product search and filtering',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Shopping Cart & Session Management',
                    'description' => 'Implement shopping cart functionality, session persistence, cart item management',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Stripe Payment Integration',
                    'description' => 'Integrate Stripe payment processing, handle webhooks, manage payment methods',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Order Management System',
                    'description' => 'Create order processing workflow, order history, status tracking, email notifications',
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Admin Dashboard',
                    'description' => 'Build admin interface for managing products, orders, users, and analytics',
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Frontend UI/UX Implementation',
                    'description' => 'Create responsive Vue.js components, product pages, checkout flow, user dashboard',
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Testing & Quality Assurance',
                    'description' => 'Write unit tests, feature tests, integration tests, perform security audits',
                    'priority' => 'low',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Performance Optimization',
                    'description' => 'Optimize database queries, implement caching, CDN setup, image optimization',
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Documentation & Deployment',
                    'description' => 'Create API documentation, deployment guides, set up CI/CD pipeline, production deployment',
                    'priority' => 'low',
                    'status' => 'pending',
                ],
            ]);

        $this->app->instance(AIManager::class, $mockAI);

        // Step 2: User requests task generation
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/projects/generate-tasks', [
                'description' => 'Build a comprehensive e-commerce platform with Vue.js, Laravel, and Stripe integration',
                'due_date' => '2026-06-30',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $responseData = $response->json();
        $suggestedTasks = $responseData['suggested_tasks'];

        // Verify AI generated comprehensive tasks
        $this->assertCount(12, $suggestedTasks);
        $this->assertEquals('Project Setup & Environment Configuration', $suggestedTasks[0]['title']);
        $this->assertEquals('Stripe Payment Integration', $suggestedTasks[5]['title']);
        $this->assertEquals('Documentation & Deployment', $suggestedTasks[11]['title']);

        // Verify task priorities are properly set
        $highPriorityTasks = array_filter($suggestedTasks, fn($task) => $task['priority'] === 'high');
        $mediumPriorityTasks = array_filter($suggestedTasks, fn($task) => $task['priority'] === 'medium');
        $lowPriorityTasks = array_filter($suggestedTasks, fn($task) => $task['priority'] === 'low');

        $this->assertCount(6, $highPriorityTasks);
        $this->assertCount(4, $mediumPriorityTasks);
        $this->assertCount(2, $lowPriorityTasks);

        // Step 3: User modifies some tasks (simulating frontend interaction)
        $modifiedTasks = $suggestedTasks;

        // User adds a custom task
        $modifiedTasks[] = [
            'title' => 'Email Marketing Integration',
            'description' => 'Integrate with Mailchimp or similar service for customer email campaigns',
            'priority' => 'low',
            'status' => 'pending',
            'sort_order' => 13,
        ];

        // User modifies an existing task
        $modifiedTasks[5]['description'] = 'Integrate Stripe payment processing with comprehensive error handling, webhooks, and subscription support';
        $modifiedTasks[5]['priority'] = 'high';

        // User removes a task (Performance Optimization)
        unset($modifiedTasks[10]);
        $modifiedTasks = array_values($modifiedTasks); // Re-index array

        // Update sort orders
        foreach ($modifiedTasks as $index => $task) {
            $modifiedTasks[$index]['sort_order'] = $index + 1;
        }

        // Step 4: User confirms and creates project with modified tasks
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'description' => 'Build a comprehensive e-commerce platform with Vue.js, Laravel, and Stripe integration',
                'due_date' => '2026-06-30',
                'tasks' => $modifiedTasks,
            ]);

        $response->assertRedirect('/dashboard/projects')
            ->assertSessionHas('message', 'Project created successfully with 12 tasks!');

        // Step 5: Verify project was created correctly
        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertNotNull($project);
        $this->assertEquals('Build a comprehensive e-commerce platform with Vue.js, Laravel, and Stripe integration', $project->description);
        $this->assertEquals('2026-06-30', $project->due_date->format('Y-m-d'));
        $this->assertEquals('active', $project->status);

        // Step 6: Verify all tasks were created with correct details
        $this->assertEquals(12, $project->tasks()->count());

        // Verify specific tasks
        $setupTask = $project->tasks()->where('title', 'Project Setup & Environment Configuration')->first();
        $this->assertNotNull($setupTask);
        $this->assertEquals('high', $setupTask->priority);
        $this->assertEquals('pending', $setupTask->status);
        $this->assertEquals(1, $setupTask->sort_order);

        $stripeTask = $project->tasks()->where('title', 'Stripe Payment Integration')->first();
        $this->assertNotNull($stripeTask);
        $this->assertEquals('high', $stripeTask->priority); // Modified from medium to high
        $this->assertStringContainsString('comprehensive error handling', $stripeTask->description);

        $customTask = $project->tasks()->where('title', 'Email Marketing Integration')->first();
        $this->assertNotNull($customTask);
        $this->assertEquals('low', $customTask->priority);
        $this->assertStringContainsString('Mailchimp', $customTask->description);

        // Verify removed task doesn't exist
        $removedTask = $project->tasks()->where('title', 'Performance Optimization')->first();
        $this->assertNull($removedTask);

        // Step 7: Verify task sort orders are correct
        $orderedTasks = $project->tasks()->orderBy('sort_order')->get();
        $this->assertEquals('Project Setup & Environment Configuration', $orderedTasks[0]->title);
        $this->assertEquals('Email Marketing Integration', $orderedTasks[11]->title);

        // Verify all sort orders are sequential
        foreach ($orderedTasks as $index => $task) {
            $this->assertEquals($index + 1, $task->sort_order);
        }

        // Step 8: Test project can be accessed via normal routes
        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertStatus(200);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$project->id}/tasks");

        $response->assertStatus(200);
    }

    public function test_workflow_handles_ai_failure_gracefully()
    {
        // Mock AI service to fail
        $mockAI = Mockery::mock(AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->andThrow(new \Exception('AI service temporarily unavailable'));

        $this->app->instance(AIManager::class, $mockAI);

        // Step 1: Request task generation (should fail gracefully)
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/projects/generate-tasks', [
                'description' => 'Build a simple blog application',
                'due_date' => '2025-12-31',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $responseData = $response->json();
        $fallbackTasks = $responseData['suggested_tasks'];

        // Should return fallback tasks
        $this->assertGreaterThan(0, count($fallbackTasks));
        $this->assertEquals('Project Setup & Planning', $fallbackTasks[0]['title']);
        $this->assertStringContainsString('Build a simple blog application', $fallbackTasks[0]['description']);

        // Step 2: User can still create project with fallback tasks
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'description' => 'Build a simple blog application',
                'due_date' => '2025-12-31',
                'tasks' => $fallbackTasks,
            ]);

        $response->assertRedirect('/dashboard/projects')
            ->assertSessionHas('message');

        // Verify project and tasks were created
        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertNotNull($project);
        $this->assertEquals(count($fallbackTasks), $project->tasks()->count());
    }

    public function test_workflow_with_empty_task_list()
    {
        // User decides not to use any AI-generated tasks
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'description' => 'Simple project without tasks',
                'due_date' => '2025-12-31',
                'tasks' => [],
            ]);

        $response->assertRedirect('/dashboard/projects')
            ->assertSessionHas('message', 'Project created successfully with 0 tasks!');

        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertNotNull($project);
        $this->assertEquals(0, $project->tasks()->count());
    }

    public function test_concurrent_task_generation_requests()
    {
        // Mock AI service
        $mockAI = Mockery::mock(AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->times(2)
            ->andReturn([
                [
                    'title' => 'Task 1',
                    'description' => 'Description 1',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
            ]);

        $this->app->instance(AIManager::class, $mockAI);

        // Simulate concurrent requests
        $response1 = $this->actingAs($this->user)
            ->postJson('/dashboard/projects/generate-tasks', [
                'description' => 'Project 1',
                'due_date' => '2025-12-31',
            ]);

        $response2 = $this->actingAs($this->user)
            ->postJson('/dashboard/projects/generate-tasks', [
                'description' => 'Project 2',
                'due_date' => '2025-12-31',
            ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Both should succeed
        $this->assertTrue($response1->json()['success']);
        $this->assertTrue($response2->json()['success']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
