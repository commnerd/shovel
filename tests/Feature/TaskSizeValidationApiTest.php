<?php

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\OrganizationSeeder::class);
});

test('api rejects invalid t-shirt size for top-level task', function () {
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

    // Test invalid sizes
    $invalidSizes = ['xxl', 'tiny', 'huge', 'small', 'medium', 'large', 'extra-large', 'xs', 's', 'm', 'l', 'xl'];

    foreach ($invalidSizes as $invalidSize) {
        if (!in_array($invalidSize, ['xs', 's', 'm', 'l', 'xl'])) {
            expect(fn() => $topLevelTask->setSize($invalidSize))
                ->toThrow(InvalidArgumentException::class, 'Invalid size. Must be one of: xs, s, m, l, xl');
        }
    }
});

test('api rejects t-shirt size for subtask', function () {
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

    // Test that subtasks cannot have T-shirt sizes
    $validSizes = ['xs', 's', 'm', 'l', 'xl'];

    foreach ($validSizes as $validSize) {
        expect(fn() => $subtask->setSize($validSize))
            ->toThrow(InvalidArgumentException::class, 'Only top-level tasks can have a T-shirt size');
    }
});

test('api rejects non-fibonacci story points for subtask', function () {
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

    // Test invalid Fibonacci numbers
    $invalidPoints = [0, 4, 6, 7, 9, 10, 11, 12, 14, 15, 16, 17, 18, 19, 20, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 90, 91, 92, 93, 94, 95, 96, 97, 98, 100];

    foreach ($invalidPoints as $invalidPoint) {
        expect(fn() => $subtask->setStoryPoints($invalidPoint))
            ->toThrow(InvalidArgumentException::class, 'Story points must be a Fibonacci number: 1, 2, 3, 5, 8, 13, 21, 34, 55, 89');
    }
});

test('api rejects story points for top-level task', function () {
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

    // Test that top-level tasks cannot have story points
    $validFibonacciPoints = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];

    foreach ($validFibonacciPoints as $validPoint) {
        expect(fn() => $topLevelTask->setStoryPoints($validPoint))
            ->toThrow(InvalidArgumentException::class, 'Only subtasks can have story points');
    }
});

test('api accepts all valid t-shirt sizes for top-level task', function () {
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

    // Test all valid sizes
    $validSizes = ['xs', 's', 'm', 'l', 'xl'];
    $expectedNames = ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large'];

    foreach ($validSizes as $index => $validSize) {
        $topLevelTask->setSize($validSize);
        expect($topLevelTask->size)->toBe($validSize);
        expect($topLevelTask->getSizeDisplayName())->toBe($expectedNames[$index]);
    }
});

test('api accepts all valid fibonacci story points for subtask', function () {
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

    // Test all valid Fibonacci points
    $validFibonacciPoints = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];

    foreach ($validFibonacciPoints as $validPoint) {
        $subtask = Task::factory()->create([
            'project_id' => $project->id,
            'parent_id' => $parentTask->id,
            'depth' => 1,
        ]);

        $subtask->setStoryPoints($validPoint);
        expect($subtask->current_story_points)->toBe($validPoint);
        expect($subtask->initial_story_points)->toBe($validPoint);
        expect($subtask->story_points_change_count)->toBe(0);
    }
});

