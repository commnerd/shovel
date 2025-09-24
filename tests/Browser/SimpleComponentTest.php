<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SimpleComponentTest extends DuskTestCase
{
    use DatabaseMigrations, MocksAIServices;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock AI services to prevent real API calls
        $this->mockAIServices();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    /**
     * Test that the registration page loads and has basic elements.
     */
    public function test_registration_page_has_basic_elements()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertSee('Create an account')
                ->assertSee('Name')
                ->assertSee('Email address')
                ->assertSee('Password')
                ->assertSee('Confirm password');
        });
    }

    /**
     * Test that we can interact with form elements.
     */
    public function test_can_interact_with_form_elements()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->type('name', 'Test User')
                ->type('email', 'test@example.com')
                ->type('password', 'password123')
                ->type('password_confirmation', 'password123')
                ->assertInputValue('name', 'Test User')
                ->assertInputValue('email', 'test@example.com');
        });
    }

    /**
     * Test that we can check and uncheck checkboxes.
     */
    public function test_can_toggle_checkboxes()
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
     * Test that we can find elements by various selectors.
     */
    public function test_can_find_elements_by_selectors()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertPresent('input[name="name"]')
                ->assertPresent('input[name="email"]')
                ->assertPresent('input[name="password"]')
                ->assertPresent('input[name="password_confirmation"]')
                ->assertPresent('input[name="organization_email"]')
                ->assertPresent('button[type="submit"]');
        });
    }

    /**
     * Test that we can get element attributes.
     */
    public function test_can_get_element_attributes()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertAttribute('input[name="name"]', 'type', 'text')
                ->assertAttribute('input[name="email"]', 'type', 'email')
                ->assertAttribute('input[name="password"]', 'type', 'password')
                ->assertAttribute('input[name="password_confirmation"]', 'type', 'password')
                ->assertAttribute('input[name="organization_email"]', 'type', 'checkbox');
        });
    }

    /**
     * Test that we can get element text content.
     */
    public function test_can_get_element_text_content()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertSeeIn('h1', 'Create an account')
                ->assertSeeIn('label[for="name"]', 'Name')
                ->assertSeeIn('label[for="email"]', 'Email address')
                ->assertSeeIn('label[for="password"]', 'Password')
                ->assertSeeIn('label[for="password_confirmation"]', 'Confirm password');
        });
    }

    /**
     * Test that we can wait for elements to appear.
     */
    public function test_can_wait_for_elements()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->waitFor('input[name="name"]')
                ->waitFor('input[name="email"]')
                ->waitFor('input[name="password"]')
                ->waitFor('input[name="password_confirmation"]')
                ->waitFor('input[name="organization_email"]');
        });
    }

    /**
     * Test that we can take screenshots.
     */
    public function test_can_take_screenshots()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->screenshot('registration-page');
        });
    }

    /**
     * Test that we can resize the browser window.
     */
    public function test_can_resize_browser_window()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->resize(1920, 1080)
                ->assertSee('Create an account')
                ->resize(800, 600)
                ->assertSee('Create an account');
        });
    }

    /**
     * Test that we can scroll the page.
     */
    public function test_can_scroll_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->scrollTo('input[name="organization_email"]')
                ->assertVisible('input[name="organization_email"]')
                ->scrollTo('button[type="submit"]')
                ->assertVisible('button[type="submit"]');
        });
    }
}
