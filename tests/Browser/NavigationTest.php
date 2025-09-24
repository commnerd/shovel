<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class NavigationTest extends DuskTestCase
{
    use DatabaseMigrations, MocksAIServices;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock AI services to prevent real API calls
        $this->mockAIServices();

        // Run migrations to ensure database is set up
        $this->artisan('migrate');

        // Clean up any existing test users
        User::where('email', 'like', 'navigation%')->delete();

        // Create default organization if it doesn't exist
        $defaultOrg = \App\Models\Organization::where('is_default', true)->first();
        if (!$defaultOrg) {
            $defaultOrg = \App\Models\Organization::create([
                'name' => 'None',
                'domain' => null,
                'address' => null,
                'creator_id' => null,
                'is_default' => true,
            ]);

            // Create the default 'Everyone' group
            \App\Models\Group::create([
                'name' => 'Everyone',
                'description' => 'Default group for individual users',
                'organization_id' => $defaultOrg->id,
                'is_default' => true,
            ]);
        }

        // Create a test user for navigation testing
        $this->user = User::factory()->create([
            'name' => 'Navigation Test User',
            'email' => 'navigation@example.com',
            'password' => bcrypt('password'),
            'pending_approval' => false,
            'approved_at' => now(),
            'organization_id' => $defaultOrg->id,
        ]);

        // Assign user to default group
        $defaultGroup = $defaultOrg->defaultGroup();
        $this->user->groups()->attach($defaultGroup->id, ['joined_at' => now()]);
    }

    public function test_landing_page_loads_and_redirects_unauthenticated_users()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->assertSee('Foca')
                    ->assertSee('Seal your focus')
                    ->assertSee('Get early access')
                    ->assertPresent('a[href="#waitlist"]');
        });
    }

    public function test_login_page_loads_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertSee('Email')
                    ->assertSee('Password')
                    ->assertPresent('input[type="email"]')
                    ->assertPresent('input[type="password"]')
                    ->assertPresent('button[type="submit"]');
        });
    }

    public function test_user_can_login_and_access_dashboard()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->type('email', 'navigation@example.com')
                    ->type('password', 'password')
                    ->press('Log in')
                    ->waitForLocation('/dashboard')
                    ->assertPathIs('/dashboard')
                    ->assertSee('Dashboard');
        });
    }

    public function test_authenticated_user_redirects_from_landing_to_dashboard()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/')
                    ->waitForLocation('/dashboard')
                    ->assertPathIs('/dashboard')
                    ->assertSee('Dashboard');
        });
    }

    public function test_dashboard_navigation_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->assertSee('Dashboard')
                    ->assertPresent('nav')
                    ->assertPresent('a[href="/dashboard/projects"]')
                    ->assertPresent('a[href="/dashboard/todays-tasks"]');
        });
    }

    public function test_projects_page_navigation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->clickLink('Projects')
                    ->waitForLocation('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects')
                    ->assertSee('Projects')
                    ->assertPresent('a[href="/dashboard/projects/create"]');
        });
    }

    public function test_create_project_page_navigation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard/projects')
                    ->clickLink('Create Project')
                    ->waitForLocation('/dashboard/projects/create')
                    ->assertPathIs('/dashboard/projects/create')
                    ->assertSee('Create Project')
                    ->assertPresent('form')
                    ->assertPresent('input[name="title"]')
                    ->assertPresent('textarea[name="description"]');
        });
    }

    public function test_todays_tasks_page_navigation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->clickLink('Today\'s Tasks')
                    ->waitForLocation('/dashboard/todays-tasks')
                    ->assertPathIs('/dashboard/todays-tasks')
                    ->assertSee('Today\'s Tasks');
        });
    }

    public function test_settings_page_navigation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->click('button[aria-label="User menu"]')
                    ->waitForText('Settings')
                    ->clickLink('Settings')
                    ->waitForLocation('/settings/system')
                    ->assertPathIs('/settings/system')
                    ->assertSee('System Settings');
        });
    }

    public function test_logout_functionality()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->click('button[aria-label="User menu"]')
                    ->waitForText('Log out')
                    ->clickLink('Log out')
                    ->waitForLocation('/')
                    ->assertPathIs('/')
                    ->assertSee('Laravel');
        });
    }

    public function test_breadcrumb_navigation_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard/projects')
                    ->assertSee('Projects')
                    ->clickLink('Create Project')
                    ->waitForLocation('/dashboard/projects/create')
                    ->assertSee('Create Project')
                    ->assertPresent('nav[aria-label="Breadcrumb"]');
        });
    }

    public function test_back_button_navigation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard/projects')
                    ->clickLink('Create Project')
                    ->waitForLocation('/dashboard/projects/create')
                    ->assertSee('Create Project')
                    ->click('button[aria-label="Back"]')
                    ->waitForLocation('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects');
        });
    }

    public function test_responsive_navigation_mobile()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->resize(375, 667) // iPhone SE size
                    ->visit('/dashboard')
                    ->assertSee('Dashboard')
                    ->click('button[aria-label="Open main menu"]')
                    ->waitForText('Projects')
                    ->assertSee('Projects')
                    ->assertSee('Today\'s Tasks');
        });
    }

    public function test_page_transitions_are_smooth()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->assertSee('Dashboard')
                    ->clickLink('Projects')
                    ->waitForLocation('/dashboard/projects')
                    ->assertSee('Projects')
                    ->clickLink('Today\'s Tasks')
                    ->waitForLocation('/dashboard/todays-tasks')
                    ->assertSee('Today\'s Tasks')
                    ->clickLink('Dashboard')
                    ->waitForLocation('/dashboard')
                    ->assertSee('Dashboard');
        });
    }

    public function test_unauthorized_access_redirects_to_login()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                    ->waitForLocation('/login')
                    ->assertPathIs('/login')
                    ->assertSee('Email')
                    ->assertSee('Password');
        });
    }

    public function test_form_submission_does_not_break_navigation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard/projects/create')
                    ->type('title', 'Test Project')
                    ->type('description', 'This is a test project description')
                    ->press('Create Project')
                    ->waitForLocation('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects')
                    ->assertSee('Test Project');
        });
    }

    public function test_loading_states_during_navigation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->clickLink('Projects')
                    ->waitForText('Projects')
                    ->assertSee('Projects')
                    ->clickLink('Create Project')
                    ->waitForText('Create Project')
                    ->assertSee('Create Project');
        });
    }

    public function test_error_pages_handle_navigation_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard/projects/999999')
                    ->assertSee('Not Found')
                    ->clickLink('Go Home')
                    ->waitForLocation('/dashboard')
                    ->assertPathIs('/dashboard');
        });
    }

    protected function tearDown(): void
    {
        // Clean up the test user
        if (isset($this->user)) {
            $this->user->groups()->detach();
            $this->user->delete();
        }

        // Clean up any remaining test users
        User::where('email', 'like', 'navigation%')->delete();

        parent::tearDown();
    }
}
