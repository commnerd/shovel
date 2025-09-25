<?php

use App\Models\Group;
use App\Models\Iteration;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\OrganizationSeeder::class);
});

test('can create iterative project', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
        'default_iteration_length_weeks' => 2,
        'auto_create_iterations' => true,
    ]);

    expect($project->isIterative())->toBe(true);
    expect($project->isFinite())->toBe(false);
    expect($project->default_iteration_length_weeks)->toBe(2);
    expect($project->auto_create_iterations)->toBe(true);
});

test('can create finite project', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'finite',
    ]);

    expect($project->isIterative())->toBe(false);
    expect($project->isFinite())->toBe(true);
});

test('project defaults to iterative type', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    // Test database default by creating without factory
    $project = new Project([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'title' => 'Test Project',
        'description' => 'Test Description',
        'status' => 'active',
    ]);
    $project->save();

    // Refresh from database to get default values
    $project->refresh();

    expect($project->project_type)->toBe('iterative');
    expect($project->isIterative())->toBe(true);
});

test('can create iterations for iterative project', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
        'default_iteration_length_weeks' => 3,
    ]);

    $iteration = Iteration::factory()->create([
        'project_id' => $project->id,
        'name' => 'Sprint 1',
        'start_date' => today(),
        'end_date' => today()->addWeeks(3),
        'status' => 'planned',
    ]);

    expect($project->iterations)->toHaveCount(1);
    expect($iteration->project_id)->toBe($project->id);
    expect($iteration->name)->toBe('Sprint 1');
});

test('iteration can calculate completion percentage', function () {
    $iteration = Iteration::factory()->create([
        'committed_points' => 50,
        'completed_points' => 30,
    ]);

    expect($iteration->getCompletionPercentage())->toBe(60.0);
});

test('iteration completion percentage is zero when no committed points', function () {
    $iteration = Iteration::factory()->create([
        'committed_points' => 0,
        'completed_points' => 10,
    ]);

    expect($iteration->getCompletionPercentage())->toBe(0.0);
});

test('iteration can check if overdue', function () {
    $overdueIteration = Iteration::factory()->create([
        'end_date' => now()->subDay(),
        'status' => 'active',
    ]);

    $currentIteration = Iteration::factory()->create([
        'end_date' => now()->addDay(),
        'status' => 'active',
    ]);

    $completedIteration = Iteration::factory()->create([
        'end_date' => now()->subDay(),
        'status' => 'completed',
    ]);

    expect($overdueIteration->isOverdue())->toBe(true);
    expect($currentIteration->isOverdue())->toBe(false);
    expect($completedIteration->isOverdue())->toBe(false);
});

test('iteration can check remaining capacity', function () {
    $iteration = Iteration::factory()->create([
        'capacity_points' => 100,
        'committed_points' => 60,
    ]);

    expect($iteration->getRemainingCapacity())->toBe(40);
    expect($iteration->hasCapacityFor(30))->toBe(true);
    expect($iteration->hasCapacityFor(50))->toBe(false);
});

test('iteration without capacity limit always has capacity', function () {
    $iteration = Iteration::factory()->create([
        'capacity_points' => null,
        'committed_points' => 100,
    ]);

    expect($iteration->getRemainingCapacity())->toBe(null);
    expect($iteration->hasCapacityFor(1000))->toBe(true);
});

test('can create next iteration for project', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
        'default_iteration_length_weeks' => 2,
    ]);

    // Create first iteration
    $firstIteration = Iteration::factory()->create([
        'project_id' => $project->id,
        'start_date' => today(),
        'end_date' => today()->addWeeks(2),
        'sort_order' => 1,
    ]);

    // Create next iteration
    $nextIteration = Iteration::createNext($project);

    expect($nextIteration->project_id)->toBe($project->id);
    expect($nextIteration->name)->toBe('Sprint 2');
    expect($nextIteration->sort_order)->toBe(2);
    expect($nextIteration->start_date->format('Y-m-d'))->toBe($firstIteration->end_date->copy()->addDay()->format('Y-m-d'));
    expect($nextIteration->end_date->format('Y-m-d'))->toBe($firstIteration->end_date->copy()->addDay()->addWeeks(2)->format('Y-m-d'));
});

