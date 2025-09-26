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
use App\Services\AI\Facades\AI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class UserTaskHistoryAnalysisTest extends TestCase
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
    public function it_analyzes_task_completion_patterns_correctly()
    {
        // Create a project
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        // Create completed tasks with different characteristics
        $quickTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 2,
            'size' => 's',
            'created_at' => Carbon::now()->subDays(20),
            'updated_at' => Carbon::now()->subDays(19), // 1 day completion
        ]);

        $mediumTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 5,
            'size' => 'm',
            'created_at' => Carbon::now()->subDays(15),
            'updated_at' => Carbon::now()->subDays(12), // 3 days completion
        ]);

        $largeTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 8,
            'size' => 'l',
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(5), // 5 days completion
        ]);

        // Create curated tasks records for completed tasks
        $this->createCuratedTask($quickTask, 19, 20);
        $this->createCuratedTask($mediumTask, 12, 15);
        $this->createCuratedTask($largeTask, 5, 10);

        // Create a pending task for curation
        $pendingTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_story_points' => 3,
            'size' => 'm',
        ]);

        // Mock AI response that should include task history analysis
        $mockAIResponse = new class {
            public function getContent() {
                return json_encode([
                    'suggestions' => [
                        [
                            'type' => 'priority',
                            'task_id' => 4,
                            'message' => 'Based on your history, this medium task should take about 3 days'
                        ]
                    ],
                    'summary' => 'User completes medium tasks in 3 days on average',
                    'focus_areas' => ['medium_complexity_tasks'],
                    'recommended_tasks' => [4]
                ]);
            }
        };

        AI::shouldReceive('hasConfiguredProvider')->andReturn(true);
        AI::shouldReceive('driver')->andReturnSelf();
        AI::shouldReceive('chat')->andReturn($mockAIResponse);

        // Execute the job
        $job = new UserCurationJob($this->user);
        $job->handle();

        // Assert that the task history was included in the prompt
        $this->assertDatabaseHas('curation_prompts', [
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        // Verify the prompt contains task history information
        $prompt = \App\Models\CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('User Task Completion History', $prompt->prompt_text);
        $this->assertStringContainsString('Total tasks completed: 3', $prompt->prompt_text);
        $this->assertStringContainsString('Total story points completed: 15', $prompt->prompt_text);
    }

    /** @test */
    public function it_calculates_average_completion_time_correctly()
    {
        // Create tasks with known completion times
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        // Task 1: 2 hours (0.08 days)
        $task1 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 1,
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(10)->addHours(2),
        ]);

        // Task 2: 24 hours (1 day)
        $task2 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 2,
            'created_at' => Carbon::now()->subDays(8),
            'updated_at' => Carbon::now()->subDays(7),
        ]);

        // Task 3: 48 hours (2 days)
        $task3 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 3,
            'created_at' => Carbon::now()->subDays(5),
            'updated_at' => Carbon::now()->subDays(3),
        ]);

        // Create curated tasks records
        $this->createCuratedTask($task1, 9.92, 10); // 2 hours = 0.08 days
        $this->createCuratedTask($task2, 7, 8);     // 1 day
        $this->createCuratedTask($task3, 3, 5);     // 2 days

        // Expected average: (2 + 24 + 48) / 3 = 24.67 hours = 1.03 days

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

        // Verify the prompt contains the correct average completion time
        $prompt = \App\Models\CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('Average completion time:', $prompt->prompt_text);

        // The average should be approximately 1.03 hours (rounded to 2 decimal places)
        $this->assertStringContainsString('1.03 hours', $prompt->prompt_text);
    }

    /** @test */
    public function it_identifies_top_task_types_correctly()
    {
        // Create tasks with different sizes and types
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        // Create multiple medium-sized tasks (should be top type)
        $mediumTask1 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 5,
            'size' => 'm',
            'created_at' => Carbon::now()->subDays(20),
            'updated_at' => Carbon::now()->subDays(19),
        ]);

        $mediumTask2 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 5,
            'size' => 'm',
            'created_at' => Carbon::now()->subDays(15),
            'updated_at' => Carbon::now()->subDays(14),
        ]);

        // Create one small task
        $smallTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 2,
            'size' => 's',
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(9),
        ]);

        // Create one large task
        $largeTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 8,
            'size' => 'l',
            'created_at' => Carbon::now()->subDays(5),
            'updated_at' => Carbon::now()->subDays(4),
        ]);

        // Create curated tasks records
        $this->createCuratedTask($mediumTask1, 19, 20);
        $this->createCuratedTask($mediumTask2, 14, 15);
        $this->createCuratedTask($smallTask, 9, 10);
        $this->createCuratedTask($largeTask, 4, 5);

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

        // Verify the prompt contains top task types
        $prompt = \App\Models\CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('Top completed task types:', $prompt->prompt_text);

        // Should mention "Size: m" as the top type (appears twice)
        $this->assertStringContainsString('Size: m', $prompt->prompt_text);
    }

    /** @test */
    public function it_handles_users_with_no_completion_history()
    {
        // Create a project with only pending tasks
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $pendingTask = Task::factory()->create([
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
                            'message' => 'Focus on this task today'
                        ]
                    ],
                    'summary' => 'New user - focus on getting started',
                    'focus_areas' => ['getting_started'],
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

        // Verify the prompt handles no history gracefully
        $prompt = \App\Models\CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('User Task Completion History', $prompt->prompt_text);

        // Should not contain completion statistics since there are none
        $this->assertStringNotContainsString('Total tasks completed: 0', $prompt->prompt_text);
    }

    /** @test */
    public function it_distinguishes_between_parent_and_child_tasks()
    {
        // Create a project with parent-child task relationships
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 8,
            'size' => 'xl',
            'created_at' => Carbon::now()->subDays(15),
            'updated_at' => Carbon::now()->subDays(10),
        ]);

        // Create child tasks
        $childTask1 = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'status' => 'completed',
            'current_story_points' => 3,
            'size' => 'm',
            'created_at' => Carbon::now()->subDays(12),
            'updated_at' => Carbon::now()->subDays(11),
        ]);

        $childTask2 = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'status' => 'completed',
            'current_story_points' => 5,
            'size' => 'l',
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(9),
        ]);

        // Create curated tasks records
        $this->createCuratedTask($parentTask, 10, 15);
        $this->createCuratedTask($childTask1, 11, 12);
        $this->createCuratedTask($childTask2, 9, 10);

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

        // Verify the prompt distinguishes between parent and child tasks
        $prompt = \App\Models\CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('User Task Completion History', $prompt->prompt_text);

        // Should contain information about both parent and child tasks
        $this->assertStringContainsString('(Top-level)', $prompt->prompt_text);
        $this->assertStringContainsString('(Subtask)', $prompt->prompt_text);
    }

    /** @test */
    public function it_calculates_story_points_performance_metrics()
    {
        // Create tasks with different story point values
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $task1 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 2,
            'created_at' => Carbon::now()->subDays(20),
            'updated_at' => Carbon::now()->subDays(19),
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 5,
            'created_at' => Carbon::now()->subDays(15),
            'updated_at' => Carbon::now()->subDays(14),
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 8,
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(9),
        ]);

        // Create curated tasks records
        $this->createCuratedTask($task1, 19, 20);
        $this->createCuratedTask($task2, 14, 15);
        $this->createCuratedTask($task3, 9, 10);

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

        // Verify the prompt contains story points metrics
        $prompt = \App\Models\CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('Total story points completed: 15', $prompt->prompt_text);
        $this->assertStringContainsString('Average story points per task: 5', $prompt->prompt_text);
    }

    /**
     * Helper method to create curated task records
     */
    private function createCuratedTask(Task $task, int $completedDaysAgo, int $createdDaysAgo): void
    {
        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'work_date' => Carbon::now()->subDays($createdDaysAgo)->toDateString(),
            'assigned_to' => $this->user->id,
            'completed_at' => Carbon::now()->subDays($completedDaysAgo),
            'created_at' => Carbon::now()->subDays($createdDaysAgo),
        ]);
    }
}