test('project creation api validates task sizes correctly', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);
    $user->joinGroup($group);

    // Test valid project creation with proper task sizes
    $validProjectData = [
        'title' => 'Test Project',
        'description' => 'A test project with proper task sizes',
        'group_id' => $group->id,
        'project_type' => 'iterative',
        'default_iteration_length_weeks' => 2,
        'auto_create_iterations' => false,
        'tasks' => [
            [
                'title' => 'Top Level Task 1',
                'description' => 'A top level task',
                'status' => 'pending',
                'sort_order' => 1,
                'size' => 'm',
                'initial_story_points' => null,
                'current_story_points' => null,
                'story_points_change_count' => 0,
            ],
            [
                'title' => 'Top Level Task 2',
                'description' => 'Another top level task',
                'status' => 'pending',
                'sort_order' => 2,
                'size' => 'l',
                'initial_story_points' => null,
                'current_story_points' => null,
                'story_points_change_count' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($user)->post('/dashboard/projects', $validProjectData);
    $response->assertStatus(302); // Redirect on success

    // Verify the project was created with correct task sizes
    $project = Project::where('title', 'Test Project')->first();
    expect($project)->not->toBeNull();

    $tasks = $project->tasks()->whereNull('parent_id')->get();
    expect($tasks)->toHaveCount(2);
    expect($tasks->first()->size)->toBe('m');
    expect($tasks->last()->size)->toBe('l');
});

test('project creation api rejects invalid task sizes', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);
    $user->joinGroup($group);

    // Test invalid project creation with invalid task sizes
    $invalidProjectData = [
        'title' => 'Invalid Project',
        'description' => 'A project with invalid task sizes',
        'group_id' => $group->id,
        'project_type' => 'iterative',
        'default_iteration_length_weeks' => 2,
        'auto_create_iterations' => false,
        'tasks' => [
            [
                'title' => 'Invalid Size Task',
                'description' => 'A task with invalid size',
                'status' => 'pending',
                'sort_order' => 1,
                'size' => 'xxl', // Invalid size
                'initial_story_points' => null,
                'current_story_points' => null,
                'story_points_change_count' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($user)->post('/dashboard/projects', $invalidProjectData, ['Accept' => 'application/json']);
    $response->assertStatus(422); // Validation error
    $response->assertJsonValidationErrors(['tasks.0.size']);
});

test('task update api validates sizes correctly', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);
    $user->joinGroup($group);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
    ]);

    $topLevelTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
        'size' => 's',
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
        'current_story_points' => 5,
    ]);

    // Test valid size update for top-level task
    $response = $this->actingAs($user)->patch("/dashboard/tasks/{$topLevelTask->id}", [
        'size' => 'l',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(200);
    $topLevelTask->refresh();
    expect($topLevelTask->size)->toBe('l');

    // Test valid story points update for subtask
    $response = $this->actingAs($user)->patch("/dashboard/tasks/{$subtask->id}", [
        'current_story_points' => 8,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(200);
    $subtask->refresh();
    expect($subtask->current_story_points)->toBe(8);
});

test('task update api rejects invalid sizes', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);
    $user->joinGroup($group);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'group_id' => $group->id,
    ]);

    $topLevelTask = Task::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'depth' => 0,
        'size' => 's',
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
        'current_story_points' => 5,
    ]);

    // Test invalid size update for top-level task
    $response = $this->actingAs($user)->patch("/dashboard/tasks/{$topLevelTask->id}", [
        'size' => 'xxl', // Invalid size
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['size']);

    // Test invalid story points update for subtask
    $response = $this->actingAs($user)->patch("/dashboard/tasks/{$subtask->id}", [
        'current_story_points' => 7, // Invalid Fibonacci number
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Story points must be a Fibonacci number: 1, 2, 3, 5, 8, 13, 21, 34, 55, 89']);
});

test('constants are properly defined', function () {
    // Test T-shirt size constants
    expect(Task::SIZES)->toBe([
        'xs' => 'Extra Small',
        's' => 'Small',
        'm' => 'Medium',
        'l' => 'Large',
        'xl' => 'Extra Large',
    ]);

    // Test Fibonacci sequence constants
    expect(Task::FIBONACCI_POINTS)->toBe([1, 2, 3, 5, 8, 13, 21, 34, 55, 89]);

    // Test that constants are immutable
    expect(array_keys(Task::SIZES))->toContain('xs', 's', 'm', 'l', 'xl');
    expect(array_values(Task::SIZES))->toContain('Extra Small', 'Small', 'Medium', 'Large', 'Extra Large');

    // Test Fibonacci sequence properties
    $fibonacci = Task::FIBONACCI_POINTS;
    expect($fibonacci)->toHaveCount(10);
    expect($fibonacci[0])->toBe(1);
    expect($fibonacci[1])->toBe(2);
    expect($fibonacci[2])->toBe(3);
    expect($fibonacci[3])->toBe(5);
    expect($fibonacci[4])->toBe(8);
    expect($fibonacci[5])->toBe(13);
    expect($fibonacci[6])->toBe(21);
    expect($fibonacci[7])->toBe(34);
    expect($fibonacci[8])->toBe(55);
    expect($fibonacci[9])->toBe(89);
});

test('size validation methods work correctly', function () {
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

    // Test canHaveSize method
    expect($topLevelTask->canHaveSize())->toBe(true);
    expect($subtask->canHaveSize())->toBe(false);

    // Test canHaveStoryPoints method
    expect($topLevelTask->canHaveStoryPoints())->toBe(false);
    expect($subtask->canHaveStoryPoints())->toBe(true);

    // Test getSizeDisplayName method
    $topLevelTask->setSize('m');
    expect($topLevelTask->getSizeDisplayName())->toBe('Medium');

    $topLevelTask->setSize('xl');
    expect($topLevelTask->getSizeDisplayName())->toBe('Extra Large');

    // Test story points change tracking
    $subtask->setStoryPoints(5);
    expect($subtask->hasStoryPointsChanged())->toBe(false);
    expect($subtask->getStoryPointsChangeCount())->toBe(0);

    $subtask->setStoryPoints(8);
    expect($subtask->hasStoryPointsChanged())->toBe(true);
    expect($subtask->getStoryPointsChangeCount())->toBe(1);
});
