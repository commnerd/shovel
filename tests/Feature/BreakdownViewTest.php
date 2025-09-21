<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Project, Task};
use Illuminate\Foundation\Testing\RefreshDatabase;

class BreakdownViewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_breakdown_view_shows_complete_hierarchy()
    {
        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'priority' => 'medium',
        ]);

        // Create subtasks
        $subtask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Subtask 1',
            'priority' => 'medium',
        ]);

        $subtask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Subtask 2',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'all')
                 ->has('tasks', 3) // Should show parent + 2 subtasks
        );
    }

    public function test_parent_status_updates_when_all_children_completed()
    {
        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        // Create subtasks
        $subtask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $subtask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        // Complete first subtask
        $subtask1->update(['status' => 'completed']);
        $parentTask->refresh();
        $this->assertEquals('in_progress', $parentTask->status);

        // Complete second subtask
        $subtask2->update(['status' => 'completed']);
        $parentTask->refresh();
        $this->assertEquals('completed', $parentTask->status);
    }

    public function test_parent_status_updates_when_some_children_in_progress()
    {
        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        // Create subtasks
        $subtask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $subtask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        // Set first subtask to in_progress
        $subtask1->update(['status' => 'in_progress']);
        $parentTask->refresh();
        $this->assertEquals('in_progress', $parentTask->status);
    }

    public function test_parent_status_reverts_when_children_incomplete()
    {
        // Create parent task with completed subtasks
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'status' => 'completed',
            'priority' => 'medium',
        ]);

        $subtask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'status' => 'completed',
            'priority' => 'medium',
        ]);

        $subtask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'status' => 'completed',
            'priority' => 'medium',
        ]);

        // Revert one subtask to pending
        $subtask1->update(['status' => 'pending']);
        $parentTask->refresh();
        $this->assertEquals('in_progress', $parentTask->status);

        // Revert second subtask to pending
        $subtask2->update(['status' => 'pending']);
        $parentTask->refresh();
        $this->assertEquals('pending', $parentTask->status);
    }

    public function test_deep_hierarchy_status_propagation()
    {
        // Create 3-level hierarchy
        $grandparent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Grandparent',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $grandparent->id,
            'title' => 'Parent',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        // Complete the leaf task
        $child->update(['status' => 'completed']);

        // Check that status propagates up the hierarchy
        $parent->refresh();
        $grandparent->refresh();

        $this->assertEquals('completed', $parent->status);
        $this->assertEquals('completed', $grandparent->status);
    }

    public function test_completion_percentage_calculation()
    {
        // Create parent with multiple children
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'priority' => 'medium',
        ]);

        // Create 4 subtasks
        $subtasks = [];
        for ($i = 1; $i <= 4; $i++) {
            $subtasks[] = Task::factory()->create([
                'project_id' => $this->project->id,
                'parent_id' => $parentTask->id,
                'title' => "Subtask {$i}",
                'status' => 'pending',
                'priority' => 'medium',
            ]);
        }

        // Complete 2 out of 4 subtasks (50%)
        $subtasks[0]->update(['status' => 'completed']);
        $subtasks[1]->update(['status' => 'completed']);

        $parentTask->refresh();
        $this->assertEquals(50.0, $parentTask->getCompletionPercentage());
        $this->assertEquals('in_progress', $parentTask->status);
    }

    public function test_breakdown_view_includes_completion_percentage()
    {
        // Create parent with children
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'priority' => 'medium',
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'status' => 'completed',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'all')
                 ->has('tasks.0.completion_percentage')
                 ->where('tasks.0.completion_percentage', 100) // Parent should show 100% since child is completed
        );
    }

    public function test_leaf_tasks_show_individual_completion()
    {
        // Create a leaf task
        $leafTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Leaf Task',
            'status' => 'completed',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'all')
                 ->has('tasks.0.completion_percentage')
                 ->where('tasks.0.completion_percentage', 100) // Completed leaf task should show 100%
        );
    }

    public function test_only_leaf_tasks_can_change_status_in_breakdown_view()
    {
        // This test verifies the frontend logic - we'll test that the backend
        // properly updates parent status when children change

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $leafTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        // Update leaf task status
        $response = $this->actingAs($this->user)
            ->patchJson("/dashboard/projects/{$this->project->id}/tasks/{$leafTask->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(200);

        // Verify both tasks were updated
        $leafTask->refresh();
        $parentTask->refresh();

        $this->assertEquals('completed', $leafTask->status);
        $this->assertEquals('completed', $parentTask->status); // Parent should auto-update
    }
}
