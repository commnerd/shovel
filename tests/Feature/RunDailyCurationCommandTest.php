<?php

namespace Tests\Feature;

use App\Console\Commands\RunDailyCurationCommand;
use App\Jobs\ScheduleUserCurationJob;
use App\Jobs\UserCurationJob;
use App\Jobs\AutoCreateIterationJob;
use App\Models\User;
use App\Models\Project;
use App\Models\Organization;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunDailyCurationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_dispatches_schedule_user_curation_job_for_all_users()
    {
        // Create approved users
        $user1 = User::factory()->create([
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        $user2 = User::factory()->create([
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Create pending user (should not be processed)
        $pendingUser = User::factory()->create([
            'pending_approval' => true,
            'approved_at' => null,
        ]);

        // Execute the command
        Artisan::call('curation:daily');

        // Assert that ScheduleUserCurationJob was dispatched
        Queue::assertPushed(ScheduleUserCurationJob::class, 1);

        // Assert command output
        $output = Artisan::output();
        $this->assertStringContainsString('Starting daily curation and iteration management...', $output);
        $this->assertStringContainsString('Processing daily curation for users...', $output);
        $this->assertStringContainsString('Dispatched ScheduleUserCurationJob to process all users', $output);
        $this->assertStringContainsString('Daily curation and iteration management completed successfully!', $output);
    }

    /** @test */
    public function it_processes_specific_user_when_user_id_option_provided()
    {
        $user = User::factory()->create([
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Execute the command with specific user ID
        Artisan::call('curation:daily', ['--user-id' => $user->id]);

        // Assert that UserCurationJob was dispatched for specific user
        Queue::assertPushed(UserCurationJob::class, function ($job) use ($user) {
            return $job->user->id === $user->id;
        });

        // Assert command output
        $output = Artisan::output();
        $this->assertStringContainsString("Queued curation for user {$user->id} ({$user->name})", $output);
    }

    /** @test */
    public function it_processes_specific_project_when_project_id_option_provided()
    {
        $organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'test-org-' . uniqid() . '.com'
        ]);

        $group = Group::factory()->create([
            'organization_id' => $organization->id,
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        $user->groups()->attach($group);

        // Create iterative project with auto-create enabled
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'auto_create_iterations' => true,
            'default_iteration_length_weeks' => 2,
            'status' => 'active',
        ]);

        // Execute the command with specific project ID
        Artisan::call('curation:daily', ['--project-id' => $project->id]);

        // Assert that AutoCreateIterationJob was dispatched for specific project
        Queue::assertPushed(AutoCreateIterationJob::class, function ($job) use ($project) {
            return $job->project->id === $project->id;
        });

        // Assert command output
        $output = Artisan::output();
        $this->assertStringContainsString("Queued iteration check for project {$project->id} ({$project->title})", $output);
    }

    /** @test */
    public function it_shows_dry_run_information_when_dry_run_option_provided()
    {
        // Create users and projects
        $user = User::factory()->create([
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        $organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'test-org-' . uniqid() . '.com'
        ]);

        $group = Group::factory()->create([
            'organization_id' => $organization->id,
            'is_default' => true,
        ]);

        $projectUser = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        $projectUser->groups()->attach($group);

        $project = Project::factory()->create([
            'user_id' => $projectUser->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'auto_create_iterations' => true,
            'default_iteration_length_weeks' => 2,
            'status' => 'active',
        ]);

        // Execute the command in dry-run mode
        Artisan::call('curation:daily', ['--dry-run' => true]);

        // Assert no jobs were actually dispatched
        Queue::assertNotPushed(ScheduleUserCurationJob::class);
        Queue::assertNotPushed(UserCurationJob::class);
        Queue::assertNotPushed(AutoCreateIterationJob::class);

        // Assert dry-run output
        $output = Artisan::output();
        $this->assertStringContainsString('Would dispatch ScheduleUserCurationJob for 2 users', $output);
        $this->assertStringContainsString("Would check project {$project->id} ({$project->title})", $output);
    }

    /** @test */
    public function it_processes_auto_iteration_creation_correctly()
    {
        $organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'test-org-' . uniqid() . '.com'
        ]);

        $group = Group::factory()->create([
            'organization_id' => $organization->id,
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        $user->groups()->attach($group);

        // Create iterative project with auto-create enabled
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'auto_create_iterations' => true,
            'default_iteration_length_weeks' => 2,
            'status' => 'active',
        ]);

        // Create non-iterative project (should not be processed)
        $nonIterativeProject = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'finite',
            'auto_create_iterations' => true,
            'default_iteration_length_weeks' => 2,
            'status' => 'active',
        ]);

        // Create inactive project (should not be processed)
        $inactiveProject = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'auto_create_iterations' => true,
            'default_iteration_length_weeks' => 2,
            'status' => 'completed',
        ]);

        // Execute the command
        Artisan::call('curation:daily');

        // Assert that AutoCreateIterationJob was dispatched only for the iterative project
        Queue::assertPushed(AutoCreateIterationJob::class, 1);
        Queue::assertPushed(AutoCreateIterationJob::class, function ($job) use ($project) {
            return $job->project->id === $project->id;
        });

        // Assert command output
        $output = Artisan::output();
        $this->assertStringContainsString('Found 1 iterative projects with auto-create enabled', $output);
        $this->assertStringContainsString("Queued iteration check for project {$project->id} ({$project->title})", $output);
    }

    /** @test */
    public function it_handles_no_iterative_projects_gracefully()
    {
        $user = User::factory()->create([
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Execute the command
        Artisan::call('curation:daily');

        // Assert command output
        $output = Artisan::output();
        $this->assertStringContainsString('Found 0 iterative projects with auto-create enabled', $output);
    }

    /** @test */
    public function it_handles_no_users_gracefully()
    {
        // Don't create any users

        // Execute the command
        Artisan::call('curation:daily');

        // Assert command output
        $output = Artisan::output();
        $this->assertStringContainsString('Would dispatch ScheduleUserCurationJob for 0 users', $output);
    }

    /** @test */
    public function it_handles_user_not_found_error()
    {
        // Execute the command with non-existent user ID
        Artisan::call('curation:daily', ['--user-id' => 99999]);

        // Assert command output
        $output = Artisan::output();
        $this->assertStringContainsString('User with ID 99999 not found', $output);
    }

    /** @test */
    public function it_handles_exceptions_gracefully()
    {
        // Mock ScheduleUserCurationJob to throw an exception
        Queue::shouldReceive('push')->andThrow(new \Exception('Queue service unavailable'));

        // Execute the command
        $exitCode = Artisan::call('curation:daily');

        // Assert command failed with error code
        $this->assertEquals(1, $exitCode);

        // Assert error output
        $output = Artisan::output();
        $this->assertStringContainsString('Daily curation failed:', $output);
        $this->assertStringContainsString('Queue service unavailable', $output);
    }

    /** @test */
    public function it_combines_user_and_project_options_correctly()
    {
        $organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'test-org-' . uniqid() . '.com'
        ]);

        $group = Group::factory()->create([
            'organization_id' => $organization->id,
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        $user->groups()->attach($group);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
            'auto_create_iterations' => true,
            'default_iteration_length_weeks' => 2,
            'status' => 'active',
        ]);

        // Execute the command with both options
        Artisan::call('curation:daily', [
            '--user-id' => $user->id,
            '--project-id' => $project->id,
        ]);

        // Assert that both jobs were dispatched
        Queue::assertPushed(UserCurationJob::class, function ($job) use ($user) {
            return $job->user->id === $user->id;
        });

        Queue::assertPushed(AutoCreateIterationJob::class, function ($job) use ($project) {
            return $job->project->id === $project->id;
        });

        // Assert command output
        $output = Artisan::output();
        $this->assertStringContainsString("Queued curation for user {$user->id} ({$user->name})", $output);
        $this->assertStringContainsString("Queued iteration check for project {$project->id} ({$project->title})", $output);
    }
}
