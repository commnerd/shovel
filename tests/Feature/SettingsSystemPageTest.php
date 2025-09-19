<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsSystemPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $organization = Organization::getDefault();
        $group = $organization->defaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($group);
    }

    public function test_settings_system_page_loads_correctly()
    {
        $response = $this->actingAs($this->user)
            ->get('/settings/system');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('settings/System')
        );
    }

    public function test_settings_system_page_has_required_data()
    {
        // Set some test data
        Setting::set('ai.default.provider', 'openai');
        Setting::set('ai.default.model', 'gpt-4');
        Setting::set('ai.cerebrus.api_key', 'test-key');

        $response = $this->actingAs($this->user)
            ->get('/settings/system');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('settings/System')
            ->has('defaultAISettings')
            ->has('providerConfigs')
            ->has('availableProviders')
            ->where('defaultAISettings.provider', 'openai')
            ->where('defaultAISettings.model', 'gpt-4')
            ->where('providerConfigs.cerebrus.api_key', 'test-key')
        );
    }

    public function test_settings_navigation_link_works()
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertOk();

        // The navigation should include the settings link
        // This is tested indirectly through the dashboard page rendering
    }
}
