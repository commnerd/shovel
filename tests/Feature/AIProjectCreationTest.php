<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\AIManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AIProjectCreationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $organization = \App\Models\Organization::getDefault();
        $group = $organization->defaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Add user to default group
        $this->user->groups()->attach($group->id, ['joined_at' => now()]);

        // Mock AI configuration
        config([
            'ai.default' => 'cerebras',
            'ai.providers.cerebras' => [
                'api_key' => 'test-key',
                'base_url' => 'https://api.cerebras.ai',
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
        $mockTaskResponse = \App\Services\AI\Contracts\AITaskResponse::success([
            [
                'title' => 'Setup Development Environment',
                'description' => 'Configure development tools and dependencies',
                'status' => 'pending',
            ],
            [
                'title' => 'Design Database Schema',
                'description' => 'Create database tables and relationships',
                'status' => 'pending',
            ],
            [
                'title' => 'Implement Authentication',
                'description' => 'Add user registration and login functionality',
                'status' => 'pending',
            ],
        ]);

        $mockAI->shouldReceive('generateTasks')
            ->withAnyArgs()
            ->andReturn($mockTaskResponse);

        $this->app->instance('ai', $mockAI);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a task management app with Vue.js and Laravel',
                'due_date' => '2025-12-31',
                'group_id' => $this->user->groups->first()->id,
            ]);

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Projects/CreateTasks')
                ->has('projectData')
                ->has('suggestedTasks')
                ->where('projectData.description', 'Build a task management app with Vue.js and Laravel')
                ->where('projectData.due_date', '2025-12-31')
                ->where('aiUsed', true)
                ->whereType('suggestedTasks', 'array')
                ->has('suggestedTasks', 3)
                ->where('suggestedTasks.0.title', 'Setup Development Environment')
            );
    }

    public function test_task_generation_handles_ai_failure_gracefully()
    {
        // Mock AI service to throw an exception
        $mockAI = Mockery::mock(AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->andThrow(new \Exception('AI service unavailable'));

        $this->app->instance('ai', $mockAI);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a web application',
                'due_date' => '2025-12-31',
                'group_id' => $this->user->groups->first()->id,
            ]);

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Projects/CreateTasks')
                ->has('suggestedTasks')
                ->where('aiUsed', false) // Should be false when AI fails
                ->whereType('suggestedTasks', 'array')
                ->where('suggestedTasks.0.title', 'Project Setup & Planning')
            );
    }

    public function test_user_can_create_project_with_ai_generated_tasks()
    {
        $defaultGroup = $this->user->getDefaultGroup();

        $projectData = [
            'title' => 'Mobile App Project',
            'description' => 'Build a mobile application',
            'due_date' => '2025-12-31',
            'group_id' => $defaultGroup->id,
            'tasks' => [
                [
                    'title' => 'Project Setup',
                    'description' => 'Initialize project structure',
                    'status' => 'pending',
                    'sort_order' => 1,
                ],
                [
                    'title' => 'UI Design',
                    'description' => 'Create user interface mockups',
                    'status' => 'pending',
                    'sort_order' => 2,
                ],
                [
                    'title' => 'Backend API',
                    'description' => 'Develop REST API endpoints',
                    'status' => 'pending',
                    'sort_order' => 3,
                ],
            ],
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
            'status' => 'pending',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'UI Design',
            'description' => 'Create user interface mockups',
            'status' => 'pending',
            'sort_order' => 2,
        ]);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Backend API',
            'description' => 'Develop REST API endpoints',
            'status' => 'pending',
            'sort_order' => 3,
        ]);

        // Verify task count
        $this->assertEquals(3, $project->tasks()->count());
    }

    public function test_user_can_create_project_without_tasks()
    {
        $defaultGroup = $this->user->getDefaultGroup();

        $projectData = [
            'title' => 'Simple Project',
            'description' => 'Simple project without tasks',
            'due_date' => '2025-12-31',
            'group_id' => $defaultGroup->id,
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
                'title' => 'Test Project',
                'description' => '', // Empty description
                'due_date' => '2025-12-31',
                'tasks' => [],
            ]);

        $response->assertSessionHasErrors(['description']);

        // Test invalid due date
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'Test Project',
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
                    'status' => 'pending',
                    'sort_order' => 1,
                ],
                [
                    'title' => 'Valid title',
                    'description' => 'Valid description',
                    'status' => 'pending',
                    'sort_order' => 2,
                ],
                [
                    'title' => 'Valid title',
                    'description' => 'Valid description',
                    'status' => 'invalid_status', // Invalid status
                    'sort_order' => 3,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', $projectData);

        $response->assertSessionHasErrors([
            'tasks.0.title',
            'tasks.2.status',
        ]);
    }

    public function test_task_generation_requires_authentication()
    {
        $response = $this->post('/dashboard/projects/create/tasks', [
            'description' => 'Test project',
        ]);

        $response->assertStatus(302); // Redirect to login
    }

    public function test_project_creation_requires_authentication()
    {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Test Project',
            'description' => 'Test project',
            'tasks' => [],
        ]);

        $response->assertStatus(302); // Redirect to login
    }

    public function test_task_generation_validates_input()
    {
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => '', // Empty description
            ]);

        $response->assertStatus(302) // Redirect back with errors
            ->assertSessionHasErrors(['description']);

        // Test with very long description
        $longDescription = str_repeat('a', 1001);
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => $longDescription,
            ]);

        $response->assertStatus(302) // Redirect back with errors
            ->assertSessionHasErrors(['description']);
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
                    'status' => 'pending',
                    'sort_order' => 1,
                ],
            ],
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
