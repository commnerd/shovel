<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\{Task, Project, User, Organization};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskPriorityAdjustmentTest extends TestCase
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

    public function test_task_priority_promoted_when_moved_to_higher_priority_neighbors()
    {
        // Create tasks: low, high, high (positions 1, 2, 3)
        $lowTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        $highTask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        $highTask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        // Move low priority task to position 3 (between two high priority tasks)
        $result = $lowTask->reorderTo(3, true); // confirmed = true

        $this->assertTrue($result['success']);
        $this->assertTrue($result['priority_changed']);
        $this->assertEquals('low', $result['old_priority']);
        $this->assertEquals('high', $result['new_priority']);
        $this->assertStringContainsString('Priority changed from low to high', $result['message']);

        // Verify task was actually updated
        $lowTask->refresh();
        $this->assertEquals('high', $lowTask->priority);
        $this->assertEquals(3, $lowTask->sort_order);
    }

    public function test_task_priority_demoted_when_moved_to_lower_priority_neighbors()
    {
        // Create tasks: high, low, low (positions 1, 2, 3)
        $highTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        $lowTask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        $lowTask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        // Move high priority task to position 3 (between two low priority tasks)
        $result = $highTask->reorderTo(3, true); // confirmed = true

        $this->assertTrue($result['success']);
        $this->assertTrue($result['priority_changed']);
        $this->assertEquals('high', $result['old_priority']);
        $this->assertEquals('low', $result['new_priority']);
        $this->assertStringContainsString('Priority changed from high to low', $result['message']);

        // Verify task was actually updated
        $highTask->refresh();
        $this->assertEquals('low', $highTask->priority);
        $this->assertEquals(3, $highTask->sort_order);
    }

    public function test_task_priority_adjusted_to_medium_when_moving_between_mixed_priorities()
    {
        // Create tasks: high, low, medium (positions 1, 2, 3)
        $highTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        $lowTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        $mediumTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        // Move low priority task to position 1 (near high priority)
        $result = $lowTask->reorderTo(1, true);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['priority_changed']);
        $this->assertEquals('low', $result['old_priority']);
        $this->assertEquals('high', $result['new_priority']);
    }

    public function test_no_priority_change_when_moving_to_same_priority_neighbors()
    {
        // Create tasks: medium, medium, medium
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        // Move task to different position but same priority neighbors
        $result = $task1->reorderTo(3, true);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['priority_changed']);
        $this->assertNull($result['old_priority']);
        $this->assertNull($result['new_priority']);
        $this->assertStringNotContainsString('Priority changed', $result['message']);

        // Verify priority unchanged
        $task1->refresh();
        $this->assertEquals('medium', $task1->priority);
    }

    public function test_no_priority_change_when_not_confirmed()
    {
        // Create tasks: low, high
        $lowTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        $highTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        // Move without confirmation - should require confirmation
        $result = $lowTask->reorderTo(2, false); // confirmed = false

        $this->assertFalse($result['success']);
        $this->assertTrue($result['requires_confirmation']);
        $this->assertArrayNotHasKey('priority_changed', $result);

        // Verify no changes
        $lowTask->refresh();
        $this->assertEquals('low', $lowTask->priority);
        $this->assertEquals(1, $lowTask->sort_order);
    }

    public function test_priority_adjustment_with_multiple_neighbor_priorities()
    {
        // Create tasks with mixed priorities: low, high, medium, high
        $lowTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        $highTask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        $mediumTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        $highTask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 4,
            'parent_id' => null,
        ]);

        // Move low task to position 3 (neighbors are high and medium)
        // Should adopt the highest neighbor priority
        $result = $lowTask->reorderTo(3, true);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['priority_changed']);
        $this->assertEquals('low', $result['old_priority']);
        $this->assertEquals('high', $result['new_priority']); // Should adopt highest neighbor priority

        $lowTask->refresh();
        $this->assertEquals('high', $lowTask->priority);
    }

    public function test_priority_adjustment_only_affects_moved_task()
    {
        // Create tasks: low, high, high
        $lowTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        $highTask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        $highTask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        // Move low task to position 3
        $lowTask->reorderTo(3, true);

        // Verify only the moved task changed priority
        $lowTask->refresh();
        $highTask1->refresh();
        $highTask2->refresh();

        $this->assertEquals('high', $lowTask->priority); // Changed
        $this->assertEquals('high', $highTask1->priority); // Unchanged
        $this->assertEquals('high', $highTask2->priority); // Unchanged
    }
}
