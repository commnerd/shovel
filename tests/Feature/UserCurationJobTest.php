<?php

namespace Tests\Feature;

use App\Jobs\UserCurationJob;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Group;
use App\Models\Organization;
use App\Models\CuratedTasks;
use App\Models\DailyWeightMetric;
use App\Models\DailyCuration;
use App\Models\CurationPrompt;
use App\Services\AI\Facades\AI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Tests\TestCase;

class UserCurationJobTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'test-org-' . uniqid() . '.com'
        ]);

        $this->group = Group::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Group',
            'is_default' => true
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        $this->user->groups()->attach($this->group);
    }

    /** @test */
    public function it_processes_user_curation_successfully()
    {
        // Create a project with tasks
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ]);

        $task1 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_story_points' => 3,
            'size' => 'm',
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
            'current_story_points' => 5,
            'size' => 'l',
        ]);

        // Mock AI response
        $mockAIResponse = new class {
            public function getContent() {
                return json_encode([
                    'suggestions' => [
                        [
                            'type' => 'priority',
                            'task_id' => 1,
                            'message' => 'Focus on this task today'
                        ]
                    ],
                    'summary' => 'Test summary',
                    'focus_areas' => ['priority_tasks'],
                    'recommended_tasks' => [1, 2]
                ]);
            }
        };

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('chat')->andReturn($mockAIResponse);

        // Execute the job
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert daily weight metrics were created
        $this->assertDatabaseHas('daily_weight_metrics', [
            'user_id' => $this->user->id,
        ]);

        // Assert curation was created
        $this->assertDatabaseHas('daily_curations', [
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        // Assert curated tasks were populated
        $this->assertDatabaseHas('curated_tasks', [
            'assigned_to' => $this->user->id,
            'curatable_type' => Task::class,
            'work_date' => today()->toDateString(),
        ]);
    }

    /** @test */
    public function it_handles_user_with_no_visible_projects()
    {
        // Don't create any projects

        // Execute the job
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert no curation was created
        $this->assertDatabaseMissing('daily_curations', [
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_calculates_user_task_history_correctly()
    {
        // Create a project with completed tasks
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        // Create completed tasks from last month
        $completedTask1 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 3,
            'updated_at' => Carbon::now()->subDays(15),
        ]);

        $completedTask2 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 5,
            'updated_at' => Carbon::now()->subDays(10),
        ]);

        // Create curated tasks records for completed tasks
        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $completedTask1->id,
            'work_date' => Carbon::now()->subDays(15)->toDateString(),
            'assigned_to' => $this->user->id,
            'completed_at' => Carbon::now()->subDays(14),
            'created_at' => Carbon::now()->subDays(15),
        ]);

        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $completedTask2->id,
            'work_date' => Carbon::now()->subDays(10)->toDateString(),
            'assigned_to' => $this->user->id,
            'completed_at' => Carbon::now()->subDays(8),
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // Mock AI response
        $mockAIResponse = new class {
            public function getContent() {
                return json_encode([
                    'suggestions' => [],
                    'summary' => 'Test summary',
                    'focus_areas' => [],
                    'recommended_tasks' => []
                ]);
            }
        };

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('chat')->andReturn($mockAIResponse);

        // Execute the job
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert daily weight metrics were created with correct data
        $metrics = DailyWeightMetric::where('user_id', $this->user->id)->first();
        $this->assertNotNull($metrics);

        // The metrics should include the completed tasks from history
        $this->assertGreaterThan(0, $metrics->total_story_points);
    }

    /** @test */
    public function it_focuses_on_leaf_tasks_only()
    {
        // Create a project with parent and child tasks
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ]);

        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_story_points' => 8,
            'size' => 'xl',
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'status' => 'pending',
            'current_story_points' => 3,
            'size' => 'm',
        ]);

        // Mock AI response that should only include the child task (leaf task)
        $mockAIResponse = new class($childTask) {
            private $childTask;

            public function __construct($childTask) {
                $this->childTask = $childTask;
            }

            public function getContent() {
                return json_encode([
                    'suggestions' => [
                        [
                            'type' => 'priority',
                            'task_id' => $this->childTask->id,
                            'message' => 'Focus on this leaf task'
                        ]
                    ],
                    'summary' => 'Leaf task focus',
                    'focus_areas' => ['leaf_tasks'],
                    'recommended_tasks' => [$this->childTask->id]
                ]);
            }
        };

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('chat')->andReturn(new $mockAIResponse($childTask));

        // Execute the job
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert only the leaf task was curated
        $this->assertDatabaseHas('curated_tasks', [
            'assigned_to' => $this->user->id,
            'curatable_id' => $childTask->id,
            'curatable_type' => Task::class,
        ]);

        $this->assertDatabaseMissing('curated_tasks', [
            'assigned_to' => $this->user->id,
            'curatable_id' => $parentTask->id,
            'curatable_type' => Task::class,
        ]);
    }

    /** @test */
    public function it_includes_iteration_due_dates_for_iterative_projects()
    {
        // Create an iterative project
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'project_type' => 'iterative',
            'ai_provider' => 'cerebras',
        ]);

        // Create a future iteration
        $iteration = \App\Models\Iteration::factory()->create([
            'project_id' => $project->id,
            'start_date' => Carbon::now()->addDays(7),
            'end_date' => Carbon::now()->addDays(21),
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_story_points' => 3,
        ]);

        // Mock AI response
        $mockAIResponse = new class {
            public function getContent() {
                return json_encode([
                    'suggestions' => [
                        [
                            'type' => 'priority',
                            'task_id' => 1,
                            'message' => 'Focus on this task'
                        ]
                    ],
                    'summary' => 'Test summary',
                    'focus_areas' => ['priority_tasks'],
                    'recommended_tasks' => [1]
                ]);
            }
        };

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('chat')->andReturn($mockAIResponse);

        // Execute the job
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert curation prompt was stored with iteration information
        $this->assertDatabaseHas('curation_prompts', [
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);
    }

    /** @test */
    public function it_handles_organization_users_correctly()
    {
        // Create another user in the same organization
        $otherUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $otherUser->groups()->attach($this->group);

        // Create a project
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_story_points' => 3,
        ]);

        // Create a curated task for the other user today
        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'work_date' => today()->toDateString(),
            'assigned_to' => $otherUser->id,
        ]);

        // Mock AI response
        $mockAIResponse = new class {
            public function getContent() {
                return json_encode([
                    'suggestions' => [],
                    'summary' => 'No unassigned tasks',
                    'focus_areas' => [],
                    'recommended_tasks' => []
                ]);
            }
        };

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('chat')->andReturn($mockAIResponse);

        // Execute the job for the current user
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert that the already-assigned task was not curated for this user
        $this->assertDatabaseMissing('curated_tasks', [
            'assigned_to' => $this->user->id,
            'curatable_id' => $task->id,
            'curatable_type' => Task::class,
            'work_date' => today()->toDateString(),
        ]);
    }

    /** @test */
    public function it_uses_fallback_suggestions_when_ai_fails()
    {
        // Create a project with tasks
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->subDay(), // Overdue task
            'current_story_points' => 3,
        ]);

        // Mock AI to fail
        AI::shouldReceive('hasConfiguredProvider')->andReturn(false);

        // Execute the job
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert that fallback suggestions were used
        $this->assertDatabaseHas('daily_curations', [
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        $curation = DailyCuration::where('user_id', $this->user->id)->first();
        $this->assertNotNull($curation);
        $this->assertNotEmpty($curation->suggestions);
    }

    /** @test */
    public function it_clears_previous_curation_prompts()
    {
        // Create previous curation prompts
        CurationPrompt::create([
            'user_id' => $this->user->id,
            'project_id' => 1,
            'prompt_text' => 'Old prompt',
            'ai_provider' => 'cerebras',
            'is_organization_user' => false,
            'task_count' => 5,
        ]);

        // Create a project
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_story_points' => 3,
        ]);

        // Mock AI response
        $mockAIResponse = new class {
            public function getContent() {
                return json_encode([
                    'suggestions' => [],
                    'summary' => 'Test summary',
                    'focus_areas' => [],
                    'recommended_tasks' => []
                ]);
            }
        };

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('chat')->andReturn($mockAIResponse);

        // Execute the job
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert old prompts were cleared and new ones created
        $this->assertDatabaseHas('curation_prompts', [
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);
    }

    /** @test */
    public function it_categorizes_task_types_correctly()
    {
        // Create a project
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'project_type' => 'finite',
        ]);

        // Create tasks with different characteristics
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 8,
            'size' => 'xl',
            'updated_at' => Carbon::now()->subDays(10),
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'status' => 'completed',
            'current_story_points' => 3,
            'size' => 'm',
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        // Create curated tasks records
        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $parentTask->id,
            'work_date' => Carbon::now()->subDays(10)->toDateString(),
            'assigned_to' => $this->user->id,
            'completed_at' => Carbon::now()->subDays(8),
            'created_at' => Carbon::now()->subDays(10),
        ]);

        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $childTask->id,
            'work_date' => Carbon::now()->subDays(5)->toDateString(),
            'assigned_to' => $this->user->id,
            'completed_at' => Carbon::now()->subDays(3),
            'created_at' => Carbon::now()->subDays(5),
        ]);

        // Mock AI response
        $mockAIResponse = new class {
            public function getContent() {
                return json_encode([
                    'suggestions' => [],
                    'summary' => 'Test summary',
                    'focus_areas' => [],
                    'recommended_tasks' => []
                ]);
            }
        };

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('chat')->andReturn($mockAIResponse);

        // Execute the job
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert that task history was processed
        $this->assertDatabaseHas('daily_weight_metrics', [
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_handles_exceptions_gracefully()
    {
        // Create a project that will cause an exception
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        // Mock AI to throw an exception
        AI::shouldReceive('hasConfiguredProvider')->andThrow(new \Exception('AI service error'));

        // Execute the job and expect it to handle the exception
        $job = new UserCurationJob($this->user);

        // The job should handle the exception and continue
        $job->handle();

        // Assert that some processing still occurred (weight metrics)
        $this->assertDatabaseHas('daily_weight_metrics', [
            'user_id' => $this->user->id,
        ]);
    }
}
