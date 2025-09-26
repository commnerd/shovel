<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskSizingForSubtasksTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected Task $parentTask;
    protected Task $subtask;

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
            'title' => 'Test Project for Task Sizing',
            'ai_provider' => 'cerebras',
        ]);

        $this->parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => null,
            'depth' => 0,
            'title' => 'Parent Task',
            'size' => 'l',
        ]);

        $this->subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $this->parentTask->id,
            'depth' => 1,
            'title' => 'Subtask with Story Points',
            'description' => 'A subtask that should have story points',
            'size' => null, // Subtasks don't have T-shirt sizes
            'initial_story_points' => 5,
            'current_story_points' => 5,
            'story_points_change_count' => 0,
        ]);
    }

    public function test_subtask_displays_story_points_correctly()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                ->has('tasks')
                ->where('tasks', function ($tasks) {
                    // Find the subtask in the tasks data
                    $subtask = collect($tasks)->where('id', $this->subtask->id)->first();
                    return $subtask !== null
                        && $subtask['current_story_points'] === 5
                        && $subtask['initial_story_points'] === 5
                        && $subtask['story_points_change_count'] === 0;
                })
        );
    }

    public function test_subtask_story_points_can_be_updated()
    {
        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/tasks/{$this->subtask->id}", [
                'current_story_points' => 8,
                'story_points_change_count' => 1,
            ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertTrue($responseData['success']);

        // Verify the subtask was updated in the database
        $this->subtask->refresh();
        $this->assertEquals(8, $this->subtask->current_story_points);
        $this->assertEquals(5, $this->subtask->initial_story_points); // Initial should remain unchanged
        $this->assertEquals(1, $this->subtask->story_points_change_count);
    }

    public function test_subtask_story_points_validation()
    {
        // Test invalid story points (non-Fibonacci) - should be handled by the model's setStoryPoints method
        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/tasks/{$this->subtask->id}", [
                'current_story_points' => 4, // Not a Fibonacci number
            ]);

        $response->assertStatus(422);
        $responseData = $response->json();
        $this->assertStringContainsString('Fibonacci number', $responseData['message']);

        // Test negative story points - should be caught by validation
        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/tasks/{$this->subtask->id}", [
                'current_story_points' => -1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_story_points']);
    }

    public function test_subtask_without_story_points_displays_set_points_button()
    {
        // Create a subtask without story points
        $subtaskWithoutPoints = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $this->parentTask->id,
            'depth' => 1,
            'title' => 'Subtask Without Points',
            'size' => null,
            'initial_story_points' => null,
            'current_story_points' => null,
            'story_points_change_count' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                ->has('tasks')
                ->where('tasks', function ($tasks) use ($subtaskWithoutPoints) {
                    $subtask = collect($tasks)->where('id', $subtaskWithoutPoints->id)->first();
                    return $subtask !== null
                        && $subtask['current_story_points'] === null
                        && $subtask['initial_story_points'] === null;
                })
        );
    }

    public function test_subtask_size_is_always_null()
    {
        // Verify that subtasks don't have T-shirt sizes
        $this->assertNull($this->subtask->size);

        // Try to set a size on a subtask using the setSize method (should throw exception)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only top-level tasks can have a T-shirt size');
        $this->subtask->setSize('m');
    }

    public function test_parent_task_can_have_both_size_and_story_points()
    {
        // Parent tasks can have T-shirt sizes
        $this->assertNotNull($this->parentTask->size);
        $this->assertEquals('l', $this->parentTask->size);

        // Parent tasks can also have story points
        $this->parentTask->current_story_points = 13;
        $this->parentTask->initial_story_points = 13;
        $this->parentTask->save();

        $this->parentTask->refresh();
        $this->assertEquals(13, $this->parentTask->current_story_points);
        $this->assertEquals(13, $this->parentTask->initial_story_points);
        $this->assertNotNull($this->parentTask->size); // Size should still be present
    }
}
