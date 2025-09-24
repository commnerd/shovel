<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Organization;
use App\Models\Group;
use App\Services\AggressiveCacheBusterService;
use App\Services\DeploymentVersionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Browser\MocksAIServices;

class CacheBustingBrowserTest extends DuskTestCase
{
    use DatabaseMigrations, MocksAIServices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
    }

    /** @test */
    public function cache_busting_headers_are_present_on_pages()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertHeader('X-Deployment-Version')
                ->assertHeader('X-Deployment-Timestamp')
                ->assertHeader('Cache-Control')
                ->assertHeader('X-Cache-Status');
        });
    }

    /** @test */
    public function cache_busting_headers_disable_caching_for_pages()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertHeader('Cache-Control', 'no-cache, no-store, must-revalidate, private')
                ->assertHeader('Pragma', 'no-cache')
                ->assertHeader('Expires', '0')
                ->assertHeader('X-Cache-Status', 'disabled');
        });
    }

    /** @test */
    public function cache_busting_headers_allow_caching_for_static_assets()
    {
        // Create a test CSS file
        $cssFile = public_path('test-cache.css');
        File::put($cssFile, 'body { color: red; }');

        $this->browse(function (Browser $browser) {
            $browser->visit('/test-cache.css')
                ->assertHeader('Cache-Control')
                ->assertHeader('X-Cache-Status');
        });

        // Clean up
        File::delete($cssFile);
    }

    /** @test */
    public function deployment_marker_file_is_accessible()
    {
        // Generate deployment marker
        $deploymentService = app(DeploymentVersionService::class);
        $deploymentService->createDeploymentMarker();

        $this->browse(function (Browser $browser) {
            $browser->visit('/deployment-marker.txt')
                ->assertSee('version')
                ->assertSee('timestamp')
                ->assertSee('build_number');
        });
    }

    /** @test */
    public function cache_buster_javascript_functions_are_available()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->script('
                    // Test cache buster functions
                    expect(window.cacheBuster).toBeDefined();
                    expect(typeof window.cacheBuster.getCurrentVersion).toBe("function");
                    expect(typeof window.cacheBuster.getCacheBustedUrl).toBe("function");
                    expect(typeof window.cacheBuster.clearAllBrowserCaches).toBe("function");
                    expect(typeof window.cacheBuster.forceReloadWithCacheBusting).toBe("function");
                ');
        });
    }

    /** @test */
    public function cache_buster_url_generation_works()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->script('
                    const testUrl = "https://example.com/style.css";
                    const cacheBustedUrl = window.cacheBuster.getCacheBustedUrl(testUrl);

                    expect(cacheBustedUrl).toContain("v=");
                    expect(cacheBustedUrl).toContain("t=");
                    expect(cacheBustedUrl).toContain(testUrl);
                ');
        });
    }

    /** @test */
    public function cache_buster_update_notification_appears()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->script('
                    // Mock deployment marker response with new version
                    const originalFetch = window.fetch;
                    window.fetch = function(url, options) {
                        if (url.includes("deployment-marker.txt")) {
                            return Promise.resolve({
                                ok: true,
                                json: () => Promise.resolve({
                                    version: "1.0.1-test",
                                    timestamp: new Date().toISOString(),
                                    build_number: 2
                                })
                            });
                        }
                        return originalFetch(url, options);
                    };

                    // Trigger update check
                    window.cacheBuster.checkForUpdates().then(hasUpdate => {
                        expect(hasUpdate).toBe(true);
                    });
                ')
                ->pause(1000) // Wait for notification to appear
                ->assertPresent('.fixed.top-4.right-4'); // Check for notification element
        });
    }

    /** @test */
    public function cache_buster_clears_browser_storage()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->script('
                    // Add some test data to browser storage
                    localStorage.setItem("test_key", "test_value");
                    sessionStorage.setItem("test_session", "test_data");

                    expect(localStorage.getItem("test_key")).toBe("test_value");
                    expect(sessionStorage.getItem("test_session")).toBe("test_data");
                ')
                ->script('
                    // Clear all browser caches
                    return window.cacheBuster.clearAllBrowserCaches();
                ')
                ->script('
                    // Verify storage is cleared
                    expect(localStorage.getItem("test_key")).toBeNull();
                    expect(sessionStorage.getItem("test_session")).toBeNull();
                ');
        });
    }

    /** @test */
    public function cache_buster_asset_versioning_works()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->script('
                    // Test asset versioning
                    const assetUrl = "/css/app.css";
                    const versionedUrl = window.cacheBuster.getCacheBustedUrl(assetUrl);

                    expect(versionedUrl).toContain(assetUrl);
                    expect(versionedUrl).toContain("v=");
                    expect(versionedUrl).toContain("t=");
                ');
        });
    }

    /** @test */
    public function cache_buster_handles_fetch_interception()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->script('
                    // Test that fetch is intercepted and cache-busted
                    let interceptedUrl = null;

                    // Override fetch temporarily to capture URL
                    const originalFetch = window.fetch;
                    window.fetch = function(url, options) {
                        interceptedUrl = url;
                        return originalFetch(url, options);
                    };

                    // Make a fetch request
                    fetch("/api/test").catch(() => {}); // Ignore errors

                    // Check that URL was cache-busted
                    expect(interceptedUrl).toContain("v=");
                    expect(interceptedUrl).toContain("t=");
                ');
        });
    }

    /** @test */
    public function cache_buster_works_with_vue_components()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->script('
                    // Test that Vue components have access to cache buster
                    expect(window.$cacheBuster).toBeDefined();
                    expect(window.$asset).toBeDefined();
                    expect(window.$clearAllCaches).toBeDefined();
                    expect(window.$forceReload).toBeDefined();

                    // Test asset function
                    const testAsset = window.$asset("/test.css");
                    expect(testAsset).toContain("v=");
                    expect(testAsset).toContain("t=");
                ');
        });
    }

    /** @test */
    public function cache_buster_periodic_checking_works()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->script('
                    // Test that periodic checking is set up
                    expect(window.cacheBuster.checkInterval).toBeDefined();
                    expect(typeof window.cacheBuster.checkForUpdates).toBe("function");
                ')
                ->pause(2000) // Wait a bit to see if periodic check runs
                ->script('
                    // Verify check interval is set
                    expect(window.cacheBuster.checkInterval).not.toBeNull();
                ');
        });
    }

    /** @test */
    public function cache_buster_handles_visibility_change()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $user->update(['organization_id' => $organization->id]);
        $user->joinGroup($group);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->script('
                    // Mock checkForUpdates to track calls
                    let updateCheckCalled = false;
                    const originalCheck = window.cacheBuster.checkForUpdates;
                    window.cacheBuster.checkForUpdates = function() {
                        updateCheckCalled = true;
                        return originalCheck.call(this);
                    };

                    // Simulate visibility change
                    document.dispatchEvent(new Event("visibilitychange"));
                ')
                ->pause(500)
                ->script('
                    // Verify update check was called
                    expect(updateCheckCalled).toBe(true);
                ');
        });
    }
}

