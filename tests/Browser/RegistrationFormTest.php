<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class RegistrationFormTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    /**
     * Test that the registration form displays correctly.
     */
    public function test_registration_form_displays_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertSee('Create an account')
                ->assertPresent('input[name="name"]')
                ->assertPresent('input[name="email"]')
                ->assertPresent('input[name="password"]')
                ->assertPresent('input[name="password_confirmation"]')
                ->assertPresent('input[name="organization_email"]')
                ->assertSee('Organization Email');
        });
    }

    /**
     * Test organization email checkbox functionality.
     */
    public function test_organization_email_checkbox_functionality()
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
     * Test registration with organization email checked leads to organization form.
     */
    public function test_registration_with_organization_email_shows_org_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->type('name', 'Dusk Test Founder')
                ->type('email', 'duskfounder@dusktest123.com')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->check('organization_email')
                ->press('Create account')
                ->waitForLocation('/organization/create', 30)
                ->assertPathIs('/organization/create')
                ->assertSee('Create Your Organization')
                ->assertSee('duskfounder@dusktest123.com'); // Email should be shown
        });
    }
}
