<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class OrganizationEmailWorkflowTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    /**
     * Test organization email checked with unique domain shows organization form.
     */
    public function test_organization_email_checked_with_unique_domain_shows_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertSee('Create an account')
                ->type('name', 'Test Founder')
                ->type('email', 'founder@uniquecompany123.com')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->check('organization_email') // Check the organization email checkbox
                ->press('Create account')
                ->waitForLocation('/organization/create', 10)
                ->assertPathIs('/organization/create')
                ->assertSee('Create Your Organization')
                ->assertSee('founder@uniquecompany123.com') // Email should be displayed
                ->type('organization_name', 'Unique Company Inc')
                ->type('organization_address', '123 Business Street, City, State 12345')
                ->press('Create Organization')
                ->waitForLocation('/dashboard', 10)
                ->assertPathIs('/dashboard')
                ->assertSee('Dashboard');
        });
    }

    /**
     * Test organization email checked with existing domain shows pending notification.
     */
    public function test_organization_email_checked_with_existing_domain_shows_pending()
    {
        // First create an organization
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->type('name', 'Company Creator')
                ->type('email', 'creator@testcompany.com')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->check('organization_email')
                ->press('Create account')
                ->waitForLocation('/organization/create', 10)
                ->type('organization_name', 'Test Company')
                ->type('organization_address', '456 Corporate Blvd')
                ->press('Create Organization')
                ->waitForLocation('/dashboard', 10)
                ->visit('/logout')
                ->press('Log out');
        });

        // Now test a new user joining the existing organization
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertSee('Create an account')
                ->type('name', 'New Employee')
                ->type('email', 'employee@testcompany.com')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->check('organization_email') // Check the organization email checkbox
                ->press('Create account')
                ->waitForLocation('/dashboard', 10)
                ->assertPathIs('/dashboard')
                ->assertSee('Your registration is pending approval'); // Should see pending message
        });
    }

    /**
     * Test organization email unchecked behavior for comparison.
     */
    public function test_organization_email_unchecked_behavior()
    {
        // First create an organization
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->type('name', 'Org Creator')
                ->type('email', 'creator@comparison.com')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->check('organization_email')
                ->press('Create account')
                ->waitForLocation('/organization/create', 10)
                ->type('organization_name', 'Comparison Company')
                ->type('organization_address', '789 Test Ave')
                ->press('Create Organization')
                ->waitForLocation('/dashboard', 10)
                ->visit('/logout')
                ->press('Log out');
        });

        // Now test user with same domain but organization email UNCHECKED
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->type('name', 'Cautious User')
                ->type('email', 'user@comparison.com')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                    // Do NOT check organization_email
                ->press('Create account')
                ->waitForLocation('/registration/confirm-organization', 10)
                ->assertPathIs('/registration/confirm-organization')
                ->assertSee('Organization Found')
                ->assertSee('Comparison Company'); // Should see confirmation page
        });
    }

    /**
     * Test the checkbox positioning and functionality.
     */
    public function test_organization_email_checkbox_positioning_and_functionality()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertSee('Create an account')
                ->assertSee('Email address')
                ->type('email', 'test@example.com')
                    // Verify checkbox is present and positioned after email field
                ->assertSee('Organization Email')
                ->assertPresent('input[name="organization_email"]')
                    // Test checking and unchecking
                ->check('organization_email')
                ->assertChecked('organization_email')
                ->uncheck('organization_email')
                ->assertNotChecked('organization_email')
                    // Check it again for the actual test
                ->check('organization_email')
                ->assertChecked('organization_email');
        });
    }

    /**
     * Test form validation with organization email checked.
     */
    public function test_form_validation_with_organization_email()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->check('organization_email')
                ->press('Create account')
                    // Should show validation errors
                ->assertSee('The name field is required')
                ->assertSee('The email field is required')
                ->assertSee('The password field is required');
        });
    }
}
