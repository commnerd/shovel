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

class CompleteSubtaskStoryPointsWorkflowTest extends TestCase
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
            'title' => 'Complete Workflow Test Project',
            'ai_provider' => 'cerebras',
        ]);

        $this->parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => null,
            'depth' => 0,
            'title' => 'Complete Business Analysis',
            'description' => 'Comprehensive business structure analysis',
            'size' => 'l',
        ]);
    }

    public function test_complete_workflow_from_ai_generation_to_ui_display()
    {
        // Step 1: Mock AI response with your exact data
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

        // Step 2: Generate AI breakdown
        $breakdownResponse = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => $this->parentTask->title,
                'description' => $this->parentTask->description,
                'parent_task_id' => $this->parentTask->id,
            ]);

        $breakdownResponse->assertStatus(200);
        $breakdownData = $breakdownResponse->json();

        $this->assertTrue($breakdownData['success']);
        $this->assertCount(5, $breakdownData['subtasks']);

        // Verify each subtask has story points in the AI response
        foreach ($breakdownData['subtasks'] as $subtask) {
            $this->assertNotNull($subtask['initial_story_points']);
            $this->assertNotNull($subtask['current_story_points']);
            $this->assertEquals($subtask['initial_story_points'], $subtask['current_story_points']);
            $this->assertEquals(0, $subtask['story_points_change_count']);

            $validFibonacci = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];
            $this->assertContains($subtask['current_story_points'], $validFibonacci);
        }

        // Step 3: Save subtasks to database
        $saveResponse = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/subtasks", [
                'parent_task_id' => $this->parentTask->id,
                'subtasks' => $breakdownData['subtasks'],
            ]);

        $saveResponse->assertStatus(200);
        $saveData = $saveResponse->json();

        $this->assertTrue($saveData['success']);
        $this->assertEquals(5, $saveData['subtasks_count']);

        // Step 4: Verify subtasks were saved with story points
        $savedSubtasks = Task::where('parent_id', $this->parentTask->id)->get();
        $this->assertCount(5, $savedSubtasks);

        foreach ($savedSubtasks as $savedSubtask) {
            $this->assertEquals($this->project->id, $savedSubtask->project_id);
            $this->assertEquals($this->parentTask->id, $savedSubtask->parent_id);
            $this->assertEquals($this->parentTask->depth + 1, $savedSubtask->depth);
            $this->assertNull($savedSubtask->size, 'Subtasks should not have T-shirt sizes');

            // Verify story points are saved
            $this->assertNotNull($savedSubtask->initial_story_points);
            $this->assertNotNull($savedSubtask->current_story_points);
            $this->assertEquals($savedSubtask->initial_story_points, $savedSubtask->current_story_points);
            $this->assertEquals(0, $savedSubtask->story_points_change_count);

            // Verify Fibonacci numbers
            $validFibonacci = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];
            $this->assertContains($savedSubtask->current_story_points, $validFibonacci);
        }

        // Step 5: Verify subtasks appear in the UI with story points
        $uiResponse = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $uiResponse->assertStatus(200);
        $uiResponse->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                ->has('tasks')
                ->where('tasks', function ($tasks) {
                    // Count subtasks with story points
                    $subtasks = collect($tasks)->where('parent_id', $this->parentTask->id);
                    $subtasksWithPoints = $subtasks->whereNotNull('current_story_points');

                    return $subtasks->count() === 5
                        && $subtasksWithPoints->count() === 5
                        && $subtasksWithPoints->every(function ($subtask) {
                            return $subtask['initial_story_points'] === $subtask['current_story_points']
                                && $subtask['story_points_change_count'] === 0;
                        });
                })
        );

        // Step 6: Test updating story points
        $firstSubtask = $savedSubtasks->first();
        $updateResponse = $this->actingAs($this->user)
            ->patchJson("/dashboard/tasks/{$firstSubtask->id}", [
                'current_story_points' => 13, // Change from 3 to 13
                'story_points_change_count' => 1,
            ]);

        $updateResponse->assertStatus(200);
        $updateData = $updateResponse->json();
        $this->assertTrue($updateData['success']);

        // Verify the update
        $firstSubtask->refresh();
        $this->assertEquals(13, $firstSubtask->current_story_points);
        $this->assertEquals(3, $firstSubtask->initial_story_points); // Initial should remain unchanged
        $this->assertEquals(1, $firstSubtask->story_points_change_count);

        // Step 7: Test invalid story points validation
        $invalidUpdateResponse = $this->actingAs($this->user)
            ->patchJson("/dashboard/tasks/{$firstSubtask->id}", [
                'current_story_points' => 4, // Not a Fibonacci number
            ]);

        $invalidUpdateResponse->assertStatus(422);
        $invalidData = $invalidUpdateResponse->json();
        $this->assertStringContainsString('Fibonacci number', $invalidData['message']);

        // Step 8: Verify final state in UI
        $finalUiResponse = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $finalUiResponse->assertStatus(200);
        $finalUiResponse->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                ->has('tasks')
                ->where('tasks', function ($tasks) use ($firstSubtask) {
                    $updatedSubtask = collect($tasks)->where('id', $firstSubtask->id)->first();
                    return $updatedSubtask !== null
                        && $updatedSubtask['current_story_points'] === 13
                        && $updatedSubtask['initial_story_points'] === 3
                        && $updatedSubtask['story_points_change_count'] === 1;
                })
        );
    }
}
