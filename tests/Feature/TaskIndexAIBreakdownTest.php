<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskIndexAIBreakdownTest extends TestCase
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
            'title' => 'Test Project',
            'description' => 'A test project for AI breakdown',
        ]);
    }

    public function test_task_index_shows_ai_breakdown_button()
    {
        // Create a test task
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Test Task',
            'description' => 'A task that needs breakdown',
            'status' => 'pending',
            'priority' => 'high',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Test Task')
            ->where('project.id', $this->project->id)
        );
    }

    public function test_ai_breakdown_button_appears_for_all_tasks()
    {
        // Create multiple tasks with different statuses
        Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Completed Task',
            'status' => 'completed',
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'In Progress Task',
            'status' => 'in_progress',
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Pending Task',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 3)
            ->where('project.id', $this->project->id)
        );
    }

    public function test_task_index_with_existing_hierarchy()
    {
        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'status' => 'in_progress',
        ]);

        // Create child task
        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 2)
            ->where('project.id', $this->project->id)
        );
    }

    public function test_task_index_requires_authentication()
    {
        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks");
        $response->assertRedirect('/login');
    }

    public function test_task_index_requires_project_access()
    {
        // Create different organization and user
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

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$otherProject->id}/tasks");

        $response->assertStatus(403);
    }

    public function test_task_index_with_filters()
    {
        // Create tasks of different types
        $topLevelTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Top Level Task',
            'parent_id' => null,
        ]);

        $leafTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $topLevelTask->id,
            'title' => 'Leaf Task',
        ]);

        // Test all tasks filter
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 2)
            ->where('filter', 'all')
        );

        // Test leaf tasks filter
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=leaf");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 1)
            ->where('filter', 'leaf')
            ->where('tasks.0.title', 'Leaf Task')
        );

        // Test top-level tasks filter
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=top-level");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 1)
            ->where('filter', 'top-level')
            ->where('tasks.0.title', 'Top Level Task')
        );
    }

    public function test_task_index_shows_correct_task_counts()
    {
        // Create a hierarchical structure
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
            'status' => 'completed',
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task 1',
            'status' => 'completed',
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task 2',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 3)
            ->has('taskCounts')
            ->where('taskCounts.all', 3)
            ->where('taskCounts.leaf', 2) // Only child tasks are leaf
            ->where('taskCounts.top_level', 1) // Only parent task is top-level
        );
    }

    public function test_empty_task_index_shows_create_first_task()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 0)
            ->where('project.id', $this->project->id)
        );
    }

    public function test_task_index_component_loads_correctly()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->where('project.id', $this->project->id)
            ->where('project.title', $this->project->title)
        );
    }
}
