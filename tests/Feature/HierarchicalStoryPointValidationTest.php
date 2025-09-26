<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Facades\AI;
use App\Services\AI\Contracts\AITaskResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class HierarchicalStoryPointValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\OrganizationSeeder::class);

        $this->organization = Organization::getDefault();
        $group = $this->organization->createDefaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'title' => 'Test Project for Hierarchical Story Points',
            'ai_provider' => 'cerebras',
        ]);
    }

    public function test_task_model_size_to_max_story_points_mapping()
    {
        $this->assertEquals(2, Task::SIZE_TO_MAX_STORY_POINTS['xs']);
        $this->assertEquals(3, Task::SIZE_TO_MAX_STORY_POINTS['s']);
        $this->assertEquals(5, Task::SIZE_TO_MAX_STORY_POINTS['m']);
        $this->assertEquals(8, Task::SIZE_TO_MAX_STORY_POINTS['l']);
        $this->assertEquals(13, Task::SIZE_TO_MAX_STORY_POINTS['xl']);
    }

    public function test_get_max_story_points_for_subtasks_method()
    {
        // Test with different T-shirt sizes
        $xsTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'size' => 'xs',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $sTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'size' => 's',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $mTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'size' => 'm',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $lTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'size' => 'l',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $xlTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'size' => 'xl',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $this->assertEquals(2, $xsTask->getMaxStoryPointsForSubtasks());
        $this->assertEquals(3, $sTask->getMaxStoryPointsForSubtasks());
        $this->assertEquals(5, $mTask->getMaxStoryPointsForSubtasks());
        $this->assertEquals(8, $lTask->getMaxStoryPointsForSubtasks());
        $this->assertEquals(13, $xlTask->getMaxStoryPointsForSubtasks());
    }

    public function test_get_max_story_points_returns_null_for_subtasks()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'size' => 'm',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'depth' => 1,
            'size' => null,
        ]);

        $this->assertNull($subtask->getMaxStoryPointsForSubtasks());
    }

    public function test_get_max_story_points_returns_null_for_tasks_without_size()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'size' => null,
            'parent_id' => null,
            'depth' => 0,
        ]);

        $this->assertNull($task->getMaxStoryPointsForSubtasks());
    }

    public function test_ai_prompt_includes_parent_task_size_constraint()
    {
        // Create a parent task with size 'm'
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task with Medium Size',
            'size' => 'm',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response
        $mockResponse = Mockery::mock(AITaskResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getTasks')->andReturn([
            [
                'title' => 'Subtask 1',
                'description' => 'First subtask',
                'status' => 'pending',
                'sort_order' => 1,
                'initial_story_points' => 3,
                'current_story_points' => 3,
                'story_points_change_count' => 0,
            ],
            [
                'title' => 'Subtask 2',
                'description' => 'Second subtask',
                'status' => 'pending',
                'sort_order' => 2,
                'initial_story_points' => 3,
                'current_story_points' => 3,
                'story_points_change_count' => 0,
            ],
        ]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Test summary');
        $mockResponse->shouldReceive('getProblems')->andReturn([]);
        $mockResponse->shouldReceive('getSuggestions')->andReturn([]);

        // Mock AI facade
        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('breakdownTask')->andReturn($mockResponse);

        AI::shouldReceive('getAvailableProviders')->andReturn(['cerebras' => 'Cerebras']);
        AI::shouldReceive('driver')
            ->with('cerebras')
            ->andReturn($mockDriver);

        // Make request to generate task breakdown
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'parent_task_id' => $parentTask->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify that the AI was called with the correct context including parent task size
        $mockDriver->shouldHaveReceived('breakdownTask')
            ->with(
                'Test Task',
                'Test description',
                Mockery::on(function ($context) {
                    return isset($context['parent_task']['size']) &&
                           $context['parent_task']['size'] === 'm';
                }),
                []
            );
    }

    public function test_ai_prompt_does_not_include_constraint_when_no_parent_task()
    {
        // Mock AI response
        $mockResponse = Mockery::mock(AITaskResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getTasks')->andReturn([]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Test summary');
        $mockResponse->shouldReceive('getProblems')->andReturn([]);
        $mockResponse->shouldReceive('getSuggestions')->andReturn([]);

        // Mock AI facade
        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('breakdownTask')->andReturn($mockResponse);

        AI::shouldReceive('getAvailableProviders')->andReturn(['cerebras' => 'Cerebras']);
        AI::shouldReceive('driver')
            ->with('cerebras')
            ->andReturn($mockDriver);

        // Make request to generate task breakdown without parent task
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify that the AI was called with context that doesn't include parent task
        $mockDriver->shouldHaveReceived('breakdownTask')
            ->with(
                'Test Task',
                'Test description',
                Mockery::on(function ($context) {
                    return !isset($context['parent_task']) || $context['parent_task'] === null;
                }),
                []
            );
    }

    public function test_ai_prompt_does_not_include_constraint_when_parent_has_no_size()
    {
        // Create a parent task without size
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task without Size',
            'size' => null,
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response
        $mockResponse = Mockery::mock(AITaskResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getTasks')->andReturn([]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Test summary');
        $mockResponse->shouldReceive('getProblems')->andReturn([]);
        $mockResponse->shouldReceive('getSuggestions')->andReturn([]);

        // Mock AI facade
        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('breakdownTask')->andReturn($mockResponse);

        AI::shouldReceive('getAvailableProviders')->andReturn(['cerebras' => 'Cerebras']);
        AI::shouldReceive('driver')
            ->with('cerebras')
            ->andReturn($mockDriver);

        // Make request to generate task breakdown
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'parent_task_id' => $parentTask->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify that the AI was called with context that includes parent task but no size constraint
        $mockDriver->shouldHaveReceived('breakdownTask')
            ->with(
                'Test Task',
                'Test description',
                Mockery::on(function ($context) {
                    return isset($context['parent_task']['title']) &&
                           $context['parent_task']['title'] === 'Parent Task without Size' &&
                           (!isset($context['parent_task']['size']) || $context['parent_task']['size'] === null);
                }),
                []
            );
    }

    public function test_cerebras_provider_get_max_story_points_for_size()
    {
        $provider = new \App\Services\AI\Providers\CerebrasProvider([]);

        // Use reflection to access the protected promptService property
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('promptService');
        $property->setAccessible(true);
        $promptService = $property->getValue($provider);

        $this->assertEquals(2, $promptService->getMaxStoryPointsForSize('xs'));
        $this->assertEquals(3, $promptService->getMaxStoryPointsForSize('s'));
        $this->assertEquals(5, $promptService->getMaxStoryPointsForSize('m'));
        $this->assertEquals(8, $promptService->getMaxStoryPointsForSize('l'));
        $this->assertEquals(13, $promptService->getMaxStoryPointsForSize('xl'));
        $this->assertNull($promptService->getMaxStoryPointsForSize('invalid'));
    }

    public function test_openai_provider_get_max_story_points_for_size()
    {
        $provider = new \App\Services\AI\Providers\OpenAIProvider(['api_key' => 'test-key']);

        // Use reflection to access the protected promptService property
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('promptService');
        $property->setAccessible(true);
        $promptService = $property->getValue($provider);

        $this->assertEquals(2, $promptService->getMaxStoryPointsForSize('xs'));
        $this->assertEquals(3, $promptService->getMaxStoryPointsForSize('s'));
        $this->assertEquals(5, $promptService->getMaxStoryPointsForSize('m'));
        $this->assertEquals(8, $promptService->getMaxStoryPointsForSize('l'));
        $this->assertEquals(13, $promptService->getMaxStoryPointsForSize('xl'));
        $this->assertNull($promptService->getMaxStoryPointsForSize('invalid'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
