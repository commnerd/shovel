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

describe('ProjectsController Project Type Handling', function () {
    it('stores finite project type correctly', function () {
        $projectData = [
            'title' => 'Controller Test Finite Project',
            'description' => 'Testing controller finite project handling',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ];

        $response = $this->post('/dashboard/projects', $projectData);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Controller Test Finite Project',
            'project_type' => 'finite',
            'user_id' => $this->user->id,
        ]);
    });

    it('stores iterative project type correctly', function () {
        $projectData = [
            'title' => 'Controller Test Iterative Project',
            'description' => 'Testing controller iterative project handling',
            'project_type' => 'iterative',
            'group_id' => $this->group->id,
        ];

        $response = $this->post('/dashboard/projects', $projectData);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Controller Test Iterative Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
        ]);
    });

    it('defaults to iterative when project_type is missing', function () {
        $projectData = [
            'title' => 'Controller Test Default Project',
            'description' => 'Testing controller default project handling',
            'group_id' => $this->group->id,
            // project_type is intentionally omitted
        ];

        $response = $this->post('/dashboard/projects', $projectData);

        $response->assertStatus(302);

        $this->assertDatabaseHas('projects', [
            'title' => 'Controller Test Default Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
        ]);
    });

    it('validates project_type in request', function () {
        $projectData = [
            'title' => 'Controller Test Invalid Project',
            'description' => 'Testing controller invalid project handling',
            'project_type' => 'invalid_type',
            'group_id' => $this->group->id,
        ];

        $response = $this->post('/dashboard/projects', $projectData);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['project_type']);
    });

    it('handles null project_type by defaulting to iterative', function () {
        $projectData = [
            'title' => 'Controller Test Null Project',
            'description' => 'Testing controller null project handling',
            'project_type' => null,
            'group_id' => $this->group->id,
        ];

        $response = $this->post('/dashboard/projects', $projectData);

        $response->assertStatus(302);
        $response->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'title' => 'Controller Test Null Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
        ]);
    });
});

describe('ProjectsController showCreateTasksPage Project Type', function () {
    it('passes finite project_type to CreateTasks page', function () {
        $requestData = [
            'title' => 'CreateTasks Test Finite Project',
            'description' => 'Testing CreateTasks page finite project handling',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ];

        $response = $this->get('/dashboard/projects/create/tasks?' . http_build_query($requestData));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->where('projectData.project_type', 'finite')
                ->where('projectData.title', 'CreateTasks Test Finite Project')
        );
    });

    it('passes iterative project_type to CreateTasks page', function () {
        $requestData = [
            'title' => 'CreateTasks Test Iterative Project',
            'description' => 'Testing CreateTasks page iterative project handling',
            'project_type' => 'iterative',
            'group_id' => $this->group->id,
        ];

        $response = $this->get('/dashboard/projects/create/tasks?' . http_build_query($requestData));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->where('projectData.project_type', 'iterative')
                ->where('projectData.title', 'CreateTasks Test Iterative Project')
        );
    });

    it('defaults to iterative when project_type is missing in CreateTasks', function () {
        $requestData = [
            'title' => 'CreateTasks Test Default Project',
            'description' => 'Testing CreateTasks page default project handling',
            'group_id' => $this->group->id,
            // project_type is intentionally omitted
        ];

        $response = $this->get('/dashboard/projects/create/tasks?' . http_build_query($requestData));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->where('projectData.project_type', 'iterative')
                ->where('projectData.title', 'CreateTasks Test Default Project')
        );
    });

    it('handles null project_type in CreateTasks', function () {
        $requestData = [
            'title' => 'CreateTasks Test Null Project',
            'description' => 'Testing CreateTasks page null project handling',
            'project_type' => null,
            'group_id' => $this->group->id,
        ];

        $response = $this->get('/dashboard/projects/create/tasks?' . http_build_query($requestData));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->where('projectData.project_type', 'iterative')
                ->where('projectData.title', 'CreateTasks Test Null Project')
        );
    });
});

