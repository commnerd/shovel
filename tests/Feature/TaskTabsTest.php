<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Project, Task};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskTabsTest extends TestCase
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

    public function test_task_index_defaults_to_list_view()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'top-level')
        );
    }

    public function test_can_access_breakdown_view()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'all')
        );
    }

    public function test_can_access_todo_view()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=leaf");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'leaf')
        );
    }

    public function test_can_access_board_view()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=board");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->where('filter', 'board')
        );
    }

    public function test_list_view_shows_only_top_level_tasks()
    {
        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'priority' => 'medium',
        ]);

        // Create subtask
        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Subtask',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=top-level");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->has('tasks', 1) // Should only show parent task
                 ->where('tasks.0.title', 'Parent Task')
        );
    }

    public function test_breakdown_view_shows_all_tasks_hierarchically()
    {
        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'priority' => 'medium',
        ]);

        // Create subtask
        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Subtask',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->has('tasks', 2) // Should show both parent and subtask
        );
    }

    public function test_todo_view_shows_only_leaf_tasks()
    {
        // Create parent task with subtask (parent is not leaf)
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'priority' => 'medium',
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Subtask',
            'priority' => 'medium',
        ]);

        // Create standalone task (is leaf)
        $standaloneTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Standalone Task',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=leaf");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->has('tasks', 2) // Should show subtask and standalone task (both are leaf)
        );
    }

    public function test_board_view_includes_all_tasks_for_kanban()
    {
        // Create tasks with different statuses
        $pendingTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
            'priority' => 'high',
        ]);

        $inProgressTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        $completedTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'completed',
            'priority' => 'low',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=board");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->has('tasks', 3) // Should show all tasks for Kanban board
                 ->where('filter', 'board')
        );
    }

    public function test_task_counts_are_provided_for_all_tabs()
    {
        // Create various tasks
        $topLevelTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Top Level',
            'priority' => 'medium',
        ]);

        $subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $topLevelTask->id,
            'title' => 'Subtask',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Tasks/Index')
                 ->has('taskCounts')
                 ->has('taskCounts.all')
                 ->has('taskCounts.top_level')
                 ->has('taskCounts.leaf')
                 ->where('taskCounts.all', 2)
                 ->where('taskCounts.top_level', 1)
                 ->where('taskCounts.leaf', 1)
        );
    }

    public function test_unauthorized_user_cannot_access_any_tab_view()
    {
        $otherUser = User::factory()->create();

        $views = ['top-level', 'all', 'leaf', 'board'];

        foreach ($views as $view) {
            $response = $this->actingAs($otherUser)
                ->get("/dashboard/projects/{$this->project->id}/tasks?filter={$view}");

            $response->assertStatus(403);
        }
    }
}
