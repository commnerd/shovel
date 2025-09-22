<?php

namespace Tests\Browser;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FormCacheBustingTest extends DuskTestCase
{
    use DatabaseMigrations;

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
    public function it_verifies_cache_busting_headers_in_form_responses()
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
    public function it_verifies_cache_busting_in_task_creation_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/create")
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->type('input[name="title"]', 'Cache Bust Test Task')
                ->type('textarea[name="description"]', 'Testing cache busting in task creation')
                ->press('Create Task')
                ->waitForLocation("/dashboard/projects/{$this->project->id}/tasks")
                ->assertSee('Cache Bust Test Task');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_project_creation_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/projects/create')
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->type('input[name="title"]', 'Cache Bust Test Project')
                ->type('textarea[name="description"]', 'Testing cache busting in project creation')
                ->press('Create Project')
                ->waitForLocation('/dashboard/projects')
                ->assertSee('Cache Bust Test Project');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_waitlist_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->type('input[type="email"]', 'cachebust@example.com')
                ->press('Join waitlist')
                ->waitForText('Thanks! We\'ll be in touch soon.')
                ->assertSee('Thanks! We\'ll be in touch soon.');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_profile_update_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/settings/profile')
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->clear('input[name="name"]')
                ->type('input[name="name"]', 'Cache Bust User')
                ->press('Save')
                ->waitForText('Profile updated successfully')
                ->assertSee('Cache Bust User');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_password_change_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/settings/password')
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->type('input[name="current_password"]', 'password')
                ->type('input[name="password"]', 'newpassword123')
                ->type('input[name="password_confirmation"]', 'newpassword123')
                ->press('Update Password')
                ->waitForText('Password updated successfully')
                ->assertSee('Password updated successfully');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_ai_settings_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/settings/system')
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->select('select[name="provider"]', 'cerebras')
                ->type('input[name="cerebras_api_key"]', 'test-cache-bust-key')
                ->press('Save Settings')
                ->waitForText('Settings saved successfully')
                ->assertSee('Settings saved successfully');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_task_edit_form()
    {
        $task = Task::factory()->create([
            'title' => 'Original Task Title',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/{$task->id}/edit")
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->clear('input[name="title"]')
                ->type('input[name="title"]', 'Updated Cache Bust Task')
                ->press('Update Task')
                ->waitForLocation("/dashboard/projects/{$this->project->id}/tasks")
                ->assertSee('Updated Cache Bust Task');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_project_edit_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/edit")
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->clear('input[name="title"]')
                ->type('input[name="title"]', 'Updated Cache Bust Project')
                ->press('Update Project')
                ->waitForLocation('/dashboard/projects')
                ->assertSee('Updated Cache Bust Project');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_user_invitation_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/admin/invitations/create')
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->type('input[name="email"]', 'invite-cachebust@example.com')
                ->press('Send Invitation')
                ->waitForText('Invitation sent successfully')
                ->assertSee('Invitation sent successfully');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_organization_creation_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/auth/create-organization')
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->type('input[name="organization_name"]', 'Cache Bust Organization')
                ->type('textarea[name="organization_address"]', '123 Cache Bust St')
                ->press('Create Organization')
                ->waitForText('Organization created successfully')
                ->assertSee('Cache Bust Organization');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_ai_task_generation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/create")
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->type('input[name="title"]', 'AI Cache Bust Tasks')
                ->type('textarea[name="description"]', 'Generate tasks with cache busting')
                ->press('Generate Tasks with AI')
                ->waitForText('Tasks generated successfully')
                ->assertSee('Tasks generated successfully');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_task_breakdown()
    {
        $task = Task::factory()->create([
            'title' => 'Breakdown Cache Bust Task',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks")
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->click("button[data-task-id='{$task->id}']")
                ->waitForText('Breakdown Cache Bust Task')
                ->type('input[name="title"]', 'Breakdown Cache Bust Task')
                ->type('textarea[name="description"]', 'Break this task down with cache busting')
                ->press('Break Down with AI')
                ->waitForText('Task broken down successfully')
                ->assertSee('Task broken down successfully');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_todays_tasks_generation()
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
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->press('Generate Today\'s Tasks')
                ->waitForText('Tasks generated successfully')
                ->assertSee('Today\'s Tasks');
        });
    }

    /** @test */
    public function it_verifies_cache_busting_in_task_size_update()
    {
        $task = Task::factory()->create([
            'title' => 'Size Cache Bust Task',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) use ($task) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks")
                ->assertHeader('X-Cache-Bust-Timestamp')
                ->assertHeader('X-Cache-Bust-Version')
                ->click("button[data-task-id='{$task->id}']")
                ->waitForText('Size Cache Bust Task')
                ->select('select[name="size"]', 'l')
                ->press('Update Size')
                ->waitForText('Task updated successfully')
                ->assertSee('Large');
        });
    }

    /** @test */
    public function it_verifies_etag_header_presence()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertHeader('ETag');
        });
    }

    /** @test */
    public function it_verifies_last_modified_header_presence()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertHeader('Last-Modified');
        });
    }

    /** @test */
    public function it_verifies_api_cache_busting_headers()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertHeader('X-API-Cache-Bust')
                ->assertHeader('Cache-Control', 'no-cache, must-revalidate, max-age=0');
        });
    }
}
