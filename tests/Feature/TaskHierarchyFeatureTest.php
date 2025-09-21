<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskHierarchyFeatureTest extends TestCase
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

    public function test_user_can_create_top_level_task()
    {
        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Top Level Task',
                'description' => 'A top level task',
                'priority' => 'high',
                'status' => 'pending',
                'due_date' => '2025-12-31',
            ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title' => 'Top Level Task',
            'parent_id' => null,
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $task = Task::where('title', 'Top Level Task')->first();
        $this->assertEquals('2025-12-31', $task->due_date->format('Y-m-d'));
        $this->assertTrue($task->isTopLevel());
        $this->assertTrue($task->isLeaf());
    }

    public function test_user_can_create_subtask()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'priority' => 'low', // Specify parent priority so subtask can be medium
        ]);

        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Subtask',
                'description' => 'A subtask',
                'parent_id' => $parentTask->id,
                'priority' => 'medium', // Valid since parent is low
                'status' => 'pending',
                'due_date' => '2025-12-31',
            ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title' => 'Subtask',
            'parent_id' => $parentTask->id,
            'priority' => 'medium',
            'status' => 'pending',
        ]);

        $subtask = Task::where('title', 'Subtask')->first();
        $this->assertFalse($subtask->isTopLevel());
        $this->assertTrue($subtask->isLeaf());
        $this->assertEquals($parentTask->id, $subtask->parent_id);

        // Verify parent is no longer a leaf
        $parentTask->refresh();
        $this->assertFalse($parentTask->isLeaf());
    }

    public function test_user_can_access_subtask_creation_form()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/subtasks/create");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Projects/Tasks/Create')
            ->has('project')
            ->has('parentTask')
            ->where('parentTask.id', $parentTask->id)
            ->where('parentTask.title', 'Parent Task')
        );
    }

    public function test_task_creation_validates_parent_belongs_to_project()
    {
        $otherProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->project->group_id,
        ]);

        $taskFromOtherProject = Task::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Invalid Subtask',
                'parent_id' => $taskFromOtherProject->id,
                'priority' => 'medium',
                'status' => 'pending',
            ]);

        $response->assertSessionHasErrors(['parent_id']);
        $this->assertDatabaseMissing('tasks', [
            'title' => 'Invalid Subtask',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_task_update_validates_parent_is_not_self()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Self-referencing Task',
        ]);

        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$this->project->id}/tasks/{$task->id}", [
                'title' => 'Updated Task',
                'parent_id' => $task->id, // Try to set self as parent
                'priority' => 'medium',
                'status' => 'pending',
            ]);

        $response->assertSessionHasErrors(['parent_id']);
    }

    public function test_task_index_shows_hierarchy_information()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child Task',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertOk();

        // Get tasks and verify hierarchy data
        $tasks = $response->viewData('page')['props']['tasks'];
        $this->assertCount(2, $tasks);

        // Find parent and child by title
        $parentData = collect($tasks)->firstWhere('title', 'Parent Task');
        $childData = collect($tasks)->firstWhere('title', 'Child Task');

        // Verify parent task
        $this->assertTrue($parentData['has_children']);
        $this->assertTrue($parentData['is_top_level']);
        $this->assertFalse($parentData['is_leaf']);

        // Verify child task
        $this->assertFalse($childData['has_children']);
        $this->assertFalse($childData['is_top_level']);
        $this->assertTrue($childData['is_leaf']);
    }

    public function test_task_index_filters_work_with_hierarchy()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child Task',
        ]);

        $standalone = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Standalone Task',
        ]);

        // Test top-level filter
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=top-level");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('tasks', 2) // parent and standalone
            ->where('filter', 'top-level')
        );

        // Test leaf filter
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=leaf");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('tasks', 2) // child and standalone
            ->where('filter', 'leaf')
        );
    }

    public function test_task_counts_reflect_hierarchy()
    {
        $parent1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent 1',
        ]);

        $parent2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent 2',
        ]);

        $child1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent1->id,
            'title' => 'Child 1',
        ]);

        $child2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent2->id,
            'title' => 'Child 2',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('taskCounts.all', 4)
            ->where('taskCounts.top_level', 2)
            ->where('taskCounts.leaf', 2)
        );
    }

    public function test_subtask_creation_updates_hierarchy_path()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'priority' => 'low', // Specify parent priority so subtask can be medium
        ]);

        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Subtask with Path',
                'parent_id' => $parentTask->id,
                'priority' => 'medium', // Valid since parent is low
                'status' => 'pending',
            ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        $subtask = Task::where('title', 'Subtask with Path')->first();
        $this->assertEquals("{$parentTask->id}/{$subtask->id}", $subtask->path);
        $this->assertEquals(1, $subtask->depth);
    }

    public function test_task_update_changes_hierarchy_path()
    {
        $parent1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent 1',
            'priority' => 'low', // Specify priority
        ]);

        $parent2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent 2',
            'priority' => 'low', // Specify priority to allow medium child
        ]);

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent1->id,
            'title' => 'Moving Task',
            'priority' => 'medium', // Specify initial priority
        ]);

        // Move task from parent1 to parent2
        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$this->project->id}/tasks/{$task->id}", [
                'title' => 'Moving Task',
                'parent_id' => $parent2->id,
                'priority' => 'medium', // Valid since both parents are low
                'status' => 'pending',
            ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        $task->refresh();
        $this->assertEquals($parent2->id, $task->parent_id);
        $this->assertEquals("{$parent2->id}/{$task->id}", $task->path);
    }

    public function test_unauthorized_user_cannot_access_subtask_creation()
    {
        $otherUser = User::factory()->create();
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/subtasks/create");

        $response->assertStatus(403);
    }

    public function test_task_deletion_with_subtasks()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent to Delete',
        ]);

        $child1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child 1',
        ]);

        $child2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child 2',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/dashboard/projects/{$this->project->id}/tasks/{$parent->id}");

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        // Verify cascade deletion
        $this->assertDatabaseMissing('tasks', ['id' => $parent->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $child1->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $child2->id]);
    }

    public function test_task_edit_form_shows_hierarchy_context()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child Task',
            'due_date' => '2025-12-31',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$child->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Projects/Tasks/Edit')
            ->where('task.parent_id', $parent->id)
            ->where('task.due_date', '2025-12-31')
            ->has('parentTasks')
        );
    }

    public function test_task_creation_with_invalid_due_date()
    {
        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Task with Past Due Date',
                'priority' => 'medium',
                'status' => 'pending',
                'due_date' => '2020-01-01', // Past date
            ]);

        $response->assertSessionHasErrors(['due_date']);
        $this->assertDatabaseMissing('tasks', [
            'title' => 'Task with Past Due Date',
        ]);
    }

    public function test_deep_task_hierarchy_creation()
    {
        // Create a 3-level hierarchy
        $level0 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Level 0',
            'priority' => 'low', // Specify priority to allow medium children
        ]);

        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Level 1',
                'parent_id' => $level0->id,
                'priority' => 'medium', // Valid since parent is low
                'status' => 'pending',
            ]);

        $level1 = Task::where('title', 'Level 1')->first();

        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Level 2',
                'parent_id' => $level1->id,
                'priority' => 'medium', // Valid since parent is medium
                'status' => 'pending',
            ]);

        $level2 = Task::where('title', 'Level 2')->first();

        // Verify hierarchy
        $this->assertEquals(0, $level0->getDepth());
        $this->assertEquals(1, $level1->getDepth());
        $this->assertEquals(2, $level2->getDepth());

        $this->assertEquals("{$level0->id}", $level0->path);
        $this->assertEquals("{$level0->id}/{$level1->id}", $level1->path);
        $this->assertEquals("{$level0->id}/{$level1->id}/{$level2->id}", $level2->path);
    }

    public function test_task_sort_order_within_parent()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent',
            'priority' => 'low', // Specify priority to allow medium children
        ]);

        // Create first child
        $response1 = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'First Child',
                'parent_id' => $parent->id,
                'priority' => 'medium', // Valid since parent is low
                'status' => 'pending',
            ]);

        // Create second child
        $response2 = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Second Child',
                'parent_id' => $parent->id,
                'priority' => 'medium', // Valid since parent is low
                'status' => 'pending',
            ]);

        $firstChild = Task::where('title', 'First Child')->first();
        $secondChild = Task::where('title', 'Second Child')->first();

        $this->assertEquals(1, $firstChild->sort_order);
        $this->assertEquals(2, $secondChild->sort_order);
    }

    public function test_task_validation_prevents_circular_references()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent',
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child',
        ]);

        // Try to make parent a child of its own child (circular reference)
        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$this->project->id}/tasks/{$parent->id}", [
                'title' => 'Parent',
                'parent_id' => $child->id, // This should be invalid
                'priority' => 'medium',
                'status' => 'pending',
            ]);

        $response->assertSessionHasErrors(['parent_id']);

        // Verify parent is still a parent
        $parent->refresh();
        $this->assertNull($parent->parent_id);
    }

    public function test_unauthorized_access_to_other_users_tasks()
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        $otherTask = Task::factory()->create(['project_id' => $otherProject->id]);

        // Try to create subtask in other user's project
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$otherProject->id}/tasks/{$otherTask->id}/subtasks/create");

        $response->assertStatus(403);

        // Try to create task in other user's project
        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$otherProject->id}/tasks", [
                'title' => 'Unauthorized Task',
                'priority' => 'medium',
                'status' => 'pending',
            ]);

        $response->assertStatus(403);
    }

    public function test_task_hierarchy_maintains_project_consistency()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent in Project A',
        ]);

        // Create another project
        $project2 = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->project->group_id,
        ]);

        // Try to create child in project2 with parent from project1
        $response = $this->actingAs($this->user)
            ->post("/dashboard/projects/{$project2->id}/tasks", [
                'title' => 'Child in Project B',
                'parent_id' => $parentTask->id, // Parent from different project
                'priority' => 'medium',
                'status' => 'pending',
            ]);

        $response->assertSessionHasErrors(['parent_id']);
    }
}
