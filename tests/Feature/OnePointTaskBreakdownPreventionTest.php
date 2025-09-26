<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnePointTaskBreakdownPreventionTest extends TestCase
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
            'title' => 'Test Project for 1-Point Task Prevention',
            'ai_provider' => 'cerebras',
        ]);
    }

    public function test_cannot_break_down_task_with_1_story_point()
    {
        // Create a task with 1 story point
        $onePointTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task with 1 Story Point',
            'current_story_points' => 1,
            'initial_story_points' => 1,
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Attempt to break down the task
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Breakdown',
                'description' => 'Test description',
                'parent_task_id' => $onePointTask->id,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'error' => 'Tasks with 1 story point cannot be broken down further. They are already at the smallest meaningful size.',
        ]);
    }

    public function test_can_break_down_task_with_more_than_1_story_point()
    {
        // Create a task with 2 story points
        $twoPointTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task with 2 Story Points',
            'current_story_points' => 2,
            'initial_story_points' => 2,
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response
        $mockResponse = \Mockery::mock(\App\Services\AI\Contracts\AITaskResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getTasks')->andReturn([
            [
                'title' => 'Subtask 1',
                'description' => 'First subtask',
                'status' => 'pending',
                'sort_order' => 1,
                'initial_story_points' => 1,
                'current_story_points' => 1,
                'story_points_change_count' => 0,
            ],
        ]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Test summary');
        $mockResponse->shouldReceive('getProblems')->andReturn([]);
        $mockResponse->shouldReceive('getSuggestions')->andReturn([]);

        // Mock AI facade
        $mockDriver = \Mockery::mock();
        $mockDriver->shouldReceive('breakdownTask')->andReturn($mockResponse);

        \App\Services\AI\Facades\AI::shouldReceive('getAvailableProviders')->andReturn(['cerebras' => 'Cerebras']);
        \App\Services\AI\Facades\AI::shouldReceive('driver')
            ->with('cerebras')
            ->andReturn($mockDriver);

        // Attempt to break down the task
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Breakdown',
                'description' => 'Test description',
                'parent_task_id' => $twoPointTask->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_cannot_break_down_task_with_null_story_points()
    {
        // Create a task without story points (top-level task with T-shirt size)
        $topLevelTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Top Level Task',
            'current_story_points' => null,
            'initial_story_points' => null,
            'size' => 'm',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response
        $mockResponse = \Mockery::mock(\App\Services\AI\Contracts\AITaskResponse::class);
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
        ]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Test summary');
        $mockResponse->shouldReceive('getProblems')->andReturn([]);
        $mockResponse->shouldReceive('getSuggestions')->andReturn([]);

        // Mock AI facade
        $mockDriver = \Mockery::mock();
        $mockDriver->shouldReceive('breakdownTask')->andReturn($mockResponse);

        \App\Services\AI\Facades\AI::shouldReceive('getAvailableProviders')->andReturn(['cerebras' => 'Cerebras']);
        \App\Services\AI\Facades\AI::shouldReceive('driver')
            ->with('cerebras')
            ->andReturn($mockDriver);

        // Attempt to break down the task (should work since it's not 1 story point)
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Breakdown',
                'description' => 'Test description',
                'parent_task_id' => $topLevelTask->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_error_message_is_clear_and_helpful()
    {
        // Create a task with 1 story point
        $onePointTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task with 1 Story Point',
            'current_story_points' => 1,
            'initial_story_points' => 1,
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Attempt to break down the task
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Breakdown',
                'description' => 'Test description',
                'parent_task_id' => $onePointTask->id,
            ]);

        $response->assertStatus(400);

        $responseData = $response->json();
        $this->assertStringContainsString('1 story point', $responseData['error']);
        $this->assertStringContainsString('cannot be broken down', $responseData['error']);
        $this->assertStringContainsString('smallest meaningful size', $responseData['error']);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

