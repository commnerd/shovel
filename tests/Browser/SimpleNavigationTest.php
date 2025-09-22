<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SimpleNavigationTest extends DuskTestCase
{
    public function test_landing_page_loads()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->assertSee('Laravel')
                    ->assertSee('An unexamined life is not worth living.')
                    ->assertSee('Socrates');
        });
    }

    public function test_login_page_loads()
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

    public function test_dashboard_redirects_to_login_when_unauthenticated()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                    ->waitForLocation('/login')
                    ->assertPathIs('/login');
        });
    }

    public function test_authenticated_user_can_access_dashboard()
    {
        // Create a test user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        
        // Assign to default organization and group
        $defaultOrg = \App\Models\Organization::where('is_default', true)->first();
        $defaultGroup = $defaultOrg->defaultGroup();
        $user->organization_id = $defaultOrg->id;
        $user->save();
        $user->groups()->attach($defaultGroup->id, ['joined_at' => now()]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/dashboard')
                    ->assertSee('Dashboard')
                    ->assertPresent('nav');
        });

        // Clean up
        $user->groups()->detach();
        $user->delete();
    }

    public function test_navigation_links_work()
    {
        // Create a test user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        
        // Assign to default organization and group
        $defaultOrg = \App\Models\Organization::where('is_default', true)->first();
        $defaultGroup = $defaultOrg->defaultGroup();
        $user->organization_id = $defaultOrg->id;
        $user->save();
        $user->groups()->attach($defaultGroup->id, ['joined_at' => now()]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/dashboard')
                    ->assertSee('Dashboard')
                    ->clickLink('Projects')
                    ->waitForLocation('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects')
                    ->assertSee('Projects');
        });

        // Clean up
        $user->groups()->detach();
        $user->delete();
    }
}
