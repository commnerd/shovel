<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskHierarchyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

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
        ]);
    }

    public function test_complete_task_hierarchy_workflow()
    {
        // Step 1: Create a top-level task
        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Implement User Authentication',
                'description' => 'Complete user authentication system',
                'status' => 'pending',
                'due_date' => '2025-12-31',
            ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");
        $parentTask = Task::where('title', 'Implement User Authentication')->first();

        // Step 2: Create subtasks
        $subtasks = [
            'Design login form',
            'Implement authentication logic',
            'Add password validation',
            'Create user registration',
        ];

        foreach ($subtasks as $index => $subtaskTitle) {
            $response = $this->actingAs($this->user)
                ->post("/dashboard/projects/{$this->project->id}/tasks", [
                    'title' => $subtaskTitle,
                    'parent_id' => $parentTask->id,
 // Must be >= parent priority (high)
                    'status' => 'pending',
                ]);

            $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");
        }

        // Step 3: Create sub-subtasks
        $authLogicTask = Task::where('title', 'Implement authentication logic')->first();

        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Set up middleware',
                'parent_id' => $authLogicTask->id,
 // Must be >= parent priority (high)
                'status' => 'pending',
            ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        // Step 4: Verify hierarchy structure
        $parentTask->refresh();
        $this->assertCount(4, $parentTask->children);
        $this->assertFalse($parentTask->isLeaf());
        $this->assertTrue($parentTask->isTopLevel());

        $authLogicTask->refresh();
        $this->assertCount(1, $authLogicTask->children);
        $this->assertFalse($authLogicTask->isLeaf());
        $this->assertFalse($authLogicTask->isTopLevel());

        $middlewareTask = Task::where('title', 'Set up middleware')->first();
        $this->assertTrue($middlewareTask->isLeaf());
        $this->assertEquals(2, $middlewareTask->getDepth());

        // Step 5: Test filtering
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=top-level");

        $response->assertInertia(fn ($page) => $page->has('tasks', 1)
            ->where('tasks.0.title', 'Implement User Authentication')
        );

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=leaf");

        $response->assertInertia(fn ($page) => $page->has('tasks', 6) // all tasks including parent hierarchy
        );

        // Step 6: Test completion workflow
        $middlewareTask->update(['status' => 'completed']);
        $authLogicTask->refresh();

        // Auth logic task should show 100% completion (1/1 child completed)
        $this->assertEquals(100.0, $authLogicTask->getCompletionPercentage());

        // Complete all subtasks
        foreach ($parentTask->children as $child) {
            $child->update(['status' => 'completed']);
        }

        $parentTask->refresh();
        $this->assertEquals(100.0, $parentTask->getCompletionPercentage());
    }

    public function test_task_hierarchy_with_dashboard_metrics()
    {
        // Create hierarchical task structure
        $parent1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Feature A',
            'status' => 'in_progress',
        ]);

        $parent2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Feature B',
            'status' => 'pending',
        ]);

        // Create leaf tasks
        $leaf1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent1->id,
            'title' => 'Subtask A1',
            'status' => 'completed',
        ]);

        $leaf2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent1->id,
            'title' => 'Subtask A2',
            'status' => 'in_progress',
        ]);

        $leaf3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent2->id,
            'title' => 'Subtask B1',
            'status' => 'pending',
        ]);

        // Test dashboard metrics focus on leaf tasks
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('taskMetrics.totalLeaf', 3)
            ->where('taskMetrics.completed', 1)
            ->where('taskMetrics.inProgress', 1)
            ->where('taskMetrics.pending', 1)
        );
    }

    public function test_task_hierarchy_preserves_project_authorization()
    {
        // Create another user with different project
        $otherUser = User::factory()->create([
            'organization_id' => $this->user->organization_id,
            'pending_approval' => false,
        ]);
        $otherUser->joinGroup($this->user->groups->first());

        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->user->groups->first()->id,
        ]);

        $otherTask = Task::factory()->create([
            'project_id' => $otherProject->id,
            'title' => 'Other User Task',
        ]);

        // Current user should not be able to create subtask in other's project
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$otherProject->id}/tasks/{$otherTask->id}/subtasks/create");

        $response->assertStatus(403);

        // Current user should not be able to create task with other's task as parent
        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Cross-project Subtask',
                'parent_id' => $otherTask->id,
                'status' => 'pending',
            ]);

        $response->assertSessionHasErrors(['parent_id']);
    }

    public function test_task_hierarchy_with_ai_generated_tasks()
    {
        // Create a parent task first
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'AI Generated Parent',
        ]);

        // Simulate AI generating subtasks
        $aiSubtasks = [
            [
                'title' => 'AI Subtask 1',
                'description' => 'Generated by AI',
 // Valid since parent is low
                'status' => 'pending',
                'due_date' => '2025-12-31',
                'parent_id' => $parentTask->id,
            ],
            [
                'title' => 'AI Subtask 2',
                'description' => 'Another AI task',
 // Valid since parent is low
                'status' => 'pending',
                'due_date' => '2026-01-15',
                'parent_id' => $parentTask->id,
            ],
        ];

        foreach ($aiSubtasks as $taskData) {
            $response = $this->actingAs($this->user)
                ->post("/dashboard/projects/{$this->project->id}/tasks", $taskData);

            $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");
        }

        // Verify hierarchy was created correctly
        $parentTask->refresh();
        $this->assertCount(2, $parentTask->children);
        $this->assertFalse($parentTask->isLeaf());

        $children = $parentTask->children()->orderBy('sort_order')->get();
        $this->assertEquals('AI Subtask 1', $children->first()->title);
        $this->assertEquals('AI Subtask 2', $children->last()->title);
        $this->assertEquals(1, $children->first()->sort_order);
        $this->assertEquals(2, $children->last()->sort_order);
    }

    public function test_task_hierarchy_performance_with_large_dataset()
    {
        // Create a larger hierarchy for performance testing
        $root = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Large Hierarchy Root',
        ]);

        // Create 10 first-level children
        $firstLevelChildren = [];
        for ($i = 1; $i <= 10; $i++) {
            $child = Task::factory()->create([
                'project_id' => $this->project->id,
                'parent_id' => $root->id,
                'title' => "First Level Child {$i}",
                'sort_order' => $i,
            ]);
            $firstLevelChildren[] = $child;
        }

        // Create 5 second-level children for first 5 first-level tasks
        for ($i = 0; $i < 5; $i++) {
            for ($j = 1; $j <= 5; $j++) {
                Task::factory()->create([
                    'project_id' => $this->project->id,
                    'parent_id' => $firstLevelChildren[$i]->id,
                    'title' => "Second Level Child {$i}-{$j}",
                    'sort_order' => $j,
                ]);
            }
        }

        // Verify counts
        $this->assertEquals(1, Task::topLevel()->count()); // Only root
        $this->assertEquals(30, Task::leaf()->count()); // 5 childless first-level + 25 second-level = 30 leaf tasks (first-level with children are not leaf)
        $this->assertEquals(36, Task::count()); // Total tasks

        // Test index page performance
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('taskCounts.all', 36)
            ->where('taskCounts.top_level', 1)
            ->where('taskCounts.leaf', 30) // 25 second-level children + 5 childless first-level
        );
    }

    public function test_task_hierarchy_breadcrumb_navigation()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task for Navigation',
        ]);

        // Test subtask creation form shows correct context
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$parent->id}/subtasks/create");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Projects/Tasks/Create')
            ->where('parentTask.id', $parent->id)
            ->where('parentTask.title', 'Parent Task for Navigation')
        );
    }
}
