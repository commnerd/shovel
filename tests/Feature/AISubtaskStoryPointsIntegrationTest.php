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

class AISubtaskStoryPointsIntegrationTest extends TestCase
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
            'title' => 'Business Structure Analysis Project',
            'description' => 'A project to analyze and choose the best business structure',
            'ai_provider' => 'cerebras', // Ensure AI is enabled for the project
        ]);

        $this->parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => null,
            'depth' => 0,
            'title' => 'Choose the Best Business Structure',
            'description' => 'Analyze different business structures and choose the most suitable one for the company',
            'size' => 'l',
        ]);
    }

    public function test_ai_breakdown_generates_subtasks_with_story_points()
    {
        // Mock the exact AI response you provided
        $mockSubtasks = [
            [
                "title" => "Identify Company Needs and Goals",
                "description" => "Determine the specific needs and goals of the company that will influence the choice of business structure",
                "status" => "pending",
                "size" => null,
                "initial_story_points" => 3,
                "current_story_points" => 3,
                "story_points_change_count" => 0,
                "subtasks" => []
            ],
            [
                "title" => "Research Common Business Structures",
                "description" => "Gather information on common business structures such as sole proprietorship, partnership, LLC, and corporation",
                "status" => "pending",
                "size" => null,
                "initial_story_points" => 5,
                "current_story_points" => 5,
                "story_points_change_count" => 0,
                "subtasks" => []
            ],
            [
                "title" => "Analyze Advantages and Disadvantages",
                "description" => "Compare the advantages and disadvantages of each business structure in relation to the company's needs and goals",
                "status" => "pending",
                "size" => null,
                "initial_story_points" => 8,
                "current_story_points" => 8,
                "story_points_change_count" => 0,
                "subtasks" => []
            ],
            [
                "title" => "Consider Legal and Tax Implications",
                "description" => "Evaluate the legal and tax implications of each business structure option",
                "status" => "pending",
                "size" => null,
                "initial_story_points" => 8,
                "current_story_points" => 8,
                "story_points_change_count" => 0,
                "subtasks" => []
            ],
            [
                "title" => "Summarize Findings",
                "description" => "Compile the results of the analysis into a summary document highlighting the pros and cons of each business structure",
                "status" => "pending",
                "size" => null,
                "initial_story_points" => 3,
                "current_story_points" => 3,
                "story_points_change_count" => 0,
                "subtasks" => []
            ]
        ];

        $mockAIResponse = AITaskResponse::success(
            tasks: $mockSubtasks,
            notes: ['AI generated subtasks with story points'],
            summary: 'Generated 5 subtasks for business structure analysis'
        );

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('getAvailableProviders')->andReturn(['cerebras' => 'Cerebras']);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('breakdownTask')->andReturn($mockAIResponse);

        // Step 1: Test AI breakdown generation
        $breakdownResponse = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => $this->parentTask->title,
                'description' => $this->parentTask->description,
                'parent_task_id' => $this->parentTask->id,
            ]);

        $breakdownResponse->assertStatus(200);
        $breakdownData = $breakdownResponse->json();

        $this->assertTrue($breakdownData['success']);
        $this->assertArrayHasKey('subtasks', $breakdownData);
        $this->assertCount(5, $breakdownData['subtasks']);

        // Verify each subtask has story points in the response
        foreach ($breakdownData['subtasks'] as $subtask) {
            $this->assertNotNull($subtask['initial_story_points'], "Subtask '{$subtask['title']}' should have initial_story_points");
            $this->assertNotNull($subtask['current_story_points'], "Subtask '{$subtask['title']}' should have current_story_points");
            $this->assertEquals($subtask['initial_story_points'], $subtask['current_story_points'], "Initial and current story points should be equal for new subtasks");
            $this->assertEquals(0, $subtask['story_points_change_count'], "Story points change count should be 0 for new subtasks");

            $validFibonacci = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];
            $this->assertContains($subtask['current_story_points'], $validFibonacci, "Story points should be valid Fibonacci numbers");
        }

        // Step 2: Test saving subtasks to database
        $saveResponse = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => $this->parentTask->id,
                'subtasks' => $breakdownData['subtasks'],
            ]);

        $saveResponse->assertStatus(200);
        $saveData = $saveResponse->json();

        $this->assertTrue($saveData['success']);
        $this->assertEquals(5, $saveData['subtasks_count']);

        // Step 3: Verify subtasks were saved to database with story points
        $savedSubtasks = Task::where('parent_id', $this->parentTask->id)->get();
        $this->assertCount(5, $savedSubtasks);

        foreach ($savedSubtasks as $savedSubtask) {
            $this->assertEquals($this->project->id, $savedSubtask->project_id);
            $this->assertEquals($this->parentTask->id, $savedSubtask->parent_id);
            $this->assertEquals($this->parentTask->depth + 1, $savedSubtask->depth);
            $this->assertNull($savedSubtask->size, 'Subtasks should not have T-shirt sizes');

            // Verify story points are saved
            $this->assertNotNull($savedSubtask->initial_story_points, "Saved subtask '{$savedSubtask->title}' should have initial_story_points");
            $this->assertNotNull($savedSubtask->current_story_points, "Saved subtask '{$savedSubtask->title}' should have current_story_points");
            $this->assertEquals($savedSubtask->initial_story_points, $savedSubtask->current_story_points, "Initial and current story points should be equal");
            $this->assertEquals(0, $savedSubtask->story_points_change_count, "Story points change count should be 0");

            // Verify Fibonacci numbers
            $validFibonacci = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];
            $this->assertContains($savedSubtask->current_story_points, $validFibonacci, "Story points should be valid Fibonacci numbers");
        }

        // Step 4: Verify specific story points from your data
        $identifyTask = $savedSubtasks->where('title', 'Identify Company Needs and Goals')->first();
        $this->assertNotNull($identifyTask);
        $this->assertEquals(3, $identifyTask->current_story_points);

        $researchTask = $savedSubtasks->where('title', 'Research Common Business Structures')->first();
        $this->assertNotNull($researchTask);
        $this->assertEquals(5, $researchTask->current_story_points);

        $analyzeTask = $savedSubtasks->where('title', 'Analyze Advantages and Disadvantages')->first();
        $this->assertNotNull($analyzeTask);
        $this->assertEquals(8, $analyzeTask->current_story_points);

        $legalTask = $savedSubtasks->where('title', 'Consider Legal and Tax Implications')->first();
        $this->assertNotNull($legalTask);
        $this->assertEquals(8, $legalTask->current_story_points);

        $summarizeTask = $savedSubtasks->where('title', 'Summarize Findings')->first();
        $this->assertNotNull($summarizeTask);
        $this->assertEquals(3, $summarizeTask->current_story_points);
    }

    public function test_save_subtasks_endpoint_validation()
    {
        // Test missing parent_task_id
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'subtasks' => [
                    [
                        'title' => 'Test Task',
                        'status' => 'pending',
                        'current_story_points' => 5
                    ]
                ]
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_task_id']);

        // Test empty subtasks array
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => $this->parentTask->id,
                'subtasks' => []
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subtasks']);

        // Test invalid parent task
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => 99999,
                'subtasks' => [
                    [
                        'title' => 'Test Task',
                        'status' => 'pending',
                        'current_story_points' => 5
                    ]
                ]
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_task_id']);
    }

    public function test_save_subtasks_without_story_points()
    {
        $subtasksWithoutPoints = [
            [
                'title' => 'Task Without Points',
                'description' => 'A task that has no story points',
                'status' => 'pending',
                'initial_story_points' => null,
                'current_story_points' => null,
                'story_points_change_count' => 0,
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => $this->parentTask->id,
                'subtasks' => $subtasksWithoutPoints,
            ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertTrue($responseData['success']);
        $this->assertEquals(1, $responseData['subtasks_count']);

        // Verify the subtask was saved with null story points
        $savedSubtask = Task::where('parent_id', $this->parentTask->id)->first();
        $this->assertNotNull($savedSubtask);
        $this->assertNull($savedSubtask->initial_story_points);
        $this->assertNull($savedSubtask->current_story_points);
        $this->assertEquals(0, $savedSubtask->story_points_change_count);
    }

    public function test_save_subtasks_unauthorized_access()
    {
        // Create another user who doesn't own the project
        $otherUser = User::factory()->create([
            'organization_id' => $this->user->organization_id,
            'pending_approval' => false,
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => $this->parentTask->id,
                'subtasks' => [
                    [
                        'title' => 'Unauthorized Task',
                        'status' => 'pending',
                        'current_story_points' => 5
                    ]
                ]
            ]);

        $response->assertStatus(403);
    }

    public function test_save_subtasks_invalid_parent_task()
    {
        // Create a task that doesn't belong to the project
        $otherProject = Project::factory()->create(['user_id' => $this->user->id]);
        $otherTask = Task::factory()->create([
            'project_id' => $otherProject->id,
            'parent_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => $otherTask->id,
                'subtasks' => [
                    [
                        'title' => 'Invalid Parent Task',
                        'status' => 'pending',
                        'current_story_points' => 5
                    ]
                ]
            ]);

        $response->assertStatus(400);
        $responseData = $response->json();
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Invalid parent task.', $responseData['error']);
    }
}