describe('ProjectsController Index Project Type Display', function () {
    it('displays finite and iterative projects in separate sections', function () {
        // Create test projects
        Project::factory()->create([
            'title' => 'Controller Index Finite Project',
            'project_type' => 'finite',
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        Project::factory()->create([
            'title' => 'Controller Index Iterative Project',
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
                ->where('finiteProjects.0.title', 'Controller Index Finite Project')
                ->where('iterativeProjects.0.title', 'Controller Index Iterative Project')
        );
    });

    it('handles projects with no project_type set', function () {
        // Create a project without explicitly setting project_type
        Project::factory()->create([
            'title' => 'Controller Index Default Project',
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
        ]);

        $response = $this->get('/dashboard/projects');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Index')
                ->has('iterativeProjects', 1)
                ->has('finiteProjects', 0)
        );
    });
});

describe('ProjectsController Project Type with AI Integration', function () {
    it('includes project_type in AI prompt generation for finite projects', function () {
        $projectData = [
            'title' => 'AI Finite Project',
            'description' => 'This is a finite project that should use AI',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ];

        $response = $this->post('/dashboard/projects', $projectData);

        $response->assertStatus(302);

        $project = Project::where('title', 'AI Finite Project')->first();
        expect($project)->not->toBeNull();
        expect($project->project_type)->toBe('finite');
    });

    it('includes project_type in AI prompt generation for iterative projects', function () {
        $projectData = [
            'title' => 'AI Iterative Project',
            'description' => 'This is an iterative project that should use AI',
            'project_type' => 'iterative',
            'group_id' => $this->group->id,
            'ai_provider' => 'cerebras',
        ];

        $response = $this->post('/dashboard/projects', $projectData);

        $response->assertStatus(302);

        $project = Project::where('title', 'AI Iterative Project')->first();
        expect($project)->not->toBeNull();
        expect($project->project_type)->toBe('iterative');
    });
});

describe('ProjectsController Project Type Edge Cases', function () {
    it('handles empty string project_type by defaulting to iterative', function () {
        $projectData = [
            'title' => 'Controller Test Empty Project',
            'description' => 'Testing controller empty project handling',
            'project_type' => '',
            'group_id' => $this->group->id,
        ];

        $response = $this->post('/dashboard/projects', $projectData);

        $response->assertStatus(302);
        $response->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'title' => 'Controller Test Empty Project',
            'project_type' => 'iterative',
            'user_id' => $this->user->id,
        ]);
    });

    it('handles case sensitivity in project_type', function () {
        $projectData = [
            'title' => 'Controller Test Case Project',
            'description' => 'Testing controller case sensitivity',
            'project_type' => 'FINITE',
            'group_id' => $this->group->id,
        ];

        $response = $this->post('/dashboard/projects', $projectData);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['project_type']);
    });

    it('preserves project_type through complete creation flow', function () {
        // Step 1: Navigate to CreateTasks page with finite project_type
        $createTasksData = [
            'title' => 'Complete Flow Test Project',
            'description' => 'Testing complete flow with finite project',
            'project_type' => 'finite',
            'group_id' => $this->group->id,
        ];

        $createTasksResponse = $this->get('/dashboard/projects/create/tasks?' . http_build_query($createTasksData));
        $createTasksResponse->assertStatus(200);
        $createTasksResponse->assertInertia(fn ($page) =>
            $page->where('projectData.project_type', 'finite')
        );

        // Step 2: Create the project
        $storeResponse = $this->post('/dashboard/projects', $createTasksData);
        $storeResponse->assertStatus(302);

        // Step 3: Verify project was created with correct type
        $this->assertDatabaseHas('projects', [
            'title' => 'Complete Flow Test Project',
            'project_type' => 'finite',
            'user_id' => $this->user->id,
        ]);
    });
});
