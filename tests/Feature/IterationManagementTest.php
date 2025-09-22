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

test('iteration can update points from assigned tasks', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    $iteration = Iteration::factory()->create([
        'project_id' => $project->id,
        'committed_points' => 0,
        'completed_points' => 0,
    ]);

    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    // Create leaf tasks with story points
    $completedTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
        'iteration_id' => $iteration->id,
        'current_story_points' => 5,
        'status' => 'completed',
    ]);

    $pendingTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
        'iteration_id' => $iteration->id,
        'current_story_points' => 8,
        'status' => 'pending',
    ]);

    $inProgressTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
        'iteration_id' => $iteration->id,
        'current_story_points' => 3,
        'status' => 'in_progress',
    ]);

    $iteration->updatePointsFromTasks();

    expect($iteration->committed_points)->toBe(16); // 5 + 8 + 3
    expect($iteration->completed_points)->toBe(5); // Only completed task
});

test('iteration scopes work correctly', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    $activeIteration = Iteration::factory()->active()->create(['project_id' => $project->id]);
    $plannedIteration = Iteration::factory()->planned()->create(['project_id' => $project->id]);
    $completedIteration = Iteration::factory()->completed()->create(['project_id' => $project->id]);

    expect(Iteration::active()->count())->toBe(1);
    expect(Iteration::active()->first()->id)->toBe($activeIteration->id);

    expect(Iteration::planned()->count())->toBe(1);
    expect(Iteration::planned()->first()->id)->toBe($plannedIteration->id);

    expect(Iteration::completed()->count())->toBe(1);
    expect(Iteration::completed()->first()->id)->toBe($completedIteration->id);
});

test('iteration can get velocity', function () {
    $iteration = Iteration::factory()->create([
        'completed_points' => 25,
    ]);

    expect($iteration->getVelocity())->toBe(25);
});

test('iteration can calculate days remaining', function () {
    $futureIteration = Iteration::factory()->create([
        'end_date' => today()->addDays(5),
    ]);

    $pastIteration = Iteration::factory()->create([
        'end_date' => today()->subDays(3),
    ]);

    expect($futureIteration->daysRemaining())->toBe(5);
    expect($pastIteration->daysRemaining())->toBe(-3);
});

test('iteration can check if active', function () {
    $activeIteration = Iteration::factory()->active()->create();
    $plannedIteration = Iteration::factory()->planned()->create();

    expect($activeIteration->isActive())->toBe(true);
    expect($plannedIteration->isActive())->toBe(false);
});

test('iteration generates default name correctly', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    // No iterations exist
    expect(Iteration::generateDefaultName($project))->toBe('Sprint 1');

    // Create one iteration
    Iteration::factory()->create(['project_id' => $project->id]);
    expect(Iteration::generateDefaultName($project))->toBe('Sprint 2');

    // Create another iteration
    Iteration::factory()->create(['project_id' => $project->id]);
    expect(Iteration::generateDefaultName($project))->toBe('Sprint 3');
});

test('create next iteration sets correct dates and sort order', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
        'default_iteration_length_weeks' => 3,
    ]);

    // Create first iteration
    $firstIteration = Iteration::factory()->create([
        'project_id' => $project->id,
        'start_date' => today(),
        'end_date' => today()->addWeeks(3),
        'sort_order' => 1,
    ]);

    // Create next iteration
    $secondIteration = Iteration::createNext($project);

    expect($secondIteration->sort_order)->toBe(2);
    expect($secondIteration->start_date->format('Y-m-d'))->toBe($firstIteration->end_date->copy()->addDay()->format('Y-m-d'));
    expect($secondIteration->end_date->format('Y-m-d'))->toBe($firstIteration->end_date->copy()->addDay()->addWeeks(3)->format('Y-m-d'));
    expect($secondIteration->status)->toBe('planned');
});

test('create next iteration for project with no iterations', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
        'default_iteration_length_weeks' => 2,
    ]);

    $firstIteration = Iteration::createNext($project);

    expect($firstIteration->sort_order)->toBe(1);
    expect($firstIteration->start_date->format('Y-m-d'))->toBe(today()->format('Y-m-d'));
    expect($firstIteration->end_date->format('Y-m-d'))->toBe(today()->addWeeks(2)->format('Y-m-d'));
    expect($firstIteration->name)->toBe('Sprint 1');
});

test('iteration can get leaf tasks only', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    $iteration = Iteration::factory()->create(['project_id' => $project->id]);

    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'iteration_id' => $iteration->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    $leafTask = Task::factory()->create([
        'project_id' => $project->id,
        'iteration_id' => $iteration->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
    ]);

    expect($iteration->tasks)->toHaveCount(2); // All tasks
    expect($iteration->leafTasks)->toHaveCount(1); // Only leaf tasks
    expect($iteration->leafTasks->first()->id)->toBe($leafTask->id);
});

test('iteration goals are stored as json array', function () {
    $goals = ['Complete authentication', 'Implement dashboard', 'Fix bugs'];

    $iteration = Iteration::factory()->create([
        'goals' => $goals,
    ]);

    expect($iteration->goals)->toBe($goals);
    expect(is_array($iteration->goals))->toBe(true);
});

test('iteration can handle null goals', function () {
    $iteration = Iteration::factory()->create([
        'goals' => null,
    ]);

    expect($iteration->goals)->toBe(null);
});

test('iteration capacity calculations handle edge cases', function () {
    // Test with null capacity
    $unlimitedIteration = Iteration::factory()->create([
        'capacity_points' => null,
        'committed_points' => 100,
    ]);

    expect($unlimitedIteration->getRemainingCapacity())->toBe(null);
    expect($unlimitedIteration->hasCapacityFor(1000))->toBe(true);

    // Test with zero capacity
    $zeroCapacityIteration = Iteration::factory()->create([
        'capacity_points' => 0,
        'committed_points' => 0,
    ]);

    expect($zeroCapacityIteration->getRemainingCapacity())->toBe(0);
    expect($zeroCapacityIteration->hasCapacityFor(1))->toBe(false);

    // Test overcommitted iteration
    $overcommittedIteration = Iteration::factory()->create([
        'capacity_points' => 50,
        'committed_points' => 60,
    ]);

    expect($overcommittedIteration->getRemainingCapacity())->toBe(0); // Can't be negative
    expect($overcommittedIteration->hasCapacityFor(1))->toBe(false);
});

test('iteration relationships work correctly', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    $iteration = Iteration::factory()->create(['project_id' => $project->id]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'iteration_id' => $iteration->id,
    ]);

    // Test iteration -> project relationship
    expect($iteration->project->id)->toBe($project->id);

    // Test iteration -> tasks relationship
    expect($iteration->tasks->first()->id)->toBe($task->id);

    // Test project -> iterations relationship
    expect($project->iterations->first()->id)->toBe($iteration->id);

    // Test task -> iteration relationship
    expect($task->iteration->id)->toBe($iteration->id);
});
