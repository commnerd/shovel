<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WaitlistSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_correct_project_metrics(): void
    {
        $user = User::factory()->create();

        // Create projects with different statuses
        $activeProject = Project::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'due_date' => now()->addDays(7),
        ]);

        $completedProject = Project::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'due_date' => now()->addDays(7),
        ]);

        $overdueProject = Project::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'due_date' => now()->subDays(3), // overdue
        ]);

        // Create project for another user (should not be counted)
        $otherUser = User::factory()->create();
        Project::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('projectMetrics')
            ->where('projectMetrics.total', 3)
            ->where('projectMetrics.active', 2)
            ->where('projectMetrics.completed', 1)
            ->where('projectMetrics.overdue', 1)
        );
    }

    public function test_dashboard_shows_correct_leaf_task_metrics(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create leaf tasks (tasks without children)
        $completedLeafTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $inProgressLeafTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $pendingLeafTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'priority' => 'low',
            'parent_id' => null,
        ]);

        $highPriorityLeafTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'priority' => 'high',
            'parent_id' => null,
        ]);

        // Create a parent task with children (should not be counted as leaf)
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'priority' => 'high',
            'parent_id' => null,
        ]);

        // Create child task (this parent task is no longer a leaf, but child is a leaf)
        $childTask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'status' => 'completed',
            'priority' => 'medium',
        ]);

        // Create task for another user's project (should not be counted)
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        Task::factory()->create([
            'project_id' => $otherProject->id,
            'status' => 'completed',
            'priority' => 'high',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('taskMetrics')
            ->where('taskMetrics.totalLeaf', 5) // 5 leaf tasks for this user (4 top-level + 1 child)
            ->where('taskMetrics.completed', 2) // 2 completed leaf tasks (completedLeafTask + childTask)
            ->where('taskMetrics.pending', 2) // 2 pending leaf tasks
            ->where('taskMetrics.inProgress', 1) // 1 in progress leaf task
            ->where('taskMetrics.highPriority', 2) // 2 high priority leaf tasks
        );
    }

    public function test_dashboard_shows_zero_metrics_for_user_with_no_data(): void
    {
        $user = User::factory()->create();

        // Create some waitlist subscribers for global count
        WaitlistSubscriber::factory()->count(3)->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('waitlistCount', 3)
            ->where('projectMetrics.total', 0)
            ->where('projectMetrics.active', 0)
            ->where('projectMetrics.completed', 0)
            ->where('projectMetrics.overdue', 0)
            ->where('taskMetrics.totalLeaf', 0)
            ->where('taskMetrics.completed', 0)
            ->where('taskMetrics.pending', 0)
            ->where('taskMetrics.inProgress', 0)
            ->where('taskMetrics.highPriority', 0)
        );
    }

    public function test_dashboard_correctly_identifies_overdue_projects(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'overdue-test-' . uniqid() . '.com'
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        // Create overdue active project
        Project::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'due_date' => now()->subDays(5),
        ]);

        // Create overdue completed project (should not be counted as overdue)
        Project::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'due_date' => now()->subDays(5),
        ]);

        // Create active project that's not overdue
        Project::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'due_date' => now()->addDays(5),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('projectMetrics.total', 3)
            ->where('projectMetrics.active', 2)
            ->where('projectMetrics.completed', 1)
            ->where('projectMetrics.overdue', 1) // Only the active overdue project
        );
    }

    public function test_dashboard_only_shows_leaf_tasks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create a parent task
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'priority' => 'high',
            'parent_id' => null,
        ]);

        // Create child tasks (these are leaf tasks)
        $childTask1 = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'status' => 'completed',
            'priority' => 'high',
        ]);

        $childTask2 = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        // Create a grandchild task
        Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $childTask1->id,
            'status' => 'in_progress',
            'priority' => 'low',
        ]);

        // Now childTask1 is no longer a leaf, but childTask2 and grandchild are leafs
        // parentTask is definitely not a leaf

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('taskMetrics.totalLeaf', 2) // childTask2 and grandchild
            ->where('taskMetrics.completed', 0) // grandchild is in_progress, childTask2 is pending
            ->where('taskMetrics.pending', 1) // childTask2
            ->where('taskMetrics.inProgress', 1) // grandchild
            ->where('taskMetrics.highPriority', 0) // neither leaf task is high priority
        );
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }
}