test('project can get current and next iterations', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    $activeIteration = Iteration::factory()->active()->create([
        'project_id' => $project->id,
        'sort_order' => 1,
    ]);

    $plannedIteration = Iteration::factory()->planned()->create([
        'project_id' => $project->id,
        'sort_order' => 2,
    ]);

    expect($project->getCurrentIteration()->id)->toBe($activeIteration->id);
    expect($project->getNextIteration()->id)->toBe($plannedIteration->id);
});

test('project can calculate story points metrics', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    $iteration = Iteration::factory()->create(['project_id' => $project->id]);

    // Create tasks with story points
    $task1 = Task::factory()->create([
        'project_id' => $project->id,
        'iteration_id' => $iteration->id,
        'current_story_points' => 5,
        'status' => 'completed',
        'parent_id' => null,
        'depth' => 1, // Leaf task
    ]);

    $task2 = Task::factory()->create([
        'project_id' => $project->id,
        'iteration_id' => $iteration->id,
        'current_story_points' => 8,
        'status' => 'pending',
        'parent_id' => null,
        'depth' => 1, // Leaf task
    ]);

    expect($project->getTotalStoryPoints())->toBe(13);
    expect($project->getCompletedStoryPoints())->toBe(5);
});

test('project can calculate average velocity', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    // Create completed iterations with different velocities
    Iteration::factory()->completed()->create([
        'project_id' => $project->id,
        'completed_points' => 20,
    ]);

    Iteration::factory()->completed()->create([
        'project_id' => $project->id,
        'completed_points' => 30,
    ]);

    Iteration::factory()->completed()->create([
        'project_id' => $project->id,
        'completed_points' => 25,
    ]);

    expect($project->getAverageVelocity())->toBe(25.0);
});

test('project with no completed iterations has zero velocity', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    expect($project->getAverageVelocity())->toBe(0.0);
});

test('can get backlog tasks', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    $iteration = Iteration::factory()->create(['project_id' => $project->id]);

    // Create tasks - some in iteration, some in backlog
    $backlogTask = Task::factory()->create([
        'project_id' => $project->id,
        'iteration_id' => null, // In backlog
    ]);

    $iterationTask = Task::factory()->create([
        'project_id' => $project->id,
        'iteration_id' => $iteration->id,
    ]);

    $backlogTasks = $project->getBacklogTasks()->get();

    expect($backlogTasks)->toHaveCount(1);
    expect($backlogTasks->first()->id)->toBe($backlogTask->id);
});

test('auto create iterations creates next iteration when needed', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
        'auto_create_iterations' => true,
        'default_iteration_length_weeks' => 2,
    ]);

    // No iterations exist, should create one
    $newIteration = $project->createNextIterationIfNeeded();

    expect($newIteration)->not->toBe(null);
    expect($newIteration->name)->toBe('Sprint 1');
    expect($project->iterations)->toHaveCount(1);
});

test('auto create iterations does not create when active iteration exists', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
        'auto_create_iterations' => true,
    ]);

    // Create active iteration
    Iteration::factory()->active()->create(['project_id' => $project->id]);

    $newIteration = $project->createNextIterationIfNeeded();

    expect($newIteration)->toBe(null);
    expect($project->iterations)->toHaveCount(1);
});

test('finite project does not auto create iterations', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'domain' => 'iterative-test-' . uniqid() . '.com'
    ]);
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'finite',
        'auto_create_iterations' => true, // Should be ignored
    ]);

    $newIteration = $project->createNextIterationIfNeeded();

    expect($newIteration)->toBe(null);
    expect($project->iterations)->toHaveCount(0);
});
