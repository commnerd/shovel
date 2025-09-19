<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AITaskResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTitleTest extends TestCase
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

        // Create a test user with proper organization and group setup
        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Add user to default group
        $this->user->groups()->attach($group->id, ['joined_at' => now()]);
    }

    public function test_project_can_be_created_with_title()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Project Title',
            'description' => 'Test project description',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Test Project Title',
            'description' => 'Test project description',
        ]);
    }

    public function test_project_can_be_created_without_title()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => null,
            'description' => 'Test project description',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => null,
            'description' => 'Test project description',
        ]);
    }

    public function test_project_title_is_mass_assignable()
    {
        $project = Project::create([
            'user_id' => $this->user->id,
            'title' => 'Mass Assigned Title',
            'description' => 'Test description',
            'status' => 'active',
        ]);

        $this->assertEquals('Mass Assigned Title', $project->title);
    }

    public function test_ai_generates_title_when_not_provided()
    {
        // Mock the AI manager through the service container
        $mockAIManager = \Mockery::mock(AIManager::class);
        $mockAIManager->shouldReceive('generateTasks')
            ->once()
            ->with('Build a web application', [])
            ->andReturn(AITaskResponse::success(
                tasks: [],
                projectTitle: 'Web Application Development',
                summary: 'A web development project'
            ));

        $this->app->instance('ai', $mockAIManager);

        $defaultGroup = $this->user->getDefaultGroup();

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => '', // Explicitly pass empty title to trigger AI generation
                'description' => 'Build a web application',
                'due_date' => '2025-12-31',
                'group_id' => $defaultGroup->id,
                'tasks' => [],
            ]);

        $response->assertRedirect('/dashboard/projects');

        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertEquals('Web Application Development', $project->title);
    }

    public function test_ai_fallback_title_generation()
    {
        // Mock the AI manager to throw an exception
        $mockAIManager = \Mockery::mock(AIManager::class);
        $mockAIManager->shouldReceive('generateTasks')
            ->once()
            ->andThrow(new \Exception('AI service unavailable'));

        $this->app->instance('ai', $mockAIManager);

        $defaultGroup = $this->user->getDefaultGroup();

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => '', // Explicitly pass empty title to trigger AI generation
                'description' => 'Build a task management system for teams',
                'due_date' => '2025-12-31',
                'group_id' => $defaultGroup->id,
                'tasks' => [],
            ]);

        $response->assertRedirect('/dashboard/projects');

        $project = Project::where('user_id', $this->user->id)->first();
        // Should generate fallback title from description
        $this->assertStringContainsString('Project', $project->title);
        $this->assertStringContainsString('Build', $project->title);
    }

    public function test_user_provided_title_takes_precedence_over_ai()
    {
        // When title is provided, AI should not be called at all
        // No need to mock since it shouldn't be used

        $defaultGroup = $this->user->getDefaultGroup();

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'My Custom Title',
                'description' => 'Build a web application',
                'due_date' => '2025-12-31',
                'group_id' => $defaultGroup->id,
                'tasks' => [],
            ]);

        $response->assertRedirect('/dashboard/projects');

        $project = Project::where('user_id', $this->user->id)->first();
        $this->assertEquals('My Custom Title', $project->title);
    }

    public function test_project_title_validation_max_length()
    {
        $longTitle = str_repeat('a', 256); // Exceeds 255 character limit

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => $longTitle,
                'description' => 'Test description',
                'due_date' => '2025-12-31',
            ]);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_project_title_can_be_updated()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'description' => 'Test description',
        ]);

        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$project->id}", [
                'title' => 'Updated Title',
                'description' => 'Test description',
                'due_date' => '2025-12-31',
                'status' => 'active',
            ]);

        $response->assertRedirect('/dashboard/projects');

        $project->refresh();
        $this->assertEquals('Updated Title', $project->title);
    }

    public function test_project_title_can_be_cleared()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'description' => 'Test description',
        ]);

        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$project->id}", [
                'title' => '',
                'description' => 'Test description',
                'due_date' => '2025-12-31',
                'status' => 'active',
            ]);

        $response->assertRedirect('/dashboard/projects');

        $project->refresh();
        $this->assertEmpty($project->title);
    }
}
