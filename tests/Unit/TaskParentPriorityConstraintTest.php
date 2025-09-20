<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\{Task, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskParentPriorityConstraintTest extends TestCase
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

    public function test_child_task_can_have_same_priority_as_parent()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $childTask = new Task(['parent_id' => $parentTask->id]);
        $childTask->setRelation('parent', $parentTask);

        $validation = $childTask->validateParentPriorityConstraint('medium');

        $this->assertTrue($validation['valid']);
    }

    public function test_child_task_can_have_higher_priority_than_parent()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'parent_id' => null,
        ]);

        $childTask = new Task(['parent_id' => $parentTask->id]);
        $childTask->setRelation('parent', $parentTask);

        $validation = $childTask->validateParentPriorityConstraint('high');

        $this->assertTrue($validation['valid']);
    }

    public function test_child_task_cannot_have_lower_priority_than_parent()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $childTask = new Task(['parent_id' => $parentTask->id]);
        $childTask->setRelation('parent', $parentTask);

        $validation = $childTask->validateParentPriorityConstraint('low');

        $this->assertFalse($validation['valid']);
        $this->assertEquals('high', $validation['parent_priority']);
        $this->assertEquals('low', $validation['attempted_priority']);
        $this->assertEquals('high', $validation['minimum_allowed_priority']);
        $this->assertStringContainsString('cannot have lower priority', $validation['error']);
    }

    public function test_top_level_task_has_no_priority_constraints()
    {
        $topLevelTask = new Task(['parent_id' => null]);

        $validation = $topLevelTask->validateParentPriorityConstraint('low');

        $this->assertTrue($validation['valid']);
    }

    public function test_priority_constraint_validation_with_different_combinations()
    {
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $childTask = new Task(['parent_id' => $parentTask->id]);
        $childTask->setRelation('parent', $parentTask);

        // Test all combinations
        $testCases = [
            ['parent' => 'high', 'child' => 'high', 'expected' => true],
            ['parent' => 'high', 'child' => 'medium', 'expected' => false],
            ['parent' => 'high', 'child' => 'low', 'expected' => false],
            ['parent' => 'medium', 'child' => 'high', 'expected' => true],
            ['parent' => 'medium', 'child' => 'medium', 'expected' => true],
            ['parent' => 'medium', 'child' => 'low', 'expected' => false],
            ['parent' => 'low', 'child' => 'high', 'expected' => true],
            ['parent' => 'low', 'child' => 'medium', 'expected' => true],
            ['parent' => 'low', 'child' => 'low', 'expected' => true],
        ];

        foreach ($testCases as $case) {
            $parentTask->priority = $case['parent'];
            $validation = $childTask->validateParentPriorityConstraint($case['child']);

            $this->assertEquals($case['expected'], $validation['valid'],
                "Parent: {$case['parent']}, Child: {$case['child']} should be " .
                ($case['expected'] ? 'valid' : 'invalid'));
        }
    }

    public function test_priority_adjustment_respects_parent_constraints()
    {
        // Create parent with high priority
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        // Create child with high priority (valid)
        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
            'parent_id' => $parentTask->id,
        ]);

        // Create low priority sibling to test against
        $lowSibling = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 2,
            'parent_id' => $parentTask->id,
        ]);

        // Try to move child to low priority area - should be constrained by parent
        $result = $childTask->reorderTo(2, true);

        $this->assertTrue($result['success']);

        // Priority should not go below parent's priority
        $childTask->refresh();
        $this->assertEquals('high', $childTask->priority); // Should remain high due to parent constraint
    }

    public function test_deep_hierarchy_priority_constraints()
    {
        // Create: High Parent -> Medium Child -> ? Grandchild
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high', // Same as parent (valid)
            'parent_id' => $parentTask->id,
        ]);

        $grandchildTask = new Task(['parent_id' => $childTask->id]);
        $grandchildTask->setRelation('parent', $childTask);

        // Grandchild can be high (same as parent)
        $validation1 = $grandchildTask->validateParentPriorityConstraint('high');
        $this->assertTrue($validation1['valid']);

        // Grandchild cannot be medium (lower than parent)
        $validation2 = $grandchildTask->validateParentPriorityConstraint('medium');
        $this->assertFalse($validation2['valid']);

        // Grandchild cannot be low (lower than parent)
        $validation3 = $grandchildTask->validateParentPriorityConstraint('low');
        $this->assertFalse($validation3['valid']);
    }
}
