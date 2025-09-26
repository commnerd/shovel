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

class AIResponseValidationTest extends TestCase
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

    public function test_backend_rejects_ai_response_with_violating_story_points()
    {
        // Create a parent task with size 's' (max 3 story points)
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Small Parent Task',
            'size' => 's',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response with violating story points (5 points > max 3)
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
                'title' => 'Violating Subtask',
                'description' => 'This subtask violates the constraint',
                'status' => 'pending',
                'sort_order' => 2,
                'initial_story_points' => 5, // Violates max of 3
                'current_story_points' => 5, // Violates max of 3
                'story_points_change_count' => 0,
            ],
        ]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Response with violations');
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
        $response->assertJson([
            'success' => false,
        ]);
        
        $responseData = $response->json();
        $this->assertStringContainsString('AI response violates story point constraints', $responseData['error']);
        $this->assertStringContainsString("Subtask 'Violating Subtask' has 5 story points, but maximum allowed is 2", $responseData['error']);
        $this->assertArrayHasKey('violations', $responseData);
        $this->assertCount(1, $responseData['violations']);
    }

    public function test_backend_accepts_ai_response_with_valid_story_points()
    {
        // Create a parent task with size 'm' (max 5 story points)
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Medium Parent Task',
            'size' => 'm',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response with valid story points (all < 5)
        $mockResponse = Mockery::mock(AITaskResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getTasks')->andReturn([
            [
                'title' => 'Valid Subtask 1',
                'description' => 'This subtask respects the constraint',
                'status' => 'pending',
                'sort_order' => 1,
                'initial_story_points' => 3,
                'current_story_points' => 3,
                'story_points_change_count' => 0,
            ],
            [
                'title' => 'Valid Subtask 2',
                'description' => 'This subtask also respects the constraint',
                'status' => 'pending',
                'sort_order' => 2,
                'initial_story_points' => 2,
                'current_story_points' => 2,
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
    }

    public function test_backend_validation_handles_multiple_violations()
    {
        // Create a parent task with size 'xs' (max 2 story points)
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Extra Small Parent Task',
            'size' => 'xs',
            'parent_id' => null,
            'depth' => 0,
        ]);

        // Mock AI response with multiple violations
        $mockResponse = Mockery::mock(AITaskResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getTasks')->andReturn([
            [
                'title' => 'Violating Subtask 1',
                'description' => 'This subtask violates the constraint',
                'status' => 'pending',
                'sort_order' => 1,
                'initial_story_points' => 3, // Violates max of 2
                'current_story_points' => 3,
                'story_points_change_count' => 0,
            ],
            [
                'title' => 'Violating Subtask 2',
                'description' => 'This subtask also violates the constraint',
                'status' => 'pending',
                'sort_order' => 2,
                'initial_story_points' => 5, // Violates max of 2
                'current_story_points' => 5,
                'story_points_change_count' => 0,
            ],
        ]);
        $mockResponse->shouldReceive('getNotes')->andReturn([]);
        $mockResponse->shouldReceive('getSummary')->andReturn('Response with multiple violations');
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
        $response->assertJson([
            'success' => false,
        ]);
        
        $responseData = $response->json();
        $this->assertStringContainsString('AI response violates story point constraints', $responseData['error']);
        $this->assertArrayHasKey('violations', $responseData);
        $this->assertCount(2, $responseData['violations']);
        
        // Check that both violations are reported
        $violations = $responseData['violations'];
        $this->assertStringContainsString("Subtask 'Violating Subtask 1' has 3 story points, but maximum allowed is 1", $violations[0]);
        $this->assertStringContainsString("Subtask 'Violating Subtask 2' has 5 story points, but maximum allowed is 1", $violations[1]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

