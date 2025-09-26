<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskStoryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected Task $parentTask;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        
        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
        ]);

        $this->parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task for Testing',
            'status' => 'pending',
            'sort_order' => 1,
            'size' => 'm',
        ]);
    }

    /** @test */
    public function it_can_create_a_task_with_story_points()
    {
        $payload = [
            'title' => 'Assess Team Capacity and Identify Hiring Needs',
            'description' => 'Evaluate current team workload, role gaps, and projected demand to determine the number and types of new hires required.',
            'parent_id' => $this->parentTask->id,
            'status' => 'pending',
            'due_date' => null,
            'initial_story_points' => 5,
            'current_story_points' => 5,
            'story_points_change_count' => 0,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks", $payload);

        $response->assertRedirect();
        
        // Verify the task was created with story points
        $task = Task::where('title', 'Assess Team Capacity and Identify Hiring Needs')->first();
        
        $this->assertNotNull($task);
        $this->assertEquals(5, $task->initial_story_points);
        $this->assertEquals(5, $task->current_story_points);
        $this->assertEquals(0, $task->story_points_change_count);
        $this->assertEquals($this->parentTask->id, $task->parent_id);
        $this->assertEquals($this->project->id, $task->project_id);
    }

    /** @test */
    public function it_can_create_a_task_without_story_points()
    {
        $payload = [
            'title' => 'Task Without Story Points',
            'description' => 'A task without story points',
            'parent_id' => $this->parentTask->id,
            'status' => 'pending',
            'due_date' => null,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks", $payload);

        $response->assertRedirect();
        
        // Verify the task was created without story points
        $task = Task::where('title', 'Task Without Story Points')->first();
        
        $this->assertNotNull($task);
        $this->assertNull($task->initial_story_points);
        $this->assertNull($task->current_story_points);
        $this->assertEquals(0, $task->story_points_change_count);
    }

    /** @test */
    public function it_can_create_subtasks_with_story_points()
    {
        $payload = [
            'title' => 'Parent Task with Subtasks',
            'description' => 'A parent task that will have subtasks',
            'status' => 'pending',
            'due_date' => null,
            'subtasks' => [
                [
                    'title' => 'Subtask 1',
                    'description' => 'First subtask with story points',
                    'status' => 'pending',
                    'initial_story_points' => 8,
                    'current_story_points' => 8,
                    'story_points_change_count' => 0,
                ],
                [
                    'title' => 'Subtask 2',
                    'description' => 'Second subtask with story points',
                    'status' => 'pending',
                    'initial_story_points' => 13,
                    'current_story_points' => 13,
                    'story_points_change_count' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks", $payload);

        $response->assertRedirect();
        
        // Verify the parent task was created
        $parentTask = Task::where('title', 'Parent Task with Subtasks')->first();
        $this->assertNotNull($parentTask);
        
        // Verify the subtasks were created with story points
        $subtasks = Task::where('parent_id', $parentTask->id)->get();
        $this->assertCount(2, $subtasks);
        
        $subtask1 = $subtasks->where('title', 'Subtask 1')->first();
        $this->assertNotNull($subtask1);
        $this->assertEquals(8, $subtask1->initial_story_points);
        $this->assertEquals(8, $subtask1->current_story_points);
        $this->assertEquals(0, $subtask1->story_points_change_count);
        
        $subtask2 = $subtasks->where('title', 'Subtask 2')->first();
        $this->assertNotNull($subtask2);
        $this->assertEquals(13, $subtask2->initial_story_points);
        $this->assertEquals(13, $subtask2->current_story_points);
        $this->assertEquals(0, $subtask2->story_points_change_count);
    }

    /** @test */
    public function it_validates_story_points_are_non_negative_integers()
    {
        $payload = [
            'title' => 'Task with Invalid Story Points',
            'description' => 'A task with invalid story points',
            'parent_id' => $this->parentTask->id,
            'status' => 'pending',
            'due_date' => null,
            'initial_story_points' => -1, // Invalid: negative
            'current_story_points' => 'invalid', // Invalid: not integer
            'story_points_change_count' => 0,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks", $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['initial_story_points', 'current_story_points']);
    }

    /** @test */
    public function it_handles_missing_story_points_fields_gracefully()
    {
        $payload = [
            'title' => 'Task with Missing Story Points',
            'description' => 'A task with some missing story points fields',
            'parent_id' => $this->parentTask->id,
            'status' => 'pending',
            'due_date' => null,
            'initial_story_points' => 5,
            // current_story_points and story_points_change_count are missing
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks", $payload);

        $response->assertRedirect();
        
        // Verify the task was created with default values for missing fields
        $task = Task::where('title', 'Task with Missing Story Points')->first();
        
        $this->assertNotNull($task);
        $this->assertEquals(5, $task->initial_story_points);
        $this->assertNull($task->current_story_points);
        $this->assertEquals(0, $task->story_points_change_count);
    }

    /** @test */
    public function it_can_create_top_level_tasks_with_story_points()
    {
        $payload = [
            'title' => 'Top Level Task with Story Points',
            'description' => 'A top-level task with story points',
            'status' => 'pending',
            'due_date' => null,
            'initial_story_points' => 21,
            'current_story_points' => 21,
            'story_points_change_count' => 0,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks", $payload);

        $response->assertRedirect();
        
        // Verify the task was created with story points
        $task = Task::where('title', 'Top Level Task with Story Points')->first();
        
        $this->assertNotNull($task);
        $this->assertEquals(21, $task->initial_story_points);
        $this->assertEquals(21, $task->current_story_points);
        $this->assertEquals(0, $task->story_points_change_count);
        $this->assertNull($task->parent_id); // Should be a top-level task
    }
}
