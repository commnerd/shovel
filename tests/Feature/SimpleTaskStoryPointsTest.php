<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleTaskStoryPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_task_with_story_points()
    {
        // Create test data
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);
        
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
        ]);

        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Parent Task for Testing',
            'status' => 'pending',
            'sort_order' => 1,
            'size' => 'm',
        ]);

        // Test the exact payload from the user
        $payload = [
            'title' => 'Assess Team Capacity and Identify Hiring Needs',
            'description' => 'Evaluate current team workload, role gaps, and projected demand to determine the number and types of new hires required.',
            'parent_id' => $parentTask->id,
            'status' => 'pending',
            'due_date' => null,
            'initial_story_points' => 5,
            'current_story_points' => 5,
            'story_points_change_count' => 0,
        ];

        $response = $this->actingAs($user)
            ->postJson("/dashboard/projects/{$project->id}/tasks", $payload);

        $response->assertRedirect();
        
        // Verify the task was created with story points
        $task = Task::where('title', 'Assess Team Capacity and Identify Hiring Needs')->first();
        
        $this->assertNotNull($task);
        $this->assertEquals(5, $task->initial_story_points);
        $this->assertEquals(5, $task->current_story_points);
        $this->assertEquals(0, $task->story_points_change_count);
        $this->assertEquals($parentTask->id, $task->parent_id);
        $this->assertEquals($project->id, $task->project_id);
    }
}
