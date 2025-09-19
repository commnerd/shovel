<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AITaskResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTitleFeatureTest extends TestCase
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
    }

    public function test_create_project_form_includes_title_field()
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects/create');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/Create')
            );
    }

    public function test_project_creation_with_title_via_ai_tasks_page()
    {
        // Mock AI service
        $mockAIManager = \Mockery::mock(AIManager::class);
        $mockAIManager->shouldReceive('generateTasks')
            ->once()
            ->andReturn(AITaskResponse::success(
                tasks: [
                    [
                        'title' => 'Setup Development Environment',
                        'description' => 'Install tools and dependencies',
                        'priority' => 'high',
                        'status' => 'pending',
                    ],
                ],
                projectTitle: 'E-Commerce Platform',
                summary: 'Building a comprehensive e-commerce solution'
            ));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'title' => 'My Custom Title',
                'description' => 'Build an e-commerce platform',
                'due_date' => '2025-12-31',
            ]);

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/CreateTasks')
                ->has('projectData.title')
                ->where('projectData.title', 'My Custom Title')
                ->has('suggestedTasks')
            );
    }

    public function test_project_creation_without_title_uses_ai_generated()
    {
        // Mock AI service
        $mockAIManager = \Mockery::mock(AIManager::class);
        $mockAIManager->shouldReceive('generateTasks')
            ->once()
            ->andReturn(AITaskResponse::success(
                tasks: [
                    [
                        'title' => 'Setup Development Environment',
                        'description' => 'Install tools and dependencies',
                        'priority' => 'high',
                        'status' => 'pending',
                    ],
                ],
                projectTitle: 'AI Generated Title',
                summary: 'AI analysis of the project'
            ));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a web application',
                'due_date' => '2025-12-31',
            ]);

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/CreateTasks')
                ->has('projectData.title')
                ->where('projectData.title', 'AI Generated Title')
            );
    }

    public function test_project_edit_page_displays_title_field()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Project Title',
            'description' => 'Test description',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$project->id}/edit");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/Edit')
                ->has('project.title')
                ->where('project.title', 'Test Project Title')
            );
    }

    public function test_project_update_with_title()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'description' => 'Original description',
        ]);

        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$project->id}", [
                'title' => 'Updated Title',
                'description' => 'Updated description',
                'due_date' => '2025-12-31',
                'status' => 'active',
            ]);

        $response->assertRedirect('/dashboard/projects');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);
    }

    public function test_projects_index_displays_titles()
    {
        $project1 = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Project',
            'description' => 'First description',
        ]);

        $project2 = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => null,
            'description' => 'Second description',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/Index')
                ->has('projects', 2)
                ->where('projects.0.title', 'First Project')
                ->where('projects.1.title', null)
            );
    }

    public function test_project_creation_stores_with_title()
    {
        // Mock AI manager for title generation
        $mockAIManager = \Mockery::mock(AIManager::class);
        $mockAIManager->shouldReceive('generateTasks')
            ->once()
            ->andReturn(AITaskResponse::success(
                tasks: [],
                projectTitle: 'AI Generated Project Title'
            ));

        $this->app->instance('ai', $mockAIManager);

        $defaultGroup = $this->user->getDefaultGroup();

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => '', // Explicitly pass empty title to trigger AI generation
                'description' => 'Build a task management system',
                'due_date' => '2025-12-31',
                'group_id' => $defaultGroup->id,
                'tasks' => [
                    [
                        'title' => 'Setup Environment',
                        'description' => 'Configure development environment',
                        'status' => 'pending',
                        'priority' => 'high',
                        'sort_order' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect('/dashboard/projects');

        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertNotNull($project);
        $this->assertEquals('AI Generated Project Title', $project->title);
        $this->assertEquals('Build a task management system', $project->description);
    }

    public function test_title_validation_errors()
    {
        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'title' => str_repeat('a', 256), // Too long
                'description' => 'Valid description',
                'due_date' => '2025-12-31',
            ]);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_ai_task_generation_includes_title_in_schema()
    {
        // Mock AI service to verify schema includes project_title
        $mockAIManager = \Mockery::mock(AIManager::class);
        $mockAIManager->shouldReceive('generateTasks')
            ->once()
            ->withAnyArgs()
            ->andReturn(AITaskResponse::success(
                tasks: [],
                projectTitle: 'Generated Title'
            ));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects/create/tasks', [
                'description' => 'Build a web app',
                'due_date' => '2025-12-31',
                'group_id' => $this->user->groups->first()->id,
            ]);

        $response->assertOk();
    }

    public function test_project_creation_without_ai_uses_fallback_title()
    {
        // Mock AI manager to fail
        $mockAIManager = \Mockery::mock(AIManager::class);
        $mockAIManager->shouldReceive('generateTasks')
            ->once()
            ->andThrow(new \Exception('AI service unavailable'));

        $this->app->instance('ai', $mockAIManager);

        $defaultGroup = $this->user->getDefaultGroup();

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => '', // Explicitly pass empty title to trigger AI generation
                'description' => 'Build mobile app for iOS',
                'due_date' => '2025-12-31',
                'group_id' => $defaultGroup->id,
                'tasks' => [],
            ]);

        $response->assertRedirect('/dashboard/projects');

        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertNotNull($project);
        $this->assertNotEmpty($project->title);
        $this->assertStringContainsString('Project', $project->title);
    }
}
