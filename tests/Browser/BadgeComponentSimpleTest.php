<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BadgeComponentSimpleTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    /**
     * Test that the badge test page loads correctly.
     */
    public function test_badge_test_page_loads()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertSee('Badge Component Test Page')
                ->assertSee('Default Badge')
                ->assertSee('Secondary Badge')
                ->assertSee('Destructive Badge')
                ->assertSee('Outline Badge');
        });
    }

    /**
     * Test default badge rendering and content.
     */
    public function test_default_badge_renders_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertSee('Test Badge')
                ->assertPresent('[data-testid="default-badge"]')
                ->assertSeeIn('[data-testid="default-badge"]', 'Test Badge');
        });
    }

    /**
     * Test secondary badge variant.
     */
    public function test_secondary_badge_renders_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="secondary-badge"]')
                ->assertSeeIn('[data-testid="secondary-badge"]', 'Secondary Badge');
        });
    }

    /**
     * Test destructive badge variant.
     */
    public function test_destructive_badge_renders_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="destructive-badge"]')
                ->assertSeeIn('[data-testid="destructive-badge"]', 'Destructive Badge');
        });
    }

    /**
     * Test outline badge variant.
     */
    public function test_outline_badge_renders_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="outline-badge"]')
                ->assertSeeIn('[data-testid="outline-badge"]', 'Outline Badge');
        });
    }

    /**
     * Test custom class prop.
     */
    public function test_custom_class_prop()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="custom-class-badge"]')
                ->assertSeeIn('[data-testid="custom-class-badge"]', 'Custom Badge');
        });
    }

    /**
     * Test merged classes with variant.
     */
    public function test_merged_classes_with_variant()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="merged-classes-badge"]')
                ->assertSeeIn('[data-testid="merged-classes-badge"]', 'Merged Badge');
        });
    }

    /**
     * Test HTML content in slot.
     */
    public function test_html_content_in_slot()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="html-content-badge"]')
                ->assertSeeIn('[data-testid="html-content-badge"]', 'HTML Content');
        });
    }

    /**
     * Test focus badge.
     */
    public function test_focus_badge()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="focus-badge"]')
                ->assertSeeIn('[data-testid="focus-badge"]', 'Focus Badge');
        });
    }

    /**
     * Test all supported variants.
     */
    public function test_all_supported_variants()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html');

            $variants = ['default', 'secondary', 'destructive', 'outline'];

            foreach ($variants as $variant) {
                $browser->assertPresent("[data-testid=\"variant-{$variant}-badge\"]")
                    ->assertSeeIn("[data-testid=\"variant-{$variant}-badge\"]", "{$variant} badge");
            }
        });
    }

    /**
     * Test custom attributes.
     */
    public function test_custom_attributes()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="attributed-badge"]')
                ->assertSeeIn('[data-testid="attributed-badge"]', 'Attributed Badge')
                ->assertAttribute('[data-testid="attributed-badge"]', 'aria-label', 'Test badge')
                ->assertAttribute('[data-testid="attributed-badge"]', 'data-custom', 'test-value');
        });
    }

    /**
     * Test empty slot content.
     */
    public function test_empty_slot_content()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="default-badge-empty"]');
        });
    }

    /**
     * Test badge component structure.
     */
    public function test_badge_component_structure()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="default-badge"]')
                ->assertSeeIn('[data-testid="default-badge"]', 'Test Badge');
        });
    }

    /**
     * Test that badges have the correct HTML structure.
     */
    public function test_badge_html_structure()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertPresent('[data-testid="default-badge"]')
                ->assertPresent('[data-testid="secondary-badge"]')
                ->assertPresent('[data-testid="destructive-badge"]')
                ->assertPresent('[data-testid="outline-badge"]');
        });
    }

    /**
     * Test that badges are visible on the page.
     */
    public function test_badges_are_visible()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->assertVisible('[data-testid="default-badge"]')
                ->assertVisible('[data-testid="secondary-badge"]')
                ->assertVisible('[data-testid="destructive-badge"]')
                ->assertVisible('[data-testid="outline-badge"]');
        });
    }

    /**
     * Test that badges can be interacted with (clickable).
     */
    public function test_badges_are_interactive()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-badge.html')
                ->click('[data-testid="default-badge"]')
                ->click('[data-testid="secondary-badge"]')
                ->click('[data-testid="destructive-badge"]')
                ->click('[data-testid="outline-badge"]');
        });
    }
}
