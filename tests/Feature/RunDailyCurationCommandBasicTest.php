<?php

use App\Console\Commands\RunDailyCurationCommand;
use App\Jobs\ScheduleUserCurationJob;
use App\Jobs\UserCurationJob;
use App\Jobs\AutoCreateIterationJob;
use App\Models\User;
use App\Models\Project;
use App\Models\Organization;
use App\Models\Group;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Queue::fake();
});

it('dispatches schedule user curation job for all users', function () {
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
    expect($output)->toContain('Starting daily curation and iteration management...');
    expect($output)->toContain('Processing daily curation for users...');
    expect($output)->toContain('Dispatched ScheduleUserCurationJob to process all users');
    expect($output)->toContain('Daily curation and iteration management completed successfully!');
});

it('processes specific user when user-id option provided', function () {
    $user = User::factory()->create([
        'pending_approval' => false,
        'approved_at' => now(),
    ]);

    // Execute the command with specific user ID
    Artisan::call('curation:daily', ['--user-id' => $user->id]);

    // Assert that UserCurationJob was dispatched for specific user
    Queue::assertPushed(UserCurationJob::class, function ($job) use ($user) {
        $reflection = new ReflectionClass($job);
        $userProperty = $reflection->getProperty('user');
        $userProperty->setAccessible(true);
        $jobUser = $userProperty->getValue($job);
        return $jobUser->id === $user->id;
    });

    // Assert command output
    $output = Artisan::output();
    expect($output)->toContain("Queued curation for user {$user->id} ({$user->name})");
});

it('processes specific project when project-id option provided', function () {
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
        $reflection = new ReflectionClass($job);
        $projectProperty = $reflection->getProperty('project');
        $projectProperty->setAccessible(true);
        $jobProject = $projectProperty->getValue($job);
        return $jobProject->id === $project->id;
    });

    // Assert command output
    $output = Artisan::output();
    expect($output)->toContain("Queued iteration check for project {$project->id} ({$project->title})");
});

it('shows dry run information when dry-run option provided', function () {
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
    $expectedUserCount = User::whereNotNull('email_verified_at')
        ->where('pending_approval', false)
        ->whereNotNull('approved_at')
        ->count();
    expect($output)->toContain("Would dispatch ScheduleUserCurationJob for {$expectedUserCount} users");
    expect($output)->toContain("Would check project {$project->id} ({$project->title})");
});

it('handles no iterative projects gracefully', function () {
    $user = User::factory()->create([
        'pending_approval' => false,
        'approved_at' => now(),
    ]);

    // Count existing iterative projects with auto-create enabled
    $existingIterativeProjects = \App\Models\Project::where('project_type', 'iterative')
        ->where('auto_create_iterations', true)
        ->count();

    // Execute the command
    Artisan::call('curation:daily');

    // Assert command output
    $output = Artisan::output();

    if ($existingIterativeProjects === 0) {
        expect($output)->toContain('Found 0 iterative projects with auto-create enabled');
    } else {
        expect($output)->toContain("Found {$existingIterativeProjects} iterative projects with auto-create enabled");
    }
});

it('handles user not found error', function () {
    // Execute the command with non-existent user ID
    Artisan::call('curation:daily', ['--user-id' => 99999]);

    // Assert command output
    $output = Artisan::output();
    expect($output)->toContain('User with ID 99999 not found');
});
