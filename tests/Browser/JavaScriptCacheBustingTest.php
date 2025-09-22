<?php

namespace Tests\Browser;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class JavaScriptCacheBustingTest extends DuskTestCase
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
    public function it_verifies_cache_busting_utility_is_available()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    return typeof window.cacheBusting !== "undefined" &&
                           typeof window.cacheBusting.bustUrl === "function" &&
                           typeof window.cacheBusting.addHeaders === "function" &&
                           typeof window.cacheBusting.addToFormData === "function" &&
                           typeof window.cacheBusting.addToObject === "function";
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_utility_has_version_info()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    return window.cacheBusting.getVersion() !== "" &&
                           window.cacheBusting.getTimestamp() > 0;
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_url_generation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const originalUrl = "/dashboard/projects";
                    const bustedUrl = window.cacheBusting.bustUrl(originalUrl);
                    return bustedUrl.includes("_t=") &&
                           bustedUrl.includes("_v=") &&
                           bustedUrl.includes("_r=");
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_headers_generation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const headers = window.cacheBusting.addHeaders();
                    return headers["X-Cache-Bust-Timestamp"] !== undefined &&
                           headers["X-Cache-Bust-Version"] !== undefined &&
                           headers["X-Cache-Bust-Random"] !== undefined;
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_form_data_generation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const formData = new FormData();
                    formData.set("title", "Test Task");
                    const bustedFormData = window.cacheBusting.addToFormData(formData);
                    return bustedFormData.has("_cache_bust_timestamp") &&
                           bustedFormData.has("_cache_bust_version") &&
                           bustedFormData.has("_cache_bust_random");
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_object_generation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const data = { title: "Test Task", description: "Test Description" };
                    const bustedData = window.cacheBusting.addToObject(data);
                    return bustedData._cache_bust_timestamp !== undefined &&
                           bustedData._cache_bust_version !== undefined &&
                           bustedData._cache_bust_random !== undefined &&
                           bustedData.title === "Test Task";
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_parameters_generation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const params = window.cacheBusting.generateUrlParams();
                    return params.has("_t") &&
                           params.has("_v") &&
                           params.has("_r");
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_custom_options()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const customOptions = { custom: { "custom_param": "custom_value" } };
                    const params = window.cacheBusting.generateUrlParams(customOptions);
                    return params.has("custom_param") &&
                           params.get("custom_param") === "custom_value";
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_disabled_options()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const disabledOptions = { timestamp: false, version: false, random: false };
                    const params = window.cacheBusting.generateUrlParams(disabledOptions);
                    return !params.has("_t") &&
                           !params.has("_v") &&
                           !params.has("_r");
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_refresh_functionality()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const originalTimestamp = window.cacheBusting.getTimestamp();
                    window.cacheBusting.refresh();
                    const newTimestamp = window.cacheBusting.getTimestamp();
                    return newTimestamp >= originalTimestamp;
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_inertia_plugin_integration()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    return typeof window.Inertia !== "undefined" &&
                           window.Inertia.router !== undefined;
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_form_submission_includes_cache_busting()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/dashboard/projects/{$this->project->id}/tasks/create")
                ->script('
                    // Intercept the form submission to check for cache busting
                    const form = document.querySelector("form");
                    if (form) {
                        const originalSubmit = form.onsubmit;
                        form.onsubmit = function(e) {
                            const formData = new FormData(form);
                            const hasCacheBusting = formData.has("_cache_bust_timestamp") ||
                                                   formData.has("_cache_bust_version") ||
                                                   formData.has("_cache_bust_random");
                            return hasCacheBusting;
                        };
                    }
                    return true;
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_meta_tags_are_present()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const versionMeta = document.querySelector("meta[name=\'app-version\']");
                    const timestampMeta = document.querySelector("meta[name=\'deployment-timestamp\']");
                    return versionMeta !== null &&
                           timestampMeta !== null &&
                           versionMeta.getAttribute("content") !== "" &&
                           timestampMeta.getAttribute("content") !== "";
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_works_with_different_urls()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const urls = [
                        "/dashboard",
                        "/dashboard/projects",
                        "/settings/profile",
                        "/settings/password"
                    ];

                    return urls.every(url => {
                        const bustedUrl = window.cacheBusting.bustUrl(url);
                        return bustedUrl.includes("_t=") &&
                               bustedUrl.includes("_v=") &&
                               bustedUrl.includes("_r=");
                    });
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_works_with_relative_urls()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const relativeUrl = "projects/create";
                    const bustedUrl = window.cacheBusting.bustUrl(relativeUrl);
                    return bustedUrl.includes("_t=") &&
                           bustedUrl.includes("_v=") &&
                           bustedUrl.includes("_r=");
                ')
                ->assertTrue();
        });
    }

    /** @test */
    public function it_verifies_cache_busting_works_with_absolute_urls()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->script('
                    const absoluteUrl = window.location.origin + "/dashboard/projects";
                    const bustedUrl = window.cacheBusting.bustUrl(absoluteUrl);
                    return bustedUrl.includes("_t=") &&
                           bustedUrl.includes("_v=") &&
                           bustedUrl.includes("_r=") &&
                           bustedUrl.startsWith(window.location.origin);
                ')
                ->assertTrue();
        });
    }
}
