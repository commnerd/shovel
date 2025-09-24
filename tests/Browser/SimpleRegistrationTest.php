<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SimpleRegistrationTest extends DuskTestCase
{
    use DatabaseMigrations, MocksAIServices;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock AI services to prevent real API calls
        $this->mockAIServices();

        // Set up default organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
    }

    /**
     * Test basic registration page loads.
     */
    public function test_registration_page_loads()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertSee('Create an account')
                ->assertSee('Name')
                ->assertSee('Email address')
                ->assertSee('Password')
                ->assertSee('Confirm password')
                ->assertSee('Organization Email')
                ->assertPresent('input[name="organization_email"]');
        });
    }

    /**
     * Test organization email checkbox functionality.
     */
    public function test_organization_email_checkbox_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertNotChecked('organization_email')
                ->check('organization_email')
                ->assertChecked('organization_email')
                ->uncheck('organization_email')
                ->assertNotChecked('organization_email');
        });
    }

    /**
     * Test form submission with organization email checked (unique domain).
     */
    public function test_organization_email_form_submission()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->type('name', 'Test User')
                ->type('email', 'test@dusktest123.com')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->check('organization_email')
                ->press('Create account')
                ->waitForLocation('/organization/create', 30)
                ->assertPathIs('/organization/create');
        });
    }
}
