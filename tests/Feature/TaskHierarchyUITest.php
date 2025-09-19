<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskHierarchyUITest extends TestCase
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

    public function test_task_create_form_shows_parent_task_options()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Available Parent 1',
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Available Parent 2',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/create");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Projects/Tasks/Create')
            ->has('parentTasks', 2)
            ->where('parentTasks.0.title', 'Available Parent 1')
            ->where('parentTasks.1.title', 'Available Parent 2')
        );
    }

    public function test_subtask_creation_form_preselects_parent()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Preselected Parent',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/subtasks/create");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Projects/Tasks/Create')
            ->where('parentTask.id', $parentTask->id)
            ->where('parentTask.title', 'Preselected Parent')
            ->has('parentTasks', 0) // Should be empty since parent is preselected
        );
    }

    public function test_task_index_displays_hierarchy_visual_indicators()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent with Visual Indicators',
            'sort_order' => 1,
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child with Visual Indicators',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();

        // Check that both tasks are present with correct hierarchy data
        $tasks = $response->viewData('page')['props']['tasks'];
        $this->assertCount(2, $tasks);

        // Find parent and child tasks
        $parentData = collect($tasks)->firstWhere('title', 'Parent with Visual Indicators');
        $childData = collect($tasks)->firstWhere('title', 'Child with Visual Indicators');

        // Verify parent task properties
        $this->assertTrue($parentData['has_children']);
        $this->assertTrue($parentData['is_top_level']);
        $this->assertFalse($parentData['is_leaf']);
        $this->assertEquals(0, $parentData['depth']);

        // Verify child task properties
        $this->assertFalse($childData['has_children']);
        $this->assertFalse($childData['is_top_level']);
        $this->assertTrue($childData['is_leaf']);
        $this->assertEquals(1, $childData['depth']);
    }

    public function test_task_edit_form_includes_due_date()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task with Due Date',
            'due_date' => '2025-12-31',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Projects/Tasks/Edit')
            ->where('task.due_date', '2025-12-31')
            ->where('task.title', 'Task with Due Date')
        );
    }

    public function test_task_index_with_mixed_hierarchy_shows_correct_data()
    {
        // Create mixed hierarchy
        $standalone = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Standalone Task',
            'status' => 'completed',
            'priority' => 'low',
            'due_date' => '2025-11-30',
        ]);

        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => '2025-12-31',
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child Task',
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => '2025-12-15',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();

        // Verify all task data is present
        $tasks = $response->viewData('page')['props']['tasks'];

        $this->assertCount(3, $tasks);

        // Find tasks by title
        $standaloneData = collect($tasks)->firstWhere('title', 'Standalone Task');
        $parentData = collect($tasks)->firstWhere('title', 'Parent Task');
        $childData = collect($tasks)->firstWhere('title', 'Child Task');

        // Verify standalone task
        $this->assertTrue($standaloneData['is_top_level']);
        $this->assertTrue($standaloneData['is_leaf']);
        $this->assertEquals(0, $standaloneData['depth']);
        $this->assertEquals('2025-11-30', $standaloneData['due_date']);

        // Verify parent task
        $this->assertTrue($parentData['is_top_level']);
        $this->assertFalse($parentData['is_leaf']);
        $this->assertTrue($parentData['has_children']);
        $this->assertEquals(0, $parentData['depth']);

        // Verify child task
        $this->assertFalse($childData['is_top_level']);
        $this->assertTrue($childData['is_leaf']);
        $this->assertFalse($childData['has_children']);
        $this->assertEquals(1, $childData['depth']);
        $this->assertEquals($parent->id, $childData['parent_id']);
    }

    public function test_task_creation_form_validation_with_hierarchy()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        // Test missing required fields
        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'parent_id' => $parentTask->id,
                'priority' => 'medium',
                'status' => 'pending',
                // Missing title
            ]);

        $response->assertSessionHasErrors(['title']);

        // Test invalid priority
        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Task with Invalid Priority',
                'parent_id' => $parentTask->id,
                'priority' => 'urgent', // Invalid priority
                'status' => 'pending',
            ]);

        $response->assertSessionHasErrors(['priority']);

        // Test invalid status
        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Task with Invalid Status',
                'parent_id' => $parentTask->id,
                'priority' => 'medium',
                'status' => 'on_hold', // Invalid status
            ]);

        $response->assertSessionHasErrors(['status']);
    }

    public function test_task_update_preserves_hierarchy_relationships()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Original Parent',
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Original Child',
        ]);

        $newParent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'New Parent',
        ]);

        // Move child to new parent
        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$this->project->id}/tasks/{$child->id}", [
                'title' => 'Updated Child',
                'description' => 'Updated description',
                'parent_id' => $newParent->id,
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => '2025-12-31',
            ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        // Verify hierarchy change
        $child->refresh();
        $parent->refresh();
        $newParent->refresh();

        $this->assertEquals($newParent->id, $child->parent_id);
        $this->assertTrue($parent->isLeaf()); // Original parent should now be leaf
        $this->assertFalse($newParent->isLeaf()); // New parent should have children
        $this->assertEquals("{$newParent->id}/{$child->id}", $child->path);
    }

    public function test_task_hierarchy_respects_organization_boundaries()
    {
        // Create different organization
        $otherOrg = Organization::factory()->create();
        $otherGroup = $otherOrg->createDefaultGroup();

        $otherUser = User::factory()->create([
            'organization_id' => $otherOrg->id,
            'pending_approval' => false,
        ]);
        $otherUser->joinGroup($otherGroup);

        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $otherGroup->id,
        ]);

        $otherTask = Task::factory()->create([
            'project_id' => $otherProject->id,
            'title' => 'Other Organization Task',
        ]);

        // Current user should not see other organization's tasks as parent options
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/create");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Projects/Tasks/Create')
            ->has('parentTasks', 0) // Should be empty since no tasks in current project yet
        );

        // Create task in current project
        $myTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'My Task',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/create");

        $response->assertInertia(fn ($page) => $page->has('parentTasks', 1)
            ->where('parentTasks.0.title', 'My Task')
        );
    }
}
