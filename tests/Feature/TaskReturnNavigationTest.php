<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskReturnNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'project_type' => 'iterative',
        ]);
    }

    /**
     * Test that task creation redirects to return URL when provided.
     */
    public function test_task_creation_redirects_to_return_url(): void
    {
        $returnUrl = '/dashboard/projects/' . $this->project->id . '/tasks?filter=board';

        $response = $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Test Task',
                'description' => 'Test Description',
                'status' => 'pending',
                'return_url' => $returnUrl,
            ]);

        $response->assertRedirect($returnUrl);
        $response->assertSessionHas('message', 'Task created successfully!');
    }

    /**
     * Test that task creation redirects to default tasks index when no return URL provided.
     */
    public function test_task_creation_redirects_to_default_when_no_return_url(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Test Task',
                'description' => 'Test Description',
                'status' => 'pending',
            ]);

        $response->assertRedirect(route('projects.tasks.index', $this->project));
        $response->assertSessionHas('message', 'Task created successfully!');
    }

    /**
     * Test that task creation with subtasks redirects to return URL when provided.
     */
    public function test_task_creation_with_subtasks_redirects_to_return_url(): void
    {
        $returnUrl = '/dashboard/projects/' . $this->project->id . '/tasks?filter=top-level';

        $response = $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Parent Task',
                'description' => 'Parent Description',
                'status' => 'pending',
                'return_url' => $returnUrl,
                'subtasks' => [
                    [
                        'title' => 'Subtask 1',
                        'description' => 'Subtask 1 Description',
                        'status' => 'pending',
                        'initial_story_points' => 3,
                        'current_story_points' => 3,
                        'story_points_change_count' => 0,
                    ],
                    [
                        'title' => 'Subtask 2',
                        'description' => 'Subtask 2 Description',
                        'status' => 'pending',
                        'initial_story_points' => 5,
                        'current_story_points' => 5,
                        'story_points_change_count' => 0,
                    ],
                ],
            ]);

        $response->assertRedirect($returnUrl);
        $response->assertSessionHas('message', 'Task created successfully with 2 subtasks!');
    }

    /**
     * Test that task update redirects to return URL when provided.
     */
    public function test_task_update_redirects_to_return_url(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Original Title',
            'status' => 'pending',
        ]);

        $returnUrl = '/dashboard/projects/' . $this->project->id . '/tasks?filter=all';

        $response = $this->actingAs($this->user)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'title' => 'Updated Title',
                'description' => 'Updated Description',
                'status' => 'in_progress',
                'return_url' => $returnUrl,
            ]);

        $response->assertRedirect($returnUrl);
        $response->assertSessionHas('message', 'Task updated successfully!');
    }

    /**
     * Test that task update redirects to default tasks index when no return URL provided.
     */
    public function test_task_update_redirects_to_default_when_no_return_url(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Original Title',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'title' => 'Updated Title',
                'description' => 'Updated Description',
                'status' => 'in_progress',
            ]);

        $response->assertRedirect(route('projects.tasks.index', $this->project));
        $response->assertSessionHas('message', 'Task updated successfully!');
    }

    /**
     * Test that return URL is properly validated and sanitized.
     */
    public function test_return_url_validation(): void
    {
        // Test with external URL (should be ignored for security)
        $externalUrl = 'https://malicious-site.com/steal-data';

        $response = $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Test Task',
                'description' => 'Test Description',
                'status' => 'pending',
                'return_url' => $externalUrl,
            ]);

        // Should redirect to default tasks index, not external URL
        $response->assertRedirect(route('projects.tasks.index', $this->project));
    }

    /**
     * Test that return URL with different project ID is handled safely.
     */
    public function test_return_url_with_different_project_id(): void
    {
        $otherProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->project->group_id,
        ]);

        $returnUrl = '/dashboard/projects/' . $otherProject->id . '/tasks?filter=board';

        $response = $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Test Task',
                'description' => 'Test Description',
                'status' => 'pending',
                'return_url' => $returnUrl,
            ]);

        // Should still redirect to the return URL since user owns both projects
        $response->assertRedirect($returnUrl);
    }

    /**
     * Test that return URL with unauthorized project is handled safely.
     */
    public function test_return_url_with_unauthorized_project(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->project->group_id,
        ]);

        $returnUrl = '/dashboard/projects/' . $otherProject->id . '/tasks?filter=board';

        $response = $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Test Task',
                'description' => 'Test Description',
                'status' => 'pending',
                'return_url' => $returnUrl,
            ]);

        // Should redirect to default tasks index for security
        $response->assertRedirect(route('projects.tasks.index', $this->project));
    }

    /**
     * Test that return URL preserves query parameters correctly.
     */
    public function test_return_url_preserves_query_parameters(): void
    {
        $returnUrl = '/dashboard/projects/' . $this->project->id . '/tasks?filter=board&view=kanban&sort=title';

        $response = $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Test Task',
                'description' => 'Test Description',
                'status' => 'pending',
                'return_url' => $returnUrl,
            ]);

        $response->assertRedirect($returnUrl);
    }

    /**
     * Test that return URL with relative path works correctly.
     */
    public function test_return_url_with_relative_path(): void
    {
        $returnUrl = '?filter=leaf&view=compact';

        $response = $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Test Task',
                'description' => 'Test Description',
                'status' => 'pending',
                'return_url' => $returnUrl,
            ]);

        // Should redirect to the current project's tasks with the query parameters
        $response->assertRedirect(route('projects.tasks.index', $this->project) . $returnUrl);
    }
}

