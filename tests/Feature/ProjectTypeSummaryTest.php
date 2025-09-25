<?php

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'name' => 'Test Organization ' . uniqid(),
        'domain' => 'test-domain-' . uniqid() . '.com'
    ]);

    $this->group = Group::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Test Group',
        'is_default' => true
    ]);

    $this->user = User::factory()->create([
        'organization_id' => $this->organization->id,
        'email' => 'test@test.com'
    ]);

    $this->user->groups()->attach($this->group);

    $this->actingAs($this->user);
});

describe('Project Type Core Functionality', function () {
    it('can create finite projects directly via API', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Direct Finite Project',
            'description' => 'Testing direct finite project creation',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Direct Finite Project',
            'project_type' => 'finite',
            'user_id' => $this->user->id,
        ]);
    });

    it('can create iterative projects directly via API', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Direct Iterative Project',
            'description' => 'Testing direct iterative project creation',
            'project_type' => 'iterative',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Direct Iterative Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
        ]);
    });

    it('defaults to iterative when project_type is not provided', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Default Project',
            'description' => 'Testing default project creation',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Default Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
        ]);
    });

    it('rejects invalid project types', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Invalid Project',
            'description' => 'Testing invalid project type',
            'project_type' => 'invalid_type',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['project_type']);
    });

    it('displays projects in correct sections on index page', function () {
        // Create test projects
        Project::factory()->create([
            'title' => 'Test Finite Project',
            'project_type' => 'finite',
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        Project::factory()->create([
            'title' => 'Test Iterative Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->get('/dashboard/projects');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Index')
                ->has('finiteProjects', 1)
                ->has('iterativeProjects', 1)
                ->where('finiteProjects.0.title', 'Test Finite Project')
                ->where('iterativeProjects.0.title', 'Test Iterative Project')
        );
    });
});

describe('Project Type Bug Documentation', function () {
    it('documents the current bug: CreateTasks page defaults to iterative', function () {
        // This test documents the current bug where the CreateTasks page
        // doesn't properly receive the project_type from the form

        $response = $this->get('/dashboard/projects/create/tasks?' . http_build_query([
            'title' => 'Bug Test Project',
            'description' => 'Testing the project type bug',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ]));

        $response->assertStatus(200);

        // This assertion will fail until the bug is fixed
        // The CreateTasks page should receive 'finite' but currently defaults to 'iterative'
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->where('projectData.project_type', 'finite')
        );
    });

    it('documents that the backend correctly handles project_type when provided', function () {
        // This test confirms that the backend works correctly when project_type is provided

        $response = $this->post('/dashboard/projects', [
            'title' => 'Backend Test Project',
            'description' => 'Testing backend project type handling',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Backend Test Project',
            'project_type' => 'finite',
            'user_id' => $this->user->id,
        ]);
    });
});
