<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacheBustingTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $organization;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'testorg.com',
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->project = Project::factory()->create([
            'title' => 'Test Project',
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_adds_cache_busting_headers_to_responses()
    {
        $response = $this->get('/dashboard');

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
        $response->assertHeader('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
        $response->assertHeader('ETag');
        $response->assertHeader('Last-Modified');
    }

    /** @test */
    public function it_adds_cache_busting_headers_to_waitlist_page()
    {
        $response = $this->get('/');

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
        $response->assertHeader('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    /** @test */
    public function it_adds_cache_busting_headers_to_task_creation_page()
    {
        $this->actingAs($this->user);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/create");

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
        $response->assertHeader('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    /** @test */
    public function it_adds_cache_busting_headers_to_project_creation_page()
    {
        $this->actingAs($this->user);

        $response = $this->get('/dashboard/projects/create');

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
        $response->assertHeader('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    /** @test */
    public function it_adds_cache_busting_headers_to_settings_pages()
    {
        $this->actingAs($this->user);

        $response = $this->get('/settings/profile');

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
        $response->assertHeader('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    /** @test */
    public function it_adds_cache_busting_headers_to_api_requests()
    {
        $this->actingAs($this->user);

        $response = $this->get('/dashboard');

        $response->assertHeader('X-API-Cache-Bust');
        $response->assertHeader('Cache-Control', 'no-cache, must-revalidate, max-age=0');
    }

    /** @test */
    public function it_adds_cache_busting_to_form_submissions()
    {
        $this->actingAs($this->user);

        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'Test Task',
            'description' => 'Test task description',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_waitlist_submission()
    {
        $response = $this->post('/waitlist', [
            'email' => 'test@example.com',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_project_creation()
    {
        $this->actingAs($this->user);

        $response = $this->post('/dashboard/projects', [
            'title' => 'New Project',
            'description' => 'New project description',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_task_creation()
    {
        $this->actingAs($this->user);

        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'New Task',
            'description' => 'New task description',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_task_breakdown()
    {
        $this->actingAs($this->user);

        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Breakdown Task',
            'description' => 'Break this task down',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_ai_task_generation()
    {
        $this->actingAs($this->user);

        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks/generate", [
            'title' => 'AI Generated Tasks',
            'description' => 'Generate tasks for this project',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_todays_tasks_generation()
    {
        $this->actingAs($this->user);

        $response = $this->post('/dashboard/todays-tasks/generate');

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_profile_update()
    {
        $this->actingAs($this->user);

        $response = $this->put('/settings/profile', [
            'name' => 'Updated Name',
            'email' => $this->user->email,
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_password_change()
    {
        $this->actingAs($this->user);

        $response = $this->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_ai_settings_update()
    {
        $this->actingAs($this->user);

        $response = $this->put('/settings/system', [
            'provider' => 'cerebrus',
            'cerebrus_api_key' => 'test-api-key',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_user_invitation()
    {
        $this->actingAs($this->user);

        $response = $this->post('/admin/invitations', [
            'email' => 'invite@example.com',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_organization_creation()
    {
        $response = $this->post('/auth/create-organization', [
            'organization_name' => 'New Organization',
            'organization_address' => '123 Test St',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_adds_cache_busting_to_task_size_update()
    {
        $task = Task::factory()->create([
            'title' => 'Size Test Task',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->put("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/size", [
            'size' => 'l',
        ]);

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
    }

    /** @test */
    public function it_verifies_cache_busting_headers_are_unique()
    {
        $response1 = $this->get('/dashboard');
        $response2 = $this->get('/dashboard');

        $timestamp1 = $response1->headers->get('X-Cache-Bust-Timestamp');
        $timestamp2 = $response2->headers->get('X-Cache-Bust-Timestamp');

        $random1 = $response1->headers->get('X-Cache-Bust-Random');
        $random2 = $response2->headers->get('X-Cache-Bust-Random');

        // Timestamps should be different (or very close)
        $this->assertNotEquals($timestamp1, $timestamp2);

        // Random values should be different
        $this->assertNotEquals($random1, $random2);

        // Version should be the same
        $version1 = $response1->headers->get('X-Cache-Bust-Version');
        $version2 = $response2->headers->get('X-Cache-Bust-Version');
        $this->assertEquals($version1, $version2);
    }

    /** @test */
    public function it_verifies_etag_header_is_present()
    {
        $response = $this->get('/dashboard');

        $etag = $response->headers->get('ETag');
        $this->assertNotNull($etag);
        $this->assertNotEmpty($etag);
        $this->assertStringStartsWith('"', $etag);
        $this->assertStringEndsWith('"', $etag);
    }

    /** @test */
    public function it_verifies_last_modified_header_is_present()
    {
        $response = $this->get('/dashboard');

        $lastModified = $response->headers->get('Last-Modified');
        $this->assertNotNull($lastModified);
        $this->assertNotEmpty($lastModified);

        // Should be a valid GMT date
        $this->assertMatchesRegularExpression('/^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} GMT$/', $lastModified);
    }
}
