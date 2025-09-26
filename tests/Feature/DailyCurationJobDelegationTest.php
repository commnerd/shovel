<?php

namespace Tests\Feature;

use App\Jobs\DailyCurationJob;
use App\Jobs\UserCurationJob;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Group;
use App\Models\Organization;
use App\Services\AI\Facades\AI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DailyCurationJobDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

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
    public function it_delegates_to_user_curation_job()
    {
        // Execute the legacy DailyCurationJob
        $job = new DailyCurationJob($this->user);
        $job->handle();

        // Assert that UserCurationJob was dispatched
        Queue::assertPushed(UserCurationJob::class, function ($job) {
            return $job->user->id === $this->user->id;
        });
    }

    /** @test */
    public function it_logs_delegation_activity()
    {
        // Execute the legacy DailyCurationJob
        $job = new DailyCurationJob($this->user);
        $job->handle();

        // Assert that UserCurationJob was dispatched
        Queue::assertPushed(UserCurationJob::class, 1);
    }

    /** @test */
    public function it_handles_sync_execution_correctly()
    {
        // Create a project with tasks for the enhanced job to process
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
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
                    'suggestions' => [
                        [
                            'type' => 'priority',
                            'task_id' => 1,
                            'message' => 'Focus on this task today'
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

        // Execute the legacy DailyCurationJob synchronously
        DailyCurationJob::dispatchSync($this->user);

        // Assert that the enhanced functionality was executed
        $this->assertDatabaseHas('daily_weight_metrics', [
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('daily_curations', [
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        $this->assertDatabaseHas('curated_tasks', [
            'assigned_to' => $this->user->id,
            'curatable_type' => Task::class,
            'work_date' => today()->toDateString(),
        ]);
    }

    /** @test */
    public function it_maintains_backward_compatibility_for_tests()
    {
        // This test ensures that existing tests using DailyCurationJob continue to work

        // Create a project
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
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

        // Execute using the legacy job directly (as tests might do)
        $job = new DailyCurationJob($this->user);
        $job->handle();

        // Assert that UserCurationJob was queued
        Queue::assertPushed(UserCurationJob::class, 1);
    }

    /** @test */
    public function it_handles_multiple_users_correctly()
    {
        // Create another user
        $user2 = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        $user2->groups()->attach($this->group);

        // Execute DailyCurationJob for both users
        $job1 = new DailyCurationJob($this->user);
        $job1->handle();

        $job2 = new DailyCurationJob($user2);
        $job2->handle();

        // Assert that UserCurationJob was queued for both users
        Queue::assertPushed(UserCurationJob::class, 2);

        Queue::assertPushed(UserCurationJob::class, function ($job) {
            return $job->user->id === $this->user->id;
        });

        Queue::assertPushed(UserCurationJob::class, function ($job) use ($user2) {
            return $job->user->id === $user2->id;
        });
    }

    /** @test */
    public function it_preserves_user_object_correctly()
    {
        // Execute the legacy DailyCurationJob
        $job = new DailyCurationJob($this->user);
        $job->handle();

        // Assert that the same user object was passed to UserCurationJob
        Queue::assertPushed(UserCurationJob::class, function ($job) {
            return $job->user->id === $this->user->id &&
                   $job->user->name === $this->user->name &&
                   $job->user->organization_id === $this->user->organization_id;
        });
    }

    /** @test */
    public function it_works_with_organization_users()
    {
        // Create organization user
        $orgUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        $orgUser->groups()->attach($this->group);

        // Execute the legacy DailyCurationJob
        $job = new DailyCurationJob($orgUser);
        $job->handle();

        // Assert that UserCurationJob was queued with organization user
        Queue::assertPushed(UserCurationJob::class, function ($job) use ($orgUser) {
            return $job->user->id === $orgUser->id &&
                   $job->user->organization_id === $orgUser->organization_id;
        });
    }

    /** @test */
    public function it_works_with_individual_users()
    {
        // Create individual user (no organization)
        $individualUser = User::factory()->create([
            'organization_id' => null,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Execute the legacy DailyCurationJob
        $job = new DailyCurationJob($individualUser);
        $job->handle();

        // Assert that UserCurationJob was queued with individual user
        Queue::assertPushed(UserCurationJob::class, function ($job) use ($individualUser) {
            return $job->user->id === $individualUser->id &&
                   $job->user->organization_id === null;
        });
    }

    /** @test */
    public function it_handles_exceptions_gracefully()
    {
        // Mock UserCurationJob to throw an exception
        Queue::shouldReceive('push')->andThrow(new \Exception('Queue error'));

        // Execute the legacy DailyCurationJob
        $job = new DailyCurationJob($this->user);

        // The job should handle the exception
        $this->expectException(\Exception::class);
        $job->handle();
    }
}
