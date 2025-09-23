<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\DailyCuration;
use App\Models\Organization;
use App\Models\Group;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Carbon\Carbon;

class TodaysTasksBasicTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure AI provider for tests
        Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebras API Key');

        // Create test organization structure
        $this->organization = Organization::factory()->create();
        $this->group = Group::factory()->create(['organization_id' => $this->organization->id]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'email_verified_at' => now(),
            'pending_approval' => false,
        ]);
    }

    public function test_user_can_access_todays_tasks_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard/todays-tasks');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) =>
            $page->component('TodaysTasks/Index')
                ->has('curations')
                ->has('tasks')
                ->has('priorityTasks')
                ->has('activeProjects')
                ->has('stats')
        );
    }

    public function test_todays_tasks_requires_authentication(): void
    {
        $response = $this->get('/dashboard/todays-tasks');
        $response->assertRedirect('/login');
    }

    public function test_empty_state_when_no_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard/todays-tasks');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) =>
            $page->has('curations', 0)
                ->has('priorityTasks', 0)
                ->where('stats.total_curations', 0)
                ->where('stats.priority_tasks', 0)
        );
    }

    public function test_todays_tasks_shows_curations(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'status' => 'active',
        ]);

        $curation = DailyCuration::factory()->forToday()->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard/todays-tasks');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) =>
            $page->has('curations', 1)
                ->where('curations.0.id', $curation->id)
        );
    }

    public function test_todays_tasks_shows_priority_tasks(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'status' => 'active',
        ]);

        // Create overdue task
        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Overdue Task',
            'status' => 'pending',
            'due_date' => Carbon::now()->subDay(),
        ]);

        // Create in-progress task
        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'In Progress Task',
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard/todays-tasks');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) =>
            $page->has('priorityTasks', 2)
        );
    }

    public function test_user_can_complete_task(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->post("/dashboard/todays-tasks/tasks/{$task->id}/complete");

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $task->refresh();
        $this->assertEquals('completed', $task->status);
    }

    public function test_user_can_update_task_status(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->patch("/dashboard/todays-tasks/tasks/{$task->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $task->refresh();
        $this->assertEquals('in_progress', $task->status);
    }

    public function test_user_can_dismiss_curation(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $curation = DailyCuration::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post("/dashboard/todays-tasks/curations/{$curation->id}/dismiss");

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $curation->refresh();
        $this->assertTrue($curation->isDismissed());
    }

    public function test_user_cannot_access_others_data(): void
    {
        $otherUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->group->id,
        ]);

        $otherCuration = DailyCuration::factory()->create([
            'user_id' => $otherUser->id,
            'project_id' => $otherProject->id,
        ]);

        $otherTask = Task::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        // Should not see other user's curations
        $response = $this->actingAs($this->user)
            ->get('/dashboard/todays-tasks');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) =>
            $page->has('curations', 0)
        );

        // Should not be able to dismiss other's curation
        $response = $this->actingAs($this->user)
            ->post("/dashboard/todays-tasks/curations/{$otherCuration->id}/dismiss");

        $response->assertStatus(403);

        // Should not be able to modify other's tasks
        $response = $this->actingAs($this->user)
            ->post("/dashboard/todays-tasks/tasks/{$otherTask->id}/complete");

        $response->assertStatus(403);
    }

    public function test_curation_model_basic_functionality(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $curation = DailyCuration::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'viewed_at' => null,
            'dismissed_at' => null,
        ]);

        // Test initial state
        $this->assertTrue($curation->isNew());
        $this->assertFalse($curation->isDismissed());

        // Test mark as viewed
        $curation->markAsViewed();
        $this->assertFalse($curation->isNew());

        // Test dismiss
        $curation->dismiss();
        $this->assertTrue($curation->isDismissed());
    }

    public function test_daily_curation_command_exists(): void
    {
        // Test that the command can be called
        $this->artisan('curation:daily --dry-run')
            ->expectsOutput('Starting daily curation and iteration management...')
            ->expectsOutput('Daily curation and iteration management completed successfully!')
            ->assertSuccessful();
    }
}
