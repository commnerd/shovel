<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskReorderingApiTest extends TestCase
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
    public function it_requires_authentication_to_reorder_tasks()
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/reorder", [
            'new_position' => 1,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_successfully_reorders_task_without_confirmation()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$task2->id}/reorder", [
                'new_position' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reorder_data' => [
                    'old_position' => 2,
                    'new_position' => 1,
                    'move_count' => 1,
                ],
            ]);
    }

    /** @test */
    public function it_returns_confirmation_required_for_priority_conflicts()
    {
        $highTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'sort_order' => 1,
        ]);

        $lowTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/{$lowTask->id}/reorder", [
                'new_position' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'requires_confirmation' => true,
            ])
            ->assertJsonStructure([
                'confirmation_data' => [
                    'type',
                    'message',
                    'task_priority',
                    'neighbor_priorities',
                ],
            ]);
    }
}
