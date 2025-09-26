<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Contracts\AITaskResponse;
use App\Services\AI\Facades\AI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendSubtaskCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected Task $parentTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\OrganizationSeeder::class);

        $organization = Organization::getDefault();
        $group = $organization->createDefaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'title' => 'Frontend Test Project',
            'ai_provider' => 'cerebras',
        ]);

        $this->parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => null,
            'depth' => 0,
            'title' => 'Frontend Test Task',
            'description' => 'Test task for frontend integration',
            'size' => 'm',
        ]);
    }

    public function test_breakdown_page_loads_with_create_subtasks_button()
    {
        // Mock AI response
        $mockSubtasks = [
            [
                "title" => "Test Subtask 1",
                "description" => "First test subtask",
                "status" => "pending",
                "size" => null,
                "initial_story_points" => 3,
                "current_story_points" => 3,
                "story_points_change_count" => 0,
                "subtasks" => []
            ],
            [
                "title" => "Test Subtask 2",
                "description" => "Second test subtask",
                "status" => "pending",
                "size" => null,
                "initial_story_points" => 5,
                "current_story_points" => 5,
                "story_points_change_count" => 0,
                "subtasks" => []
            ]
        ];

        $mockAIResponse = AITaskResponse::success(
            tasks: $mockSubtasks,
            notes: ['AI generated subtasks'],
            summary: 'Generated 2 test subtasks'
        );

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('getAvailableProviders')->andReturn(['cerebras' => 'Cerebras']);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('breakdownTask')->andReturn($mockAIResponse);

        // Test breakdown page loads
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$this->parentTask->id}/breakdown");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Breakdown')
                ->has('task')
                ->has('project')
        );

        // Test AI breakdown generation
        $breakdownResponse = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => $this->parentTask->title,
                'description' => $this->parentTask->description,
                'parent_task_id' => $this->parentTask->id,
            ]);

        $breakdownResponse->assertStatus(200);
        $breakdownData = $breakdownResponse->json();

        $this->assertTrue($breakdownData['success']);
        $this->assertCount(2, $breakdownData['subtasks']);

        // Test creating subtasks
        $saveResponse = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => $this->parentTask->id,
                'subtasks' => $breakdownData['subtasks'],
            ]);

        $saveResponse->assertStatus(200);
        $saveData = $saveResponse->json();

        $this->assertTrue($saveData['success']);
        $this->assertEquals(2, $saveData['subtasks_count']);

        // Verify subtasks exist in database
        $savedSubtasks = Task::where('parent_id', $this->parentTask->id)->get();
        $this->assertCount(2, $savedSubtasks);

        // Verify they appear in task list with filter=all
        $taskListResponse = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $taskListResponse->assertStatus(200);
        $taskListResponse->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                ->has('tasks')
                ->where('tasks', function ($tasks) {
                    $subtasks = collect($tasks)->where('parent_id', $this->parentTask->id);
                    return $subtasks->count() === 2
                        && $subtasks->every(function ($subtask) {
                            return $subtask['current_story_points'] !== null
                                && $subtask['initial_story_points'] !== null;
                        });
                })
        );
    }

    public function test_create_subtasks_endpoint_validation_and_error_handling()
    {
        // Test with invalid parent task
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => 99999, // Non-existent task
                'subtasks' => [
                    [
                        'title' => 'Test Subtask',
                        'status' => 'pending',
                        'current_story_points' => 3
                    ]
                ]
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_task_id']);

        // Test with empty subtasks array
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => $this->parentTask->id,
                'subtasks' => []
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subtasks']);

        // Test with missing required fields
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => $this->parentTask->id,
                'subtasks' => [
                    [
                        'status' => 'pending',
                        'current_story_points' => 3
                        // Missing title
                    ]
                ]
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subtasks.0.title']);
    }
}
