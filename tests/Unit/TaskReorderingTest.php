<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskReorderingTest extends TestCase
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

    /** @test */
    public function it_initializes_order_tracking_for_new_tasks()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'sort_order' => 3,
        ]);

        $task->initializeOrderTracking();
        $task->refresh();

        $this->assertEquals(3, $task->initial_order_index);
        $this->assertEquals(3, $task->current_order_index);
        $this->assertEquals(0, $task->move_count);
    }

    /** @test */
    public function it_converts_priority_to_numeric_levels_correctly()
    {
        $highTask = Task::factory()->create(['priority' => 'high']);
        $mediumTask = Task::factory()->create(['priority' => 'medium']);
        $lowTask = Task::factory()->create(['priority' => 'low']);

        $this->assertEquals(3, $highTask->getPriorityLevel());
        $this->assertEquals(2, $mediumTask->getPriorityLevel());
        $this->assertEquals(1, $lowTask->getPriorityLevel());
    }

    /** @test */
    public function it_requires_confirmation_when_moving_low_priority_task_near_high_priority()
    {
        // Create sibling tasks with different priorities
        $highTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        $lowTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 3,
            'parent_id' => null,
        ]);

        // Try to move low priority task to position 1 (next to high priority)
        $confirmation = $lowTask->checkReorderConfirmation(1);

        $this->assertNotNull($confirmation);
        $this->assertEquals('moving_to_higher_priority', $confirmation['type']);
        $this->assertEquals('low', $confirmation['task_priority']);
        $this->assertContains('high', $confirmation['neighbor_priorities']);
    }

    /** @test */
    public function it_successfully_reorders_task_without_confirmation_needed()
    {
        // Create sibling tasks with same priority
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

        $result = $task3->reorderTo(1);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['old_position']);
        $this->assertEquals(1, $result['new_position']);
        $this->assertEquals(1, $result['move_count']);

        // Verify task was moved
        $task3->refresh();
        $this->assertEquals(1, $task3->sort_order);
        $this->assertEquals(1, $task3->current_order_index);
        $this->assertEquals(1, $task3->move_count);
        $this->assertNotNull($task3->last_moved_at);
    }

    /** @test */
    public function it_tracks_move_count_correctly()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'sort_order' => 2,
            'parent_id' => null,
        ]);

        // Initial state
        $task->initializeOrderTracking();
        $this->assertEquals(0, $task->move_count);

        // First move
        $task->reorderTo(2, true);
        $task->refresh();
        $this->assertEquals(1, $task->move_count);

        // Second move
        $task->reorderTo(1, true);
        $task->refresh();
        $this->assertEquals(2, $task->move_count);
    }
}
