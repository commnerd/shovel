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

test('top level task can have t-shirt size', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
    ]);

    $topLevelTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    expect($topLevelTask->canHaveSize())->toBe(true);
    expect($topLevelTask->canHaveStoryPoints())->toBe(false);

    $topLevelTask->setSize('m');

    expect($topLevelTask->size)->toBe('m');
    expect($topLevelTask->getSizeDisplayName())->toBe('Medium');
});

test('subtask can have story points', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
    ]);

    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    $subtask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
    ]);

    expect($subtask->canHaveSize())->toBe(false);
    expect($subtask->canHaveStoryPoints())->toBe(true);

    $subtask->setStoryPoints(5);

    expect($subtask->initial_story_points)->toBe(5);
    expect($subtask->current_story_points)->toBe(5);
    expect($subtask->story_points_change_count)->toBe(0);
});

test('story points must be fibonacci numbers', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
    ]);

    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    $subtask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
    ]);

    // Valid fibonacci numbers should work
    $subtask->setStoryPoints(8);
    expect($subtask->current_story_points)->toBe(8);

    // Invalid numbers should throw exception
    expect(fn() => $subtask->setStoryPoints(7))->toThrow(InvalidArgumentException::class);
    expect(fn() => $subtask->setStoryPoints(10))->toThrow(InvalidArgumentException::class);
});

test('t-shirt sizes must be valid', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
    ]);

    $topLevelTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    // Valid sizes should work
    $topLevelTask->setSize('xs');
    expect($topLevelTask->size)->toBe('xs');

    $topLevelTask->setSize('xl');
    expect($topLevelTask->size)->toBe('xl');

    // Invalid sizes should throw exception
    expect(fn() => $topLevelTask->setSize('xxl'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $topLevelTask->setSize('tiny'))->toThrow(InvalidArgumentException::class);
});

test('subtask cannot have t-shirt size', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
    ]);

    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    $subtask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
    ]);

    expect(fn() => $subtask->setSize('m'))->toThrow(InvalidArgumentException::class);
});

test('top level task cannot have story points', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
    ]);

    $topLevelTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    expect(fn() => $topLevelTask->setStoryPoints(5))->toThrow(InvalidArgumentException::class);
});

test('story points change tracking works correctly', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
    ]);

    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    $subtask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
    ]);

    // Initial setting
    $subtask->setStoryPoints(3);
    expect($subtask->initial_story_points)->toBe(3);
    expect($subtask->current_story_points)->toBe(3);
    expect($subtask->story_points_change_count)->toBe(0);
    expect($subtask->hasStoryPointsChanged())->toBe(false);

    // First change
    $subtask->setStoryPoints(5);
    expect($subtask->initial_story_points)->toBe(3); // Unchanged
    expect($subtask->current_story_points)->toBe(5);
    expect($subtask->story_points_change_count)->toBe(1);
    expect($subtask->hasStoryPointsChanged())->toBe(true);

    // Second change
    $subtask->setStoryPoints(8);
    expect($subtask->initial_story_points)->toBe(3); // Still unchanged
    expect($subtask->current_story_points)->toBe(8);
    expect($subtask->story_points_change_count)->toBe(2);

    // Setting to same value doesn't increment count
    $subtask->setStoryPoints(8);
    expect($subtask->story_points_change_count)->toBe(2); // No change
});

test('task can be moved to iteration', function () {
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
        'parent_id' => null,
        'depth' => 0,
    ]);

    $subtask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
        'current_story_points' => 5,
    ]);

    $subtask->moveToIteration($iteration);

    expect($subtask->iteration_id)->toBe($iteration->id);

    // Refresh to load the relationship
    $subtask->refresh();
    expect($subtask->iteration->id)->toBe($iteration->id);
});

test('task can be moved to backlog', function () {
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
        'parent_id' => null,
        'depth' => 0,
    ]);

    $subtask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
        'iteration_id' => $iteration->id,
        'current_story_points' => 5,
    ]);

    $subtask->moveToBacklog();

    expect($subtask->iteration_id)->toBe(null);
});

test('moving task updates iteration points', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'project_type' => 'iterative',
    ]);

    $iteration1 = Iteration::factory()->create([
        'project_id' => $project->id,
        'committed_points' => 10,
    ]);

    $iteration2 = Iteration::factory()->create([
        'project_id' => $project->id,
        'committed_points' => 0,
    ]);

    $parentTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    // Create a leaf task with story points in iteration1
    $leafTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentTask->id,
        'depth' => 1,
        'iteration_id' => $iteration1->id,
        'current_story_points' => 8,
        'status' => 'pending',
    ]);

    // Update iteration1 points to include this task initially
    $iteration1->update(['committed_points' => 8]);

    // Move task from iteration1 to iteration2
    $leafTask->moveToIteration($iteration2);

    // Refresh iterations to get updated points
    $iteration1->refresh();
    $iteration2->refresh();

    expect($iteration1->committed_points)->toBe(0); // 8 - 8 (task moved out)
    expect($iteration2->committed_points)->toBe(8); // 0 + 8 (task moved in)
});

test('task scopes work correctly', function () {
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

    // Create tasks with different attributes
    $sizedTask = Task::factory()->create([
        'project_id' => $project->id,
        'size' => 'l',
        'parent_id' => null,
        'depth' => 0,
    ]);

    $pointedTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $sizedTask->id,
        'depth' => 1,
        'current_story_points' => 5,
        'iteration_id' => $iteration->id,
    ]);

    $anotherParentTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
    ]);

    $backlogTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $anotherParentTask->id,
        'depth' => 1,
        'current_story_points' => 3,
        'iteration_id' => null, // In backlog
    ]);

    // Test scopes
    $tasksWithSize = Task::withSize()->get();
    $tasksWithPoints = Task::withStoryPoints()->get();
    $tasksInIteration = Task::inIteration($iteration->id)->get();
    $backlogTasks = Task::inBacklog()->get();

    expect($tasksWithSize)->toHaveCount(1);
    expect($tasksWithSize->first()->id)->toBe($sizedTask->id);

    expect($tasksWithPoints)->toHaveCount(2);
    expect($tasksWithPoints->pluck('id'))->toContain($pointedTask->id, $backlogTask->id);

    expect($tasksInIteration)->toHaveCount(1);
    expect($tasksInIteration->first()->id)->toBe($pointedTask->id);

    // Backlog includes parent tasks without iterations too, so let's be more specific
    $backlogTasksWithPoints = Task::inBacklog()->withStoryPoints()->get();
    expect($backlogTasksWithPoints)->toHaveCount(1);
    expect($backlogTasksWithPoints->first()->id)->toBe($backlogTask->id);
});

test('all t-shirt sizes are valid', function () {
    $validSizes = ['xs', 's', 'm', 'l', 'xl'];
    $expectedNames = ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large'];

    foreach ($validSizes as $index => $size) {
        expect(array_key_exists($size, Task::SIZES))->toBe(true);
        expect(Task::SIZES[$size])->toBe($expectedNames[$index]);
    }
});

test('all fibonacci points are valid', function () {
    $expectedPoints = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];

    expect(Task::FIBONACCI_POINTS)->toBe($expectedPoints);

    // Test that all expected points are valid
    foreach ($expectedPoints as $points) {
        expect(in_array($points, Task::FIBONACCI_POINTS))->toBe(true);
    }
});
