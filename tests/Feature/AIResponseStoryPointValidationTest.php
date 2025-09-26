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

class AIResponseStoryPointValidationTest extends TestCase
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
            'title' => 'Test Project for AI Response Validation',
            'ai_provider' => 'cerebras',
        ]);
    }

    public function test_ai_response_with_valid_story_points_respects_parent_constraint()
    {
        // Create a parent task with size 'm' (max 8 story points)
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Medium Parent Task',
            'size' => 'm',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response with valid story points (all <= 8)
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
                'initial_story_points' => 2,
                'current_story_points' => 2,
                'story_points_change_count' => 0,
            ],
            [
                'title' => 'Subtask 3',
                'description' => 'Third subtask',
                'status' => 'pending',
                'sort_order' => 3,
                'initial_story_points' => 1,
                'current_story_points' => 1,
                'story_points_change_count' => 0,
            ],
        ]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Valid response');
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

        // Verify that all subtasks have valid story points
        $responseData = $response->json();
        $this->assertCount(3, $responseData['subtasks']);

        foreach ($responseData['subtasks'] as $subtask) {
            $this->assertLessThan(5, $subtask['initial_story_points']);
            $this->assertLessThan(5, $subtask['current_story_points']);
        }
    }

    public function test_ai_response_with_invalid_story_points_exceeds_parent_constraint()
    {
        // Create a parent task with size 's' (max 3 story points)
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Small Parent Task',
            'size' => 's',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response with invalid story points (some > 3)
        $mockResponse = Mockery::mock(AITaskResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getTasks')->andReturn([
            [
                'title' => 'Valid Subtask',
                'description' => 'This subtask respects the constraint',
                'status' => 'pending',
                'sort_order' => 1,
                'initial_story_points' => 2,
                'current_story_points' => 2,
                'story_points_change_count' => 0,
            ],
            [
                'title' => 'Invalid Subtask',
                'description' => 'This subtask exceeds the constraint',
                'status' => 'pending',
                'sort_order' => 2,
                'initial_story_points' => 5, // Exceeds max of 3
                'current_story_points' => 5, // Exceeds max of 3
                'story_points_change_count' => 0,
            ],
        ]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Response with constraint violations');
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

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);

        // Verify that the response includes the constraint violation error
        $responseData = $response->json();
        $this->assertStringContainsString('AI response violates story point constraints', $responseData['error']);
        $this->assertArrayHasKey('violations', $responseData);
        $this->assertCount(1, $responseData['violations']);
        $this->assertStringContainsString('Subtask \'Invalid Subtask\' has 5 story points, but maximum allowed is 2', $responseData['violations'][0]);
    }

    public function test_ai_response_with_extra_large_parent_allows_higher_story_points()
    {
        // Create a parent task with size 'xl' (max 13 story points)
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Extra Large Parent Task',
            'size' => 'xl',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response with high story points (all < 13)
        $mockResponse = Mockery::mock(AITaskResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getTasks')->andReturn([
            [
                'title' => 'Complex Subtask 1',
                'description' => 'A complex subtask',
                'status' => 'pending',
                'sort_order' => 1,
                'initial_story_points' => 8,
                'current_story_points' => 8,
                'story_points_change_count' => 0,
            ],
            [
                'title' => 'Complex Subtask 2',
                'description' => 'Another complex subtask',
                'status' => 'pending',
                'sort_order' => 2,
                'initial_story_points' => 5,
                'current_story_points' => 5,
                'story_points_change_count' => 0,
            ],
        ]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Valid response for XL parent');
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

        // Verify that all subtasks have valid story points for XL parent
        $responseData = $response->json();
        $this->assertCount(2, $responseData['subtasks']);

        foreach ($responseData['subtasks'] as $subtask) {
            $this->assertLessThan(13, $subtask['initial_story_points']);
            $this->assertLessThan(13, $subtask['current_story_points']);
        }
    }

    public function test_ai_response_with_extra_small_parent_limits_story_points()
    {
        // Create a parent task with size 'xs' (max 2 story points)
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Extra Small Parent Task',
            'size' => 'xs',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response with low story points (all < 2)
        $mockResponse = Mockery::mock(AITaskResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getTasks')->andReturn([
            [
                'title' => 'Simple Subtask 1',
                'description' => 'A simple subtask',
                'status' => 'pending',
                'sort_order' => 1,
                'initial_story_points' => 1,
                'current_story_points' => 1,
                'story_points_change_count' => 0,
            ],
            [
                'title' => 'Simple Subtask 2',
                'description' => 'Another simple subtask',
                'status' => 'pending',
                'sort_order' => 2,
                'initial_story_points' => 1,
                'current_story_points' => 1,
                'story_points_change_count' => 0,
            ],
        ]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Valid response for XS parent');
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

        // Verify that all subtasks have valid story points for XS parent
        $responseData = $response->json();
        $this->assertCount(2, $responseData['subtasks']);

        foreach ($responseData['subtasks'] as $subtask) {
            $this->assertLessThan(2, $subtask['initial_story_points']);
            $this->assertLessThan(2, $subtask['current_story_points']);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
