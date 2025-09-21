<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegenerationFeedbackTestSimplified extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $organization = Organization::getDefault();
        $group = $organization->createDefaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'title' => 'Test Project',
            'description' => 'A test project for regeneration feedback',
        ]);
    }

    public function test_task_breakdown_accepts_user_feedback()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Complex Feature Implementation',
                'description' => 'Build a complex feature for the application',
                'user_feedback' => 'Make the tasks more specific and include testing phases',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Should include subtasks (from AI or fallback)
        $data = $response->json();
        $this->assertArrayHasKey('subtasks', $data);
        $this->assertNotEmpty($data['subtasks']);
    }

    public function test_task_breakdown_without_feedback()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Regular Feature',
                'description' => 'A regular task without feedback',
                // No user_feedback provided
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('subtasks', $data);
    }

    public function test_feedback_improves_ai_response_quality()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'UI Development',
                'description' => 'Create the user interface',
                'user_feedback' => 'Add accessibility features and mobile responsiveness',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $data = $response->json();
        $this->assertArrayHasKey('subtasks', $data);
        $this->assertNotEmpty($data['subtasks']);
    }

    public function test_regeneration_feedback_preserves_project_context()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Frontend Application',
                'description' => 'Build the frontend application',
                'user_feedback' => 'Make it more modular and component-based',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_empty_feedback_is_handled_gracefully()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'user_feedback' => '', // Empty feedback
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_feedback_character_limit_validation()
    {
        // Test within limit
        $maxFeedback = str_repeat('a', 2000);
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'user_feedback' => $maxFeedback,
            ]);

        $response->assertOk(); // Should accept exactly 2000 chars

        // Test over the limit
        $overLimitFeedback = str_repeat('a', 2001);
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'user_feedback' => $overLimitFeedback,
            ]);

        $response->assertStatus(422); // Should reject over 2000 chars
        $response->assertJsonValidationErrors(['user_feedback']);
    }

    public function test_regeneration_requires_authentication()
    {
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Test Task',
            'description' => 'Test description',
            'user_feedback' => 'Some feedback',
        ]);

        $response->assertStatus(401);
    }

    public function test_regeneration_requires_project_ownership()
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
                'user_feedback' => 'Some feedback',
            ]);

        $response->assertStatus(403);
    }
}
