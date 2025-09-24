<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\CurationPrompt;
use App\Jobs\DailyCurationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurationPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the default organization
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
    }

    public function test_curation_prompt_can_be_created(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $prompt = CurationPrompt::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'prompt_text' => 'Test prompt for AI curation',
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4',
            'is_organization_user' => false,
            'task_count' => 5,
        ]);

        $this->assertDatabaseHas('curation_prompts', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'prompt_text' => 'Test prompt for AI curation',
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4',
            'is_organization_user' => false,
            'task_count' => 5,
        ]);

        $this->assertEquals($user->id, $prompt->user_id);
        $this->assertEquals($project->id, $prompt->project_id);
        $this->assertEquals('Test prompt for AI curation', $prompt->prompt_text);
    }

    public function test_curation_prompt_relationships(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $prompt = CurationPrompt::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        // Test user relationship
        $this->assertInstanceOf(User::class, $prompt->user);
        $this->assertEquals($user->id, $prompt->user->id);

        // Test project relationship
        $this->assertInstanceOf(Project::class, $prompt->project);
        $this->assertEquals($project->id, $prompt->project->id);
    }

    public function test_curation_prompt_scopes(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $project1 = Project::factory()->create(['user_id' => $user1->id]);
        $project2 = Project::factory()->create(['user_id' => $user2->id]);

        // Create prompts for different users and projects
        CurationPrompt::factory()->create([
            'user_id' => $user1->id,
            'project_id' => $project1->id,
            'is_organization_user' => false,
        ]);

        CurationPrompt::factory()->create([
            'user_id' => $user2->id,
            'project_id' => $project2->id,
            'is_organization_user' => true,
        ]);

        // Test forUser scope
        $user1Prompts = CurationPrompt::forUser($user1->id)->get();
        $this->assertCount(1, $user1Prompts);
        $this->assertEquals($user1->id, $user1Prompts->first()->user_id);

        // Test forProject scope
        $project1Prompts = CurationPrompt::forProject($project1->id)->get();
        $this->assertCount(1, $project1Prompts);
        $this->assertEquals($project1->id, $project1Prompts->first()->project_id);

        // Test organizationUsers scope
        $orgPrompts = CurationPrompt::organizationUsers()->get();
        $this->assertCount(1, $orgPrompts);
        $this->assertTrue($orgPrompts->first()->is_organization_user);

        // Test individualUsers scope
        $indivPrompts = CurationPrompt::individualUsers()->get();
        $this->assertCount(1, $indivPrompts);
        $this->assertFalse($indivPrompts->first()->is_organization_user);
    }

    public function test_curation_prompt_today_scope(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create a prompt for today
        $todayPrompt = CurationPrompt::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'created_at' => now(),
        ]);

        // Create a prompt for yesterday
        $yesterdayPrompt = CurationPrompt::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'created_at' => now()->subDay(),
        ]);

        // Test today scope
        $todayPrompts = CurationPrompt::today()->get();
        $this->assertCount(1, $todayPrompts);
        $this->assertEquals($todayPrompt->id, $todayPrompts->first()->id);
    }

    public function test_curation_prompt_clear_methods(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user1->id]);

        // Create prompts for different users
        CurationPrompt::factory()->create(['user_id' => $user1->id, 'project_id' => $project->id]);
        CurationPrompt::factory()->create(['user_id' => $user1->id, 'project_id' => $project->id]);
        CurationPrompt::factory()->create(['user_id' => $user2->id, 'project_id' => $project->id]);

        $this->assertDatabaseCount('curation_prompts', 3);

        // Test clearForUser
        $deletedCount = CurationPrompt::clearForUser($user1->id);
        $this->assertEquals(2, $deletedCount);
        $this->assertDatabaseCount('curation_prompts', 1);
        $this->assertDatabaseHas('curation_prompts', ['user_id' => $user2->id]);

        // Test clearAll
        $deletedCount = CurationPrompt::clearAll();
        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseCount('curation_prompts', 0);
    }

    public function test_curation_prompt_accessors(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $longPrompt = str_repeat('This is a long prompt. ', 20); // ~500 characters

        $prompt = CurationPrompt::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'prompt_text' => $longPrompt,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4',
            'is_organization_user' => false,
            'task_count' => 5,
        ]);

        // Test truncated prompt accessor
        $truncated = $prompt->truncated_prompt;
        $this->assertLessThanOrEqual(203, strlen($truncated)); // 200 + '...'
        $this->assertStringEndsWith('...', $truncated);

        // Test prompt length accessor
        $this->assertEquals(strlen($longPrompt), $prompt->prompt_length);
    }

    public function test_daily_curation_job_stores_prompts(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'title' => 'Sample Project',
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4',
        ]);

        // Create some tasks
        $task1 = \App\Models\Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        $task2 = \App\Models\Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        // Mock AI response
        $this->mockAIResponse([
            'suggestions' => [
                [
                    'type' => 'priority',
                    'task_id' => $task1->id,
                    'message' => 'Focus on this task today'
                ]
            ],
            'summary' => 'Test curation summary',
            'focus_areas' => ['priority_tasks']
        ]);

        // Ensure AI is configured
        $this->app->bind('ai', function () {
            return new class {
                public function hasConfiguredProvider() { return true; }
                public function driver($provider) {
                    return app('ai.provider');
                }
            };
        });

        // Run the daily curation job
        $job = new DailyCurationJob($user);
        $job->handle();

        // Verify prompt was stored
        $this->assertDatabaseHas('curation_prompts', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4',
            'is_organization_user' => false,
            'task_count' => 2, // Both tasks should be in the prompt
        ]);

        $prompt = CurationPrompt::where('user_id', $user->id)->first();
        $this->assertNotNull($prompt);
        $this->assertStringContainsString('Sample Project', $prompt->prompt_text);
        $this->assertStringContainsString('Individual user', $prompt->prompt_text);
    }

    public function test_daily_curation_job_clears_previous_prompts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create some existing prompts
        CurationPrompt::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);
        CurationPrompt::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);

        $this->assertDatabaseCount('curation_prompts', 2);

        // Mock AI response
        $this->mockAIResponse([
            'suggestions' => [],
            'summary' => 'No tasks to curate',
            'focus_areas' => []
        ]);

        // Run the daily curation job
        $job = new DailyCurationJob($user);
        $job->handle();

        // Verify previous prompts were cleared and new ones might be created
        // The exact count depends on whether the job creates new prompts
        $this->assertDatabaseCount('curation_prompts', 0);
    }

    /**
     * Mock AI response for testing.
     */
    private function mockAIResponse(array $response): void
    {
        $mockAI = $this->createMock(\App\Services\AI\Contracts\AIProviderInterface::class);
        $mockResponse = $this->createMock(\App\Services\AI\Contracts\AIResponse::class);

        $mockResponse->method('getContent')
            ->willReturn(json_encode($response));

        $mockAI->method('chat')
            ->willReturn($mockResponse);

        $mockAI->method('isConfigured')
            ->willReturn(true);

        $this->app->instance('ai.provider', $mockAI);
    }
}
