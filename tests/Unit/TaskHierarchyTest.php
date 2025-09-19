<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Models\Organization;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected Organization $organization;
    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $this->organization = Organization::getDefault();
        $this->group = $this->organization->createDefaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($this->group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);
    }

    public function test_task_can_have_parent()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task',
        ]);

        $this->assertEquals($parentTask->id, $childTask->parent_id);
        $this->assertEquals($parentTask->id, $childTask->parent->id);
    }

    public function test_task_can_have_children()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        $child1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child 1',
            'sort_order' => 1,
        ]);

        $child2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child 2',
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $parentTask->children);
        $this->assertEquals('Child 1', $parentTask->children->first()->title);
        $this->assertEquals('Child 2', $parentTask->children->last()->title);
    }

    public function test_task_hierarchy_depth_calculation()
    {
        $level0 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Level 0',
        ]);

        $level1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $level0->id,
            'title' => 'Level 1',
        ]);

        $level2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $level1->id,
            'title' => 'Level 2',
        ]);

        $this->assertEquals(0, $level0->getDepth());
        $this->assertEquals(1, $level1->getDepth());
        $this->assertEquals(2, $level2->getDepth());
    }

    public function test_task_is_top_level()
    {
        $topLevel = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $topLevel->id,
        ]);

        $this->assertTrue($topLevel->isTopLevel());
        $this->assertFalse($child->isTopLevel());
    }

    public function test_task_is_leaf()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
        ]);

        $this->assertFalse($parent->isLeaf());
        $this->assertTrue($child->isLeaf());
    }

    public function test_task_scope_top_level()
    {
        $topLevel1 = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $topLevel2 = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $topLevel1->id,
        ]);

        $topLevelTasks = Task::topLevel()->get();

        $this->assertCount(2, $topLevelTasks);
        $this->assertTrue($topLevelTasks->contains($topLevel1));
        $this->assertTrue($topLevelTasks->contains($topLevel2));
        $this->assertFalse($topLevelTasks->contains($child));
    }

    public function test_task_scope_leaf()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $child1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
        ]);

        $child2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
        ]);

        $leafTasks = Task::leaf()->get();

        $this->assertCount(2, $leafTasks);
        $this->assertTrue($leafTasks->contains($child1));
        $this->assertTrue($leafTasks->contains($child2));
        $this->assertFalse($leafTasks->contains($parent));
    }

    public function test_task_scope_with_children()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $standalone = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
        ]);

        $tasksWithChildren = Task::withChildren()->get();

        $this->assertCount(1, $tasksWithChildren);
        $this->assertTrue($tasksWithChildren->contains($parent));
        $this->assertFalse($tasksWithChildren->contains($standalone));
        $this->assertFalse($tasksWithChildren->contains($child));
    }

    public function test_task_update_hierarchy_path()
    {
        $level0 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Level 0',
        ]);

        $level1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $level0->id,
            'title' => 'Level 1',
        ]);

        $level2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $level1->id,
            'title' => 'Level 2',
        ]);

        $level2->updateHierarchyPath();

        $this->assertEquals("{$level0->id}/{$level1->id}/{$level2->id}", $level2->path);
        $this->assertEquals(2, $level2->depth);
    }

    public function test_task_get_ancestors()
    {
        $grandparent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Grandparent',
        ]);

        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $grandparent->id,
            'title' => 'Parent',
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child',
        ]);

        $ancestors = $child->ancestors();

        $this->assertCount(2, $ancestors);
        $this->assertEquals($parent->id, $ancestors->first()->id);
        $this->assertEquals($grandparent->id, $ancestors->last()->id);
    }

    public function test_task_get_root()
    {
        $root = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Root',
        ]);

        $level1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $root->id,
            'title' => 'Level 1',
        ]);

        $level2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $level1->id,
            'title' => 'Level 2',
        ]);

        $this->assertEquals($root->id, $level2->getRoot()->id);
        $this->assertEquals($root->id, $level1->getRoot()->id);
        $this->assertEquals($root->id, $root->getRoot()->id);
    }

    public function test_task_siblings()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent',
        ]);

        $child1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child 1',
            'sort_order' => 1,
        ]);

        $child2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child 2',
            'sort_order' => 2,
        ]);

        $child3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child 3',
            'sort_order' => 3,
        ]);

        $siblings = $child2->siblings()->get();

        $this->assertCount(2, $siblings);
        $this->assertTrue($siblings->contains($child1));
        $this->assertTrue($siblings->contains($child3));
        $this->assertFalse($siblings->contains($child2));
    }

    public function test_task_get_next_child_sort_order()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        // No children yet
        $this->assertEquals(1, $parent->getNextChildSortOrder());

        $child1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'sort_order' => 5,
        ]);

        $child2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'sort_order' => 10,
        ]);

        $this->assertEquals(11, $parent->getNextChildSortOrder());
    }

    public function test_task_completion_percentage()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'in_progress',
        ]);

        // Test leaf task completion
        $this->assertEquals(0.0, $parent->getCompletionPercentage());

        $parent->update(['status' => 'completed']);
        $this->assertEquals(100.0, $parent->getCompletionPercentage());

        // Test parent with children
        $parent->update(['status' => 'in_progress']);

        $child1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'status' => 'completed',
        ]);

        $child2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'status' => 'pending',
        ]);

        $child3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'status' => 'completed',
        ]);

        // 2 out of 3 children completed = 66.67%
        $this->assertEquals(66.67, round($parent->getCompletionPercentage(), 2));
    }

    public function test_task_has_incomplete_descendants()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->assertFalse($parent->hasIncompleteDescendants());

        $completedChild = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'status' => 'completed',
        ]);

        $this->assertFalse($parent->hasIncompleteDescendants());

        $incompleteChild = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'status' => 'pending',
        ]);

        $this->assertTrue($parent->hasIncompleteDescendants());
    }

    public function test_task_cascade_deletion()
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
        ]);

        $grandchild = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $child->id,
        ]);

        $parentId = $parent->id;
        $childId = $child->id;
        $grandchildId = $grandchild->id;

        $parent->delete();

        // Verify cascade deletion
        $this->assertDatabaseMissing('tasks', ['id' => $parentId]);
        $this->assertDatabaseMissing('tasks', ['id' => $childId]);
        $this->assertDatabaseMissing('tasks', ['id' => $grandchildId]);
    }

    public function test_task_due_date_casting()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'due_date' => '2025-12-31',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $task->due_date);
        $this->assertEquals('2025-12-31', $task->due_date->format('Y-m-d'));
    }

    public function test_task_fillable_attributes()
    {
        $fillable = (new Task())->getFillable();

        $expectedFillable = [
            'project_id',
            'parent_id',
            'title',
            'description',
            'status',
            'priority',
            'depth',
            'path',
            'sort_order',
            'due_date',
        ];

        foreach ($expectedFillable as $field) {
            $this->assertContains($field, $fillable);
        }
    }

    public function test_task_descendants_relationship()
    {
        $root = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Root',
        ]);

        $child1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $root->id,
            'title' => 'Child 1',
        ]);

        $grandchild1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $child1->id,
            'title' => 'Grandchild 1',
        ]);

        $child2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $root->id,
            'title' => 'Child 2',
        ]);

        // Test descendants include all levels
        $descendants = $root->descendants()->get();

        $this->assertGreaterThanOrEqual(2, $descendants->count());
        $descendantIds = $descendants->pluck('id')->toArray();
        $this->assertContains($child1->id, $descendantIds);
        $this->assertContains($child2->id, $descendantIds);
    }
}
