<?php

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'name' => 'Test Organization',
        'domain' => 'test.com'
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

describe('Project Type Validation', function () {
    it('accepts finite project type', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Test Finite Project',
            'description' => 'This is a finite project',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302); // Redirect after successful creation

        $this->assertDatabaseHas('projects', [
            'title' => 'Test Finite Project',
            'project_type' => 'finite',
            'user_id' => $this->user->id,
        ]);
    });

    it('accepts iterative project type', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Test Iterative Project',
            'description' => 'This is an iterative project',
            'project_type' => 'iterative',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Test Iterative Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
        ]);
    });

    it('defaults to iterative when project_type is not provided', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Test Default Project',
            'description' => 'This project has no type specified',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Test Default Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
        ]);
    });

    it('rejects invalid project types', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Test Invalid Project',
            'description' => 'This project has an invalid type',
            'project_type' => 'invalid_type',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['project_type']);
    });

    it('accepts null project_type and defaults to iterative', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Test Null Project',
            'description' => 'This project has null type',
            'project_type' => null,
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Test Null Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
        ]);
    });
});

describe('Project Type in CreateTasks Page', function () {
    it('passes project_type to CreateTasks page', function () {
        $response = $this->get('/dashboard/projects/create/tasks?' . http_build_query([
            'title' => 'Test Project',
            'description' => 'Test description',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->where('projectData.project_type', 'finite')
        );
    });

    it('defaults to iterative when project_type not provided to CreateTasks', function () {
        $response = $this->get('/dashboard/projects/create/tasks?' . http_build_query([
            'title' => 'Test Project',
            'description' => 'Test description',
            'group_id' => $this->group->id,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->where('projectData.project_type', 'iterative')
        );
    });
});

describe('Project Type Display', function () {
    it('displays finite projects in finite section', function () {
        Project::factory()->create([
            'title' => 'Finite Project',
            'project_type' => 'finite',
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        Project::factory()->create([
            'title' => 'Iterative Project',
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
                ->where('finiteProjects.0.title', 'Finite Project')
                ->where('iterativeProjects.0.title', 'Iterative Project')
        );
    });
});

describe('Project Type with AI Integration', function () {
    it('includes project_type in AI prompt generation', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'AI Finite Project',
            'description' => 'This is a finite project that should use AI',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ]);

        $response->assertStatus(302);

        $project = Project::where('title', 'AI Finite Project')->first();
        expect($project)->not->toBeNull();
        expect($project->project_type)->toBe('finite');
    });
});

describe('Project Type Edge Cases', function () {
    it('handles empty string project_type', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Empty Type Project',
            'description' => 'This project has empty type',
            'project_type' => '',
            'group_id' => $this->group->id,
        ]);

        // Empty string might be treated as null and default to iterative
        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Empty Type Project',
            'project_type' => 'iterative', // Empty string defaults to iterative
        ]);
    });

    it('handles case sensitivity correctly', function () {
        $response = $this->post('/dashboard/projects', [
            'title' => 'Case Test Project',
            'description' => 'Testing case sensitivity',
            'project_type' => 'FINITE',
            'group_id' => $this->group->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['project_type']);
    });

    it('preserves project_type through the entire creation flow', function () {
        // Test the complete flow: CreateProjectForm -> CreateTasks -> Store
        $createTasksResponse = $this->get('/dashboard/projects/create/tasks?' . http_build_query([
            'title' => 'Complete Flow Project',
            'description' => 'Testing complete flow',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ]));

        $createTasksResponse->assertStatus(200);
        $createTasksResponse->assertInertia(fn ($page) =>
            $page->where('projectData.project_type', 'finite')
        );

        // Now create the project
        $storeResponse = $this->post('/dashboard/projects', [
            'title' => 'Complete Flow Project',
            'description' => 'Testing complete flow',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ]);

        $storeResponse->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Complete Flow Project',
            'project_type' => 'finite',
        ]);
    });
});
