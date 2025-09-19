<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Services\AI\AIManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class TaskPageWorkflowTest extends TestCase
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

    public function test_user_can_access_task_generation_page()
    {
        // Mock AI service
        $mockAI = Mockery::mock(AIManager::class);
        $mockTaskResponse = \App\Services\AI\Contracts\AITaskResponse::success([
            [
                'title' => 'Setup Development Environment',
                'description' => 'Configure development tools',
                'priority' => 'high',
                'status' => 'pending',
            ],
            [
                'title' => 'Design Database Schema',
                'description' => 'Create database structure',
                'priority' => 'high',
                'status' => 'pending',
            ],
        ]);

        $mockAI->shouldReceive('generateTasks')
            ->withAnyArgs()
            ->andReturn($mockTaskResponse);

        $this->app->instance('ai', $mockAI);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a web application',
                'due_date' => '2025-12-31',
                'group_id' => $this->user->groups->first()->id,
            ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->has('projectData')
                ->has('suggestedTasks')
                ->has('aiUsed')
                ->where('projectData.description', 'Build a web application')
                ->where('projectData.due_date', '2025-12-31')
                ->where('aiUsed', true)
                ->whereType('suggestedTasks', 'array')
        );
    }

    public function test_task_page_handles_ai_failure_gracefully()
    {
        // Mock AI service to fail
        $mockAI = Mockery::mock(AIManager::class);
        $mockAI->shouldReceive('generateTasks')
            ->andThrow(new \Exception('AI service unavailable'));

        $this->app->instance('ai', $mockAI);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a simple app',
                'due_date' => '2025-12-31',
                'group_id' => $this->user->groups->first()->id,
            ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->where('aiUsed', false)
                ->where('suggestedTasks.0.title', 'Project Setup & Planning')
                ->whereType('suggestedTasks', 'array')
        );
    }

    public function test_task_page_validates_input()
    {
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => '', // Empty description
                'due_date' => '2025-12-31',
            ]);

        $response->assertStatus(302); // Redirect back with errors
        $response->assertSessionHasErrors(['description']);
    }

    public function test_task_page_requires_authentication()
    {
        $response = $this->post('/dashboard/projects/create/tasks', [
            'description' => 'Test project',
            'due_date' => '2025-12-31',
        ]);

        $response->assertStatus(302); // Redirect to login
    }

    public function test_task_page_handles_regeneration()
    {
        $mockAI = Mockery::mock(AIManager::class);
        $mockTaskResponse = \App\Services\AI\Contracts\AITaskResponse::success([
            [
                'title' => 'Mobile App Setup',
                'description' => 'Initialize mobile development environment',
                'priority' => 'high',
                'status' => 'pending',
            ],
        ]);

        $mockAI->shouldReceive('generateTasks')
            ->times(1)
            ->withAnyArgs()
            ->andReturn($mockTaskResponse);

        $this->app->instance('ai', $mockAI);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a mobile app',
                'due_date' => '2025-12-31',
                'regenerate' => true,
                'group_id' => $this->user->groups->first()->id,
            ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->where('aiUsed', true)
                ->where('suggestedTasks.0.title', 'Mobile App Setup')
        );
    }

    public function test_project_creation_from_task_page_works()
    {
        $defaultGroup = $this->user->getDefaultGroup();

        $projectData = [
            'title' => 'Task Page Project',
            'description' => 'Test project from task page',
            'due_date' => '2025-12-31',
            'group_id' => $defaultGroup->id,
            'tasks' => [
                [
                    'title' => 'Task 1',
                    'description' => 'First task',
                    'priority' => 'high',
                    'status' => 'pending',
                    'sort_order' => 1,
                ],
                [
                    'title' => 'Task 2',
                    'description' => 'Second task',
                    'priority' => 'medium',
                    'status' => 'pending',
                    'sort_order' => 2,
                ],
            ]
        ];

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', $projectData);

        $response->assertRedirect('/dashboard/projects')
            ->assertSessionHas('message', 'Project created successfully with 2 tasks!');

        // Verify project was created
        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertNotNull($project);
        $this->assertEquals('Test project from task page', $project->description);
        $this->assertEquals(2, $project->tasks()->count());
    }

    public function test_task_page_preserves_project_data()
    {
        $mockAI = Mockery::mock(AIManager::class);
        $mockTaskResponse = \App\Services\AI\Contracts\AITaskResponse::success([
            [
                'title' => 'Generated Task',
                'description' => 'AI generated task',
                'priority' => 'medium',
                'status' => 'pending',
            ],
        ]);

        $mockAI->shouldReceive('generateTasks')
            ->with('Complex project with detailed requirements', Mockery::type('array'))
            ->andReturn($mockTaskResponse);

        $this->app->instance('ai', $mockAI);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Complex project with detailed requirements',
                'due_date' => '2025-12-31',
                'group_id' => $this->user->groups->first()->id,
            ]);

        $response->assertInertia(fn ($page) =>
            $page->where('projectData.description', 'Complex project with detailed requirements')
                ->where('projectData.due_date', '2025-12-31')
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
