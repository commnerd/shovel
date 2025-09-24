<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskCountAndEmptyStateBugfixTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);

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
            'description' => 'A test project for bug fixes',
        ]);
    }

    public function test_projects_index_shows_correct_task_count()
    {
        // Create multiple tasks for the project
        Task::factory()->count(3)->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Index')
            ->has('projects', 1)
            ->where('projects.0.tasks_count', 3)
            ->where('projects.0.id', $this->project->id)
        );
    }

    public function test_projects_index_shows_zero_task_count_for_empty_project()
    {
        // Don't create any tasks - project should show 0 tasks
        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Index')
            ->has('projects', 1)
            ->where('projects.0.tasks_count', 0)
            ->where('projects.0.id', $this->project->id)
        );
    }

    public function test_task_index_shows_tasks_when_they_exist()
    {
        // Create tasks
        Task::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'title' => 'Test Task',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 2)
            ->where('project.id', $this->project->id)
        );
    }

    public function test_task_index_shows_empty_state_when_no_tasks()
    {
        // Don't create any tasks
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 0)
            ->where('project.id', $this->project->id)
        );
    }

    public function test_task_index_does_not_show_empty_state_with_tasks()
    {
        // Create a task
        Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Existing Task',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Existing Task')
            ->where('project.id', $this->project->id)
        );
    }

    public function test_projects_index_task_count_updates_when_tasks_added()
    {
        // Initially no tasks
        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertInertia(fn (Assert $page) => $page->where('projects.0.tasks_count', 0)
        );

        // Add tasks
        Task::factory()->count(5)->create([
            'project_id' => $this->project->id,
        ]);

        // Check count is updated
        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertInertia(fn (Assert $page) => $page->where('projects.0.tasks_count', 5)
        );
    }

    public function test_projects_index_task_count_with_hierarchical_tasks()
    {
        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        // Create child tasks
        Task::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Index')
            ->where('projects.0.tasks_count', 4) // 1 parent + 3 children
        );
    }

    public function test_task_index_with_hierarchical_tasks_shows_all()
    {
        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task',
        ]);

        // Create child tasks
        Task::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Child Task',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks', 3) // Should show all tasks regardless of hierarchy
            ->where('project.id', $this->project->id)
        );
    }

    public function test_multiple_projects_show_correct_individual_task_counts()
    {
        // Create another project
        $project2 = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->user->groups->first()->id,
            'title' => 'Second Project',
        ]);

        // Add different numbers of tasks to each project
        Task::factory()->count(2)->create(['project_id' => $this->project->id]);
        Task::factory()->count(5)->create(['project_id' => $project2->id]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Index')
            ->has('projects', 2)
        );

        // Check that each project has the correct task count
        $responseData = $response->viewData('page')['props']['projects'];
        $project1Data = collect($responseData)->firstWhere('id', $this->project->id);
        $project2Data = collect($responseData)->firstWhere('id', $project2->id);

        $this->assertEquals(2, $project1Data['tasks_count']);
        $this->assertEquals(5, $project2Data['tasks_count']);
    }

    public function test_task_counts_are_consistent_between_pages()
    {
        // Create tasks
        Task::factory()->count(3)->create([
            'project_id' => $this->project->id,
        ]);

        // Check projects index shows correct count
        $projectsResponse = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $projectsResponse->assertInertia(fn (Assert $page) => $page->where('projects.0.tasks_count', 3)
        );

        // Check tasks index shows same number of tasks
        $tasksResponse = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/tasks");

        $tasksResponse->assertInertia(fn (Assert $page) => $page->has('tasks', 3)
        );
    }
}
