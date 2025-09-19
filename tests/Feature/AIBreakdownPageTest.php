<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AIBreakdownPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected Task $task;

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

        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Complex Feature Implementation',
            'description' => 'Build a complex feature that needs breakdown',
            'status' => 'pending',
            'priority' => 'high',
        ]);
    }

    public function test_user_can_access_ai_breakdown_page()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$this->task->id}/breakdown");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Breakdown')
            ->where('project.id', $this->project->id)
            ->where('project.title', $this->project->title)
            ->where('task.id', $this->task->id)
            ->where('task.title', $this->task->title)
            ->where('task.description', $this->task->description)
            ->where('task.status', $this->task->status)
            ->where('task.priority', $this->task->priority)
            ->has('projectTaskCount')
        );
    }

    public function test_breakdown_page_requires_authentication()
    {
        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/{$this->task->id}/breakdown");
        $response->assertRedirect('/login');
    }

    public function test_breakdown_page_requires_project_ownership()
    {
        // Create different user and project
        $otherUser = User::factory()->create([
            'organization_id' => $this->user->organization_id,
            'pending_approval' => false,
        ]);
        $otherUser->joinGroup($this->user->groups->first());

        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->user->groups->first()->id,
        ]);

        $otherTask = Task::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$otherProject->id}/tasks/{$otherTask->id}/breakdown");

        $response->assertStatus(403);
    }

    public function test_breakdown_page_validates_task_belongs_to_project()
    {
        // Create task in different project
        $otherProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->user->groups->first()->id,
        ]);

        $taskFromOtherProject = Task::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$taskFromOtherProject->id}/breakdown");

        $response->assertStatus(404);
    }

    public function test_breakdown_page_shows_task_hierarchy_info()
    {
        // Create a parent task with children
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/breakdown");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Breakdown')
            ->where('task.has_children', true)
            ->where('task.is_leaf', false)
            ->where('task.is_top_level', true)
        );

        // Test child task
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$childTask->id}/breakdown");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Breakdown')
            ->where('task.has_children', false)
            ->where('task.is_leaf', true)
            ->where('task.is_top_level', false)
            ->where('task.parent_id', $parentTask->id)
        );
    }

    public function test_breakdown_page_includes_project_task_count()
    {
        // Create additional tasks for context
        Task::factory()->count(3)->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$this->task->id}/breakdown");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Breakdown')
            ->where('projectTaskCount', 4) // Original task + 3 additional
        );
    }

    public function test_breakdown_page_handles_task_with_due_date()
    {
        // Update task with due date
        $this->task->update(['due_date' => '2025-12-31']);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$this->task->id}/breakdown");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Breakdown')
            ->where('task.due_date', '2025-12-31')
        );
    }

    public function test_breakdown_page_handles_task_without_description()
    {
        // Create task without description
        $taskWithoutDesc = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task Without Description',
            'description' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$taskWithoutDesc->id}/breakdown");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Breakdown')
            ->where('task.title', 'Task Without Description')
            ->where('task.description', null)
        );
    }

    public function test_breakdown_page_breadcrumbs()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks/{$this->task->id}/breakdown");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Breakdown')
                // The breadcrumbs are built in the component, so we just verify the page loads
            ->where('project.title', $this->project->title)
            ->where('task.title', $this->task->title)
        );
    }

    public function test_task_index_loads_with_breakdown_button_available()
    {
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 1)
            ->where('tasks.0.id', $this->task->id)
            ->where('tasks.0.title', $this->task->title)
        );
    }

    public function test_breakdown_page_cross_organization_security()
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
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$otherProject->id}/tasks/{$otherTask->id}/breakdown");

        $response->assertStatus(403);
    }

    public function test_breakdown_page_with_complex_task_hierarchy()
    {
        // Create a complex hierarchy
        $grandParent = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Grandparent Task',
        ]);

        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $grandParent->id,
            'title' => 'Parent Task',
        ]);

        $child = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'title' => 'Child Task',
        ]);

        // Test accessing breakdown for each level
        foreach ([$grandParent, $parent, $child] as $testTask) {
            $response = $this->actingAs($this->user)
                ->get("/dashboard/projects/{$this->project->id}/tasks/{$testTask->id}/breakdown");

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Breakdown')
                ->where('task.id', $testTask->id)
                ->where('task.title', $testTask->title)
            );
        }
    }
}
