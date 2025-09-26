<?php

namespace Tests\Feature;

use App\Jobs\UserCurationJob;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Iteration;
use App\Models\CuratedTasks;
use App\Models\CurationPrompt;
use App\Services\AI\Facades\AI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class EnhancedCurationPromptTest extends TestCase
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
    public function it_includes_project_context_in_prompt()
    {
        // Create a finite project with due date
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'project_type' => 'finite',
            'due_date' => Carbon::now()->addDays(30),
            'ai_provider' => 'cerebras',
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_story_points' => 3,
            'size' => 'm',
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

        // Verify the prompt includes project context
        $prompt = CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('**Project:** ' . $project->title, $prompt->prompt_text);
        $this->assertStringContainsString('**Project Description:** ' . $project->description, $prompt->prompt_text);
        $this->assertStringContainsString('**Project Type:** finite', $prompt->prompt_text);
        $this->assertStringContainsString('**Project Due Date:** ' . $project->due_date->format('Y-m-d'), $prompt->prompt_text);
    }

    /** @test */
    public function it_includes_iteration_context_for_iterative_projects()
    {
        // Create an iterative project
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'project_type' => 'iterative',
            'ai_provider' => 'cerebras',
        ]);

        // Create a current iteration
        $iteration = Iteration::factory()->create([
            'project_id' => $project->id,
            'name' => 'Sprint 1',
            'start_date' => Carbon::now()->subDays(7),
            'end_date' => Carbon::now()->addDays(7),
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

        // Verify the prompt includes iteration context
        $prompt = CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('**Project Type:** iterative', $prompt->prompt_text);
        $this->assertStringContainsString('**Next Iteration Due Date:** ' . $iteration->end_date->format('Y-m-d'), $prompt->prompt_text);
    }

    /** @test */
    public function it_includes_user_context_in_prompt()
    {
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

        // Verify the prompt includes user context
        $prompt = CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('**User:** ' . $this->user->name, $prompt->prompt_text);
        $this->assertStringContainsString('**Current Date:** ' . Carbon::now()->format('Y-m-d'), $prompt->prompt_text);
    }

    /** @test */
    public function it_distinguishes_organization_vs_individual_users()
    {
        // Test organization user
        $orgUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        $orgUser->groups()->attach($this->group);

        $project = Project::factory()->create([
            'user_id' => $orgUser->id,
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
        $job = new UserCurationJob($orgUser);
        $job->handle();

        // Verify the prompt identifies organization user
        $prompt = CurationPrompt::where('user_id', $orgUser->id)->first();
        $this->assertStringContainsString('**User Type:** Organization member', $prompt->prompt_text);
        $this->assertStringContainsString('(only suggest unassigned leaf tasks)', $prompt->prompt_text);
    }

    /** @test */
    public function it_includes_task_details_in_prompt()
    {
        // Create a project with various task types
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ]);

        // Create parent task
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_story_points' => 8,
            'size' => 'xl',
            'due_date' => Carbon::now()->addDays(5),
        ]);

        // Create child task (leaf task)
        $childTask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'status' => 'in_progress',
            'current_story_points' => 3,
            'size' => 'm',
            'due_date' => Carbon::now()->addDays(2),
        ]);

        // Create unsigned task
        $unsignedTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_story_points' => null,
            'size' => null,
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

        // Verify the prompt includes detailed task information
        $prompt = CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('**Currently Active Leaf Tasks (without subtasks):**', $prompt->prompt_text);

        // Should include child task details
        $this->assertStringContainsString("ID: {$childTask->id} - {$childTask->title} (in_progress)", $prompt->prompt_text);
        $this->assertStringContainsString("Due: " . $childTask->due_date->format('Y-m-d'), $prompt->prompt_text);
        $this->assertStringContainsString("Size: {$childTask->size}", $prompt->prompt_text);
        $this->assertStringContainsString("Points: {$childTask->current_story_points}", $prompt->prompt_text);
        $this->assertStringContainsString("Parent: {$parentTask->title}", $prompt->prompt_text);

        // Should include unsigned task
        $this->assertStringContainsString("ID: {$unsignedTask->id} - {$unsignedTask->title} (pending)", $prompt->prompt_text);
    }

    /** @test */
    public function it_includes_task_completion_history_in_prompt()
    {
        // Create a project
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ]);

        // Create completed tasks
        $completedTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
            'current_story_points' => 5,
            'size' => 'm',
            'created_at' => Carbon::now()->subDays(15),
            'updated_at' => Carbon::now()->subDays(14),
        ]);

        // Create curated task record
        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $completedTask->id,
            'work_date' => Carbon::now()->subDays(15)->toDateString(),
            'assigned_to' => $this->user->id,
            'completed_at' => Carbon::now()->subDays(14),
            'created_at' => Carbon::now()->subDays(15),
        ]);

        // Create pending task
        $pendingTask = Task::factory()->create([
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

        // Verify the prompt includes task history
        $prompt = CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('**User Task Completion History (Last 30 Days):**', $prompt->prompt_text);
        $this->assertStringContainsString('Total tasks completed: 1', $prompt->prompt_text);
        $this->assertStringContainsString('Total story points completed: 5', $prompt->prompt_text);
        $this->assertStringContainsString('Average story points per task: 5', $prompt->prompt_text);
    }

    /** @test */
    public function it_requests_specific_suggestion_types()
    {
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

        // Verify the prompt requests specific suggestion types
        $prompt = CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('Priority leaf tasks to focus on today', $prompt->prompt_text);
        $this->assertStringContainsString('Leaf tasks that might be overdue or at risk', $prompt->prompt_text);
        $this->assertStringContainsString('Recommended task breakdown or optimization', $prompt->prompt_text);
        $this->assertStringContainsString('Overall project progress insights', $prompt->prompt_text);
    }

    /** @test */
    public function it_specifies_json_response_format()
    {
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

        // Verify the prompt specifies JSON format
        $prompt = CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('Respond with JSON in this format:', $prompt->prompt_text);
        $this->assertStringContainsString('"suggestions": [', $prompt->prompt_text);
        $this->assertStringContainsString('"type": "priority"', $prompt->prompt_text);
        $this->assertStringContainsString('"task_id": 123', $prompt->prompt_text);
        $this->assertStringContainsString('"message": "Focus on this task today"', $prompt->prompt_text);
        $this->assertStringContainsString('"summary": "Brief overall assessment"', $prompt->prompt_text);
        $this->assertStringContainsString('"focus_areas": ["area1", "area2"]', $prompt->prompt_text);
    }

    /** @test */
    public function it_handles_empty_task_lists_gracefully()
    {
        // Create a project with no active tasks
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ]);

        // Don't create any tasks

        // Mock AI response
        $mockAIResponse = new class {
            public function getContent() {
                return json_encode([
                    'suggestions' => [],
                    'summary' => 'No active tasks',
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

        // Verify the prompt handles empty task list
        $prompt = CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('No active leaf tasks for this project.', $prompt->prompt_text);
    }

    /** @test */
    public function it_stores_prompt_metadata_correctly()
    {
        // Create a project
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4',
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

        // Verify the prompt metadata is stored correctly
        $prompt = CurationPrompt::where('user_id', $this->user->id)->first();
        $this->assertEquals($this->user->id, $prompt->user_id);
        $this->assertEquals($project->id, $prompt->project_id);
        $this->assertEquals('openai', $prompt->ai_provider);
        $this->assertEquals('gpt-4', $prompt->ai_model);
        $this->assertEquals(true, $prompt->is_organization_user);
        $this->assertEquals(1, $prompt->task_count);
        $this->assertGreaterThan(0, strlen($prompt->prompt_text));
    }
}
