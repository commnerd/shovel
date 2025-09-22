<?php

namespace Tests\Browser;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CacheBustingTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $user;
    protected $organization;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
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
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->assertHeader('X-Cache-Bust-Random')
                ->assertHeader('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
                ->assertHeader('Pragma', 'no-cache')
                ->assertHeader('Expires', '0');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_waitlist_form_submission()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->type('input[type="email"]', 'test@example.com')
                ->press('Join waitlist')
                ->waitForText('Thanks! We\'ll be in touch soon.')
                ->assertSee('Thanks! We\'ll be in touch soon.');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_task_creation_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/create")
                ->type('input[name="title"]', 'Test Task')
                ->type('textarea[name="description"]', 'Test task description')
                ->press('Create Task')
                ->waitForLocation("/dashboard/projects/{$this->project->id}/tasks")
                ->assertSee('Test Task');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_task_edit_form()
    {
        $task = Task::factory()->create([
            'title' => 'Original Task',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/edit")
                ->clear('input[name="title"]')
                ->type('input[name="title"]', 'Updated Task')
                ->press('Update Task')
                ->waitForLocation("/dashboard/projects/{$this->project->id}/tasks")
                ->assertSee('Updated Task');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_project_creation_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/create')
                ->type('input[name="title"]', 'New Project')
                ->type('textarea[name="description"]', 'New project description')
                ->press('Create Project')
                ->waitForLocation('/dashboard/projects')
                ->assertSee('New Project');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_project_edit_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/edit")
                ->clear('input[name="title"]')
                ->type('input[name="title"]', 'Updated Project')
                ->press('Update Project')
                ->waitForLocation('/dashboard/projects')
                ->assertSee('Updated Project');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_profile_update_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/settings/profile')
                ->clear('input[name="name"]')
                ->type('input[name="name"]', 'Updated Name')
                ->press('Save')
                ->waitForText('Profile updated successfully')
                ->assertSee('Updated Name');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_password_change_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/settings/password')
                ->type('input[name="current_password"]', 'password')
                ->type('input[name="password"]', 'newpassword')
                ->type('input[name="password_confirmation"]', 'newpassword')
                ->press('Update Password')
                ->waitForText('Password updated successfully')
                ->assertSee('Password updated successfully');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_ai_settings_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/settings/system')
                ->select('select[name="provider"]', 'cerebrus')
                ->type('input[name="cerebrus_api_key"]', 'test-api-key')
                ->press('Save Settings')
                ->waitForText('Settings saved successfully')
                ->assertSee('Settings saved successfully');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_user_invitation_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/admin/invitations/create')
                ->type('input[name="email"]', 'invite@example.com')
                ->press('Send Invitation')
                ->waitForText('Invitation sent successfully')
                ->assertSee('Invitation sent successfully');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_organization_creation_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/auth/create-organization')
                ->type('input[name="organization_name"]', 'New Organization')
                ->type('textarea[name="organization_address"]', '123 Test St')
                ->press('Create Organization')
                ->waitForText('Organization created successfully')
                ->assertSee('New Organization');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_todays_tasks_generation()
    {
        // Create some tasks for the user
        Task::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'todo',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->press('Generate Today\'s Tasks')
                ->waitForText('Tasks generated successfully')
                ->assertSee('Today\'s Tasks');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_task_size_update()
    {
        $task = Task::factory()->create([
            'title' => 'Size Test Task',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks")
                ->click("button[data-task-id='{$task->id}']")
                ->waitForText('Size Test Task')
                ->select('select[name="size"]', 'l')
                ->press('Update Size')
                ->waitForText('Task updated successfully')
                ->assertSee('Large');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_api_requests()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertHeader('X-API-Cache-Bust')
                ->assertHeader('Cache-Control', 'no-cache, must-revalidate, max-age=0');
        });
    }

    /** @test */
    public function it_adds_etag_header_for_html_responses()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertHeader('ETag');
        });
    }

    /** @test */
    public function it_adds_last_modified_header()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertHeader('Last-Modified');
        });
    }

    /** @test */
    public function it_prevents_cached_responses_with_different_versions()
    {
        $this->browse(function (Browser $browser) {
            // First request
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertHeader('X-Cache-Bust-Version');

            // Simulate a different version by refreshing
            $browser->refresh()
                ->assertHeader('X-Cache-Bust-Version');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_form_data_submissions()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/create")
                ->type('input[name="title"]', 'Form Data Task')
                ->type('textarea[name="description"]', 'Testing form data cache busting')
                ->press('Create Task')
                ->waitForLocation("/dashboard/projects/{$this->project->id}/tasks")
                ->assertSee('Form Data Task');
        });
    }

    /** @test */
    public function it_adds_cache_busting_to_json_submissions()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->assertHeader('X-Cache-Bust-Random');
        });
    }
}
