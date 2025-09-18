<?php

use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('redirects guests to login when accessing projects page', function () {
    $response = $this->get('/dashboard/projects');

    $response->assertRedirect('/login');
});

it('allows authenticated users to view projects index', function () {
    $this->actingAs($this->user);

    $response = $this->get('/dashboard/projects');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) =>
        $page->component('Projects/Index')
            ->has('projects')
    );
});

it('displays user projects on index page', function () {
    $this->actingAs($this->user);

    // Create projects for this user
    Project::factory()->count(3)->create(['user_id' => $this->user->id]);

    // Create projects for another user (should not be shown)
    $otherUser = User::factory()->create();
    Project::factory()->count(2)->create(['user_id' => $otherUser->id]);

    $response = $this->get('/dashboard/projects');

    $response->assertInertia(fn (Assert $page) =>
        $page->component('Projects/Index')
            ->has('projects', 3)
            ->where('projects.0.user_id', $this->user->id)
            ->where('projects.1.user_id', $this->user->id)
            ->where('projects.2.user_id', $this->user->id)
    );
});

it('can create a project with description only', function () {
    $this->actingAs($this->user);

    $projectData = [
        'description' => 'Build a new task management application',
    ];

    $response = $this->post('/dashboard/projects', $projectData);

    $response->assertRedirect('/dashboard/projects');
    $response->assertSessionHas('message', 'Project created successfully!');

    $this->assertDatabaseHas('projects', [
        'user_id' => $this->user->id,
        'description' => 'Build a new task management application',
        'due_date' => null,
        'status' => 'active',
    ]);
});

it('can create a project with due date', function () {
    $this->actingAs($this->user);

    $projectData = [
        'description' => 'Build a new task management application',
        'due_date' => '2025-12-31',
    ];

    $response = $this->post('/dashboard/projects', $projectData);

    $response->assertRedirect('/dashboard/projects');
    $response->assertSessionHas('message', 'Project created successfully!');

    $this->assertDatabaseHas('projects', [
        'user_id' => $this->user->id,
        'description' => 'Build a new task management application',
        'status' => 'active',
    ]);

    $project = Project::where('user_id', $this->user->id)->first();
    expect($project->due_date->format('Y-m-d'))->toBe('2025-12-31');
});

it('validates required description', function () {
    $this->actingAs($this->user);

    $response = $this->post('/dashboard/projects', []);

    $response->assertSessionHasErrors('description');
    $this->assertDatabaseCount('projects', 0);
});

it('validates description max length', function () {
    $this->actingAs($this->user);

    $response = $this->post('/dashboard/projects', [
        'description' => str_repeat('a', 1001), // Exceeds 1000 character limit
    ]);

    $response->assertSessionHasErrors('description');
    $this->assertDatabaseCount('projects', 0);
});

it('validates due date format', function () {
    $this->actingAs($this->user);

    $response = $this->post('/dashboard/projects', [
        'description' => 'Valid description',
        'due_date' => 'invalid-date-format',
    ]);

    $response->assertSessionHasErrors('due_date');
    $this->assertDatabaseCount('projects', 0);
});

it('prevents guests from creating projects', function () {
    $projectData = [
        'description' => 'Build a new task management application',
    ];

    $response = $this->post('/dashboard/projects', $projectData);

    $response->assertRedirect('/login');
    $this->assertDatabaseCount('projects', 0);
});

it('returns mock ai tasks when creating project', function () {
    $this->actingAs($this->user);

    $projectData = [
        'description' => 'Build a new task management application',
        'due_date' => '2025-12-31',
    ];

    $response = $this->post('/dashboard/projects', $projectData);

    $response->assertRedirect('/dashboard/projects');

    // Check that the session contains the mock project with tasks
    expect(session('project'))->not->toBeNull();
    expect(session('project'))->toHaveKey('tasks');
    expect(session('project')['tasks'])->toHaveCount(5);

    // Verify task structure
    $tasks = session('project')['tasks'];
    expect($tasks[0])->toHaveKeys(['title', 'description', 'status', 'priority']);
});

it('creates projects with correct default status', function () {
    $this->actingAs($this->user);

    $this->post('/dashboard/projects', [
        'description' => 'Test project',
    ]);

    $this->assertDatabaseHas('projects', [
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);
});

it('associates project with authenticated user', function () {
    $this->actingAs($this->user);

    $this->post('/dashboard/projects', [
        'description' => 'Test project',
    ]);

    $project = Project::first();
    expect($project->user_id)->toBe($this->user->id);
});

it('handles empty due date correctly', function () {
    $this->actingAs($this->user);

    $this->post('/dashboard/projects', [
        'description' => 'Test project',
        'due_date' => '', // Empty string should be converted to null
    ]);

    $this->assertDatabaseHas('projects', [
        'user_id' => $this->user->id,
        'due_date' => null,
    ]);
});

it('allows multiple users to have separate projects', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // User 1 creates a project
    $this->actingAs($user1);
    $this->post('/dashboard/projects', ['description' => 'User 1 project']);

    // User 2 creates a project
    $this->actingAs($user2);
    $this->post('/dashboard/projects', ['description' => 'User 2 project']);

    // Each user should only see their own projects
    $this->actingAs($user1);
    $response = $this->get('/dashboard/projects');
    $response->assertInertia(fn (Assert $page) =>
        $page->has('projects', 1)
            ->where('projects.0.description', 'User 1 project')
    );

    $this->actingAs($user2);
    $response = $this->get('/dashboard/projects');
    $response->assertInertia(fn (Assert $page) =>
        $page->has('projects', 1)
            ->where('projects.0.description', 'User 2 project')
    );
});
