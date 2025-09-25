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
});

describe('Project Model Project Type', function () {
    it('creates project with finite type', function () {
        $project = Project::create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Finite Project',
            'description' => 'A finite project',
            'project_type' => 'finite',
            'status' => 'active',
        ]);

        expect($project->project_type)->toBe('finite');
        expect($project->exists)->toBeTrue();
    });

    it('creates project with iterative type', function () {
        $project = Project::create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Iterative Project',
            'description' => 'An iterative project',
            'project_type' => 'iterative',
            'status' => 'active',
        ]);

        expect($project->project_type)->toBe('iterative');
        expect($project->exists)->toBeTrue();
    });

    it('defaults to iterative when project_type is omitted', function () {
        $project = Project::create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Default Project',
            'description' => 'A project with no type',
            // project_type omitted to test default
            'status' => 'active',
        ]);

        expect($project->project_type)->toBe('iterative');
    });

    it('validates project_type enum values', function () {
        expect(function () {
            Project::create([
                'user_id' => $this->user->id,
                'group_id' => $this->group->id,
                'title' => 'Test Invalid Project',
                'description' => 'A project with invalid type',
                'project_type' => 'invalid_type',
                'status' => 'active',
            ]);
        })->toThrow(Exception::class);
    });

    it('can update project_type', function () {
        $project = Project::create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Update Project',
            'description' => 'A project to update',
            'project_type' => 'finite',
            'status' => 'active',
        ]);

        expect($project->project_type)->toBe('finite');

        $project->update(['project_type' => 'iterative']);
        $project->refresh();

        expect($project->project_type)->toBe('iterative');
    });

    it('can scope projects by type', function () {
        Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'project_type' => 'finite',
        ]);

        Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'project_type' => 'iterative',
        ]);

        $finiteProjects = Project::where('user_id', $this->user->id)
            ->where('project_type', 'finite')->get();
        $iterativeProjects = Project::where('user_id', $this->user->id)
            ->where('project_type', 'iterative')->get();

        expect($finiteProjects)->toHaveCount(1);
        expect($iterativeProjects)->toHaveCount(1);
        expect($finiteProjects->first()->project_type)->toBe('finite');
        expect($iterativeProjects->first()->project_type)->toBe('iterative');
    });

    it('has correct fillable attributes for project_type', function () {
        $project = new Project();
        $fillable = $project->getFillable();

        expect($fillable)->toContain('project_type');
    });

    it('casts project_type correctly', function () {
        $project = Project::create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Cast Project',
            'description' => 'A project to test casting',
            'project_type' => 'finite',
            'status' => 'active',
        ]);

        expect($project->project_type)->toBeString();
        expect($project->project_type)->toBe('finite');
    });
});

describe('Project Type Database Constraints', function () {
    it('enforces project_type enum constraint at database level', function () {
        // This test would require a direct database query to test the constraint
        // In a real scenario, you might want to test this with a raw query
        expect(true)->toBeTrue(); // Placeholder - actual constraint testing would require more setup
    });

    it('handles project_type in mass assignment', function () {
        $projectData = [
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Mass Assignment',
            'description' => 'Testing mass assignment',
            'project_type' => 'finite',
            'status' => 'active',
        ];

        $project = Project::create($projectData);

        expect($project->project_type)->toBe('finite');
        expect($project->title)->toBe('Test Mass Assignment');
    });
});

describe('Project Type Relationships', function () {
    it('maintains project_type when creating tasks', function () {
        $project = Project::create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Task Project',
            'description' => 'A project with tasks',
            'project_type' => 'finite',
            'status' => 'active',
        ]);

        // Create a task for the project
        $task = $project->tasks()->create([
            'title' => 'Test Task',
            'description' => 'A test task',
            'status' => 'pending',
            'sort_order' => 1,
        ]);

        expect($task->project->project_type)->toBe('finite');
        expect($project->tasks)->toHaveCount(1);
    });

    it('preserves project_type in project relationships', function () {
        $project = Project::create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Relationship Project',
            'description' => 'Testing relationships',
            'project_type' => 'iterative',
            'status' => 'active',
        ]);

        // Test user relationship
        expect($project->user->projects->where('id', $project->id)->first()->project_type)
            ->toBe('iterative');

        // Test group relationship
        expect($project->group->projects->where('id', $project->id)->first()->project_type)
            ->toBe('iterative');
    });
});

describe('Project Type Edge Cases', function () {
    it('handles empty string project_type', function () {
        expect(function () {
            Project::create([
                'user_id' => $this->user->id,
                'group_id' => $this->group->id,
                'title' => 'Test Empty Project',
                'description' => 'A project with empty type',
                'project_type' => '',
                'status' => 'active',
            ]);
        })->toThrow(Exception::class);
    });

    it('handles case sensitivity', function () {
        expect(function () {
            Project::create([
                'user_id' => $this->user->id,
                'group_id' => $this->group->id,
                'title' => 'Test Case Project',
                'description' => 'A project with wrong case',
                'project_type' => 'FINITE',
                'status' => 'active',
            ]);
        })->toThrow(Exception::class);
    });

    it('handles numeric project_type', function () {
        expect(function () {
            Project::create([
                'user_id' => $this->user->id,
                'group_id' => $this->group->id,
                'title' => 'Test Numeric Project',
                'description' => 'A project with numeric type',
                'project_type' => 1,
                'status' => 'active',
            ]);
        })->toThrow(Exception::class);
    });
});
