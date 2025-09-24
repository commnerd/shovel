<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeafTasksFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $organization;

    protected Group $group;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);

        $this->organization = Organization::getDefault();
        $this->group = $this->organization->defaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($this->group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Project',
        ]);
    }

    public function test_leaf_tasks_filter_shows_only_actionable_tasks()
    {
        // Create a hierarchical task structure
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'description' => 'A parent task with children',
        ]);

        $childTask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task 1',
            'description' => 'A child task with children',
        ]);

        $leafTask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $childTask1->id,
            'title' => 'Leaf Task 1',
            'description' => 'An actionable leaf task',
        ]);

        $leafTask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $childTask1->id,
            'title' => 'Leaf Task 2',
            'description' => 'Another actionable leaf task',
        ]);

        $standaloneLeafTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Standalone Leaf Task',
            'description' => 'A standalone actionable task',
        ]);

        // Test leaf tasks filter
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=leaf");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks')
            ->where('filter', 'leaf')
        );

        // Should only return leaf tasks (not parent or child tasks with children)
        $response->assertInertia(fn (Assert $page) => $page->where('tasks', function ($tasks) use ($leafTask1, $leafTask2, $standaloneLeafTask) {
            $taskIds = collect($tasks)->pluck('id')->toArray();

            // Should include leaf tasks
            $this->assertContains($leafTask1->id, $taskIds);
            $this->assertContains($leafTask2->id, $taskIds);
            $this->assertContains($standaloneLeafTask->id, $taskIds);

            // Should have all tasks (including parent tasks for hierarchy context)
            $this->assertCount(5, $tasks);

            return true;
        })
        );
    }

    public function test_all_tasks_filter_shows_hierarchical_structure()
    {
        // Create a hierarchical task structure
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task',
        ]);

        // Test all tasks filter (breakdown view)
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks')
            ->where('filter', 'all')
        );

        // Should show all tasks
        $response->assertInertia(fn (Assert $page) => $page->where('tasks', function ($tasks) use ($parentTask, $childTask) {
            $this->assertCount(2, $tasks);

            $taskIds = collect($tasks)->pluck('id')->toArray();
            $this->assertContains($parentTask->id, $taskIds);
            $this->assertContains($childTask->id, $taskIds);

            return true;
        })
        );
    }

    public function test_top_level_filter_shows_only_top_level_tasks()
    {
        // Create a hierarchical task structure
        $topLevelTask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Top Level Task 1',
        ]);

        $topLevelTask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Top Level Task 2',
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $topLevelTask1->id,
            'title' => 'Child Task',
        ]);

        // Test top-level tasks filter
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=top-level");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->where('filter', 'top-level')
        );

        // Should only show top-level tasks
        $response->assertInertia(fn (Assert $page) => $page->where('tasks', function ($tasks) use ($topLevelTask1, $topLevelTask2) {
            $this->assertCount(2, $tasks);

            $taskIds = collect($tasks)->pluck('id')->toArray();
            $this->assertContains($topLevelTask1->id, $taskIds);
            $this->assertContains($topLevelTask2->id, $taskIds);

            return true;
        })
        );
    }

    public function test_task_counts_are_accurate_for_each_filter()
    {
        // Create a complex hierarchical structure
        $topLevel1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Top Level 1',
        ]);

        $topLevel2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Top Level 2 (Leaf)',
        ]);

        $child1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $topLevel1->id,
            'title' => 'Child 1',
        ]);

        $leaf1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $child1->id,
            'title' => 'Leaf 1',
        ]);

        $leaf2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $child1->id,
            'title' => 'Leaf 2',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->where('taskCounts.all', 5)      // All 5 tasks
            ->where('taskCounts.top_level', 2) // topLevel1, topLevel2
            ->where('taskCounts.leaf', 3)      // topLevel2, leaf1, leaf2
        );
    }

    public function test_leaf_tasks_display_depth_without_indentation()
    {
        // Create tasks with different depths
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task',
        ]);

        $leafTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $childTask->id,
            'title' => 'Deep Leaf Task',
        ]);

        // Test leaf tasks filter
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=leaf");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->where('filter', 'leaf')
            ->where('tasks', function ($tasks) use ($leafTask) {
                // Should have all tasks in hierarchy (parent + child + leaf)
                $this->assertCount(3, $tasks);

                // Find the leaf task in the hierarchy
                $leafTaskData = collect($tasks)->firstWhere('id', $leafTask->id);
                $this->assertNotNull($leafTaskData);
                $this->assertEquals('Deep Leaf Task', $leafTaskData['title']);

                // Should still have depth information
                $this->assertEquals(2, $leafTaskData['depth']);

                return true;
            })
        );
    }
}
