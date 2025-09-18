<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Services\AI\AIManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AIProjectCreationTest extends TestCase
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

    public function test_user_can_generate_task_suggestions()
    {
        // Mock AI service to return predictable tasks
        $mockAI = Mockery::mock(AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->with('Build a task management app with Vue.js and Laravel')
            ->andReturn([
                [
                    'title' => 'Setup Development Environment',
                    'description' => 'Configure development tools and dependencies',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Design Database Schema',
                    'description' => 'Create database tables and relationships',
                    'priority' => 'high',
                    'status' => 'pending',
                ],
                [
                    'title' => 'Implement Authentication',
                    'description' => 'Add user registration and login functionality',
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
            ]);

        $this->app->instance(AIManager::class, $mockAI);

        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/projects/generate-tasks', [
                'description' => 'Build a task management app with Vue.js and Laravel',
                'due_date' => '2025-12-31',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'project_data' => [
                    'description' => 'Build a task management app with Vue.js and Laravel',
                    'due_date' => '2025-12-31',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'suggested_tasks' => [
                    '*' => [
                        'title',
                        'description',
                        'priority',
                        'status',
                        'sort_order',
                    ]
                ],
                'project_data',
            ]);

        $responseData = $response->json();
        $this->assertCount(3, $responseData['suggested_tasks']);
        $this->assertEquals('Setup Development Environment', $responseData['suggested_tasks'][0]['title']);
        $this->assertEquals('high', $responseData['suggested_tasks'][0]['priority']);
    }

    public function test_task_generation_handles_ai_failure_gracefully()
    {
        // Mock AI service to throw an exception
        $mockAI = Mockery::mock(AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->andThrow(new \Exception('AI service unavailable'));

        $this->app->instance(AIManager::class, $mockAI);

        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/projects/generate-tasks', [
                'description' => 'Build a web application',
                'due_date' => '2025-12-31',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Should return fallback tasks
        $responseData = $response->json();
        $this->assertArrayHasKey('suggested_tasks', $responseData);
        $this->assertGreaterThan(0, count($responseData['suggested_tasks']));

        // Check fallback task structure
        $fallbackTask = $responseData['suggested_tasks'][0];
        $this->assertEquals('Project Setup & Planning', $fallbackTask['title']);
        $this->assertStringContainsString('Build a web application', $fallbackTask['description']);
    }

    public function test_user_can_create_project_with_ai_generated_tasks()
    {
        $projectData = [
            'description' => 'Build a mobile application',
            'due_date' => '2025-12-31',
            'tasks' => [
                [
                    'title' => 'Project Setup',
                    'description' => 'Initialize project structure',
                    'priority' => 'high',
                    'status' => 'pending',
                    'sort_order' => 1,
                ],
                [
                    'title' => 'UI Design',
                    'description' => 'Create user interface mockups',
                    'priority' => 'medium',
                    'status' => 'pending',
                    'sort_order' => 2,
                ],
                [
                    'title' => 'Backend API',
                    'description' => 'Develop REST API endpoints',
                    'priority' => 'high',
                    'status' => 'pending',
                    'sort_order' => 3,
                ],
            ]
        ];

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', $projectData);

        $response->assertRedirect('/dashboard/projects')
            ->assertSessionHas('message', 'Project created successfully with 3 tasks!');

        // Verify project was created
        $this->assertDatabaseHas('projects', [
            'user_id' => $this->user->id,
            'description' => 'Build a mobile application',
            'status' => 'active',
        ]);

        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertNotNull($project);
        $this->assertEquals('2025-12-31', $project->due_date->format('Y-m-d'));

        // Verify tasks were created
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Project Setup',
            'description' => 'Initialize project structure',
            'priority' => 'high',
            'status' => 'pending',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'UI Design',
            'description' => 'Create user interface mockups',
            'priority' => 'medium',
            'status' => 'pending',
            'sort_order' => 2,
        ]);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Backend API',
            'description' => 'Develop REST API endpoints',
            'priority' => 'high',
            'status' => 'pending',
            'sort_order' => 3,
        ]);

        // Verify task count
        $this->assertEquals(3, $project->tasks()->count());
    }

    public function test_user_can_create_project_without_tasks()
    {
        $projectData = [
            'description' => 'Simple project without tasks',
            'due_date' => '2025-12-31',
            'tasks' => [],
        ];

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', $projectData);

        $response->assertRedirect('/dashboard/projects')
            ->assertSessionHas('message', 'Project created successfully with 0 tasks!');

        // Verify project was created
        $this->assertDatabaseHas('projects', [
            'user_id' => $this->user->id,
            'description' => 'Simple project without tasks',
            'status' => 'active',
        ]);

        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertEquals(0, $project->tasks()->count());
    }

    public function test_project_creation_validates_required_fields()
    {
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'description' => '', // Empty description
                'due_date' => '2025-12-31',
                'tasks' => [],
            ]);

        $response->assertSessionHasErrors(['description']);

        // Test invalid due date
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'description' => 'Valid project description',
                'due_date' => '2020-01-01', // Past date
                'tasks' => [],
            ]);

        $response->assertSessionHasErrors(['due_date']);
    }

    public function test_project_creation_validates_task_fields()
    {
        $projectData = [
            'description' => 'Project with invalid tasks',
            'due_date' => '2025-12-31',
            'tasks' => [
                [
                    'title' => '', // Empty title
                    'description' => 'Valid description',
                    'priority' => 'high',
                    'status' => 'pending',
                    'sort_order' => 1,
                ],
                [
                    'title' => 'Valid title',
                    'description' => 'Valid description',
                    'priority' => 'invalid_priority', // Invalid priority
                    'status' => 'pending',
                    'sort_order' => 2,
                ],
                [
                    'title' => 'Valid title',
                    'description' => 'Valid description',
                    'priority' => 'high',
                    'status' => 'invalid_status', // Invalid status
                    'sort_order' => 3,
                ],
            ]
        ];

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', $projectData);

        $response->assertSessionHasErrors([
            'tasks.0.title',
            'tasks.1.priority',
            'tasks.2.status',
        ]);
    }

    public function test_task_generation_requires_authentication()
    {
        $response = $this->postJson('/dashboard/projects/generate-tasks', [
            'description' => 'Test project',
        ]);

        $response->assertStatus(401); // Unauthorized
    }

    public function test_project_creation_requires_authentication()
    {
        $response = $this->post('/dashboard/projects', [
            'description' => 'Test project',
            'tasks' => [],
        ]);

        $response->assertStatus(302); // Redirect to login
    }

    public function test_task_generation_validates_input()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/projects/generate-tasks', [
                'description' => '', // Empty description
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);

        // Test with very long description
        $longDescription = str_repeat('a', 1001);
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/projects/generate-tasks', [
                'description' => $longDescription,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_database_transaction_rollback_on_task_creation_failure()
    {
        // Create a project data with invalid task data that will cause a database error
        $projectData = [
            'description' => 'Test project',
            'due_date' => '2025-12-31',
            'tasks' => [
                [
                    'title' => str_repeat('a', 300), // Too long for database field
                    'description' => 'Valid description',
                    'priority' => 'high',
                    'status' => 'pending',
                    'sort_order' => 1,
                ],
            ]
        ];

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', $projectData);

        // Should not create the project if task creation fails
        $this->assertEquals(0, Project::where('user_id', $this->user->id)->count());
        $this->assertEquals(0, Task::count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
