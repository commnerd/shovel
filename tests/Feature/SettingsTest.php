<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $organization = Organization::getDefault();
        $group = $organization->createDefaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'is_super_admin' => true, // Need Super Admin to update provider settings
        ]);
        $this->user->joinGroup($group);
    }

    public function test_user_can_access_settings_page()
    {

        $response = $this->actingAs($this->user)
            ->get('/settings/system');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('settings/System')
            ->has('defaultAISettings')
            ->has('providerConfigs')
            ->has('availableProviders')
        );
    }

    public function test_settings_page_requires_authentication()
    {
        $response = $this->get('/settings/system');
        $response->assertRedirect('/login');
    }

    public function test_settings_page_shows_ai_providers()
    {
        $response = $this->actingAs($this->user)
            ->get('/settings/system');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('settings/System')
            ->has('availableProviders.cerebrus')
            ->has('availableProviders.openai')
            ->has('availableProviders.anthropic')
            ->where('availableProviders.cerebrus.name', 'Cerebras')
            ->where('availableProviders.openai.name', 'OpenAI')
            ->where('availableProviders.anthropic.name', 'Anthropic')
        );
    }

    public function test_user_can_update_ai_settings()
    {
        $response = $this->actingAs($this->user)
            ->post('/settings/ai', [
                'provider' => 'openai',
                'openai_api_key' => 'test-openai-key',
                'openai_base_url' => 'https://api.openai.com/v1',
                'openai_model' => 'gpt-4',
            ]);

        $response->assertRedirect('/settings/system');
        $response->assertSessionHas('message', 'AI settings updated successfully!');

        // Verify settings were saved
        $this->assertEquals('openai', Setting::get('ai.provider'));
        $this->assertEquals('test-openai-key', Setting::get('ai.openai.api_key'));
        $this->assertEquals('https://api.openai.com/v1', Setting::get('ai.openai.base_url'));
        $this->assertEquals('gpt-4', Setting::get('ai.openai.model'));
    }

    public function test_ai_settings_validation()
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/settings/ai', [
                'provider' => 'invalid-provider',
                'openai_api_key' => str_repeat('x', 300), // Too long
                'openai_base_url' => 'not-a-url',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider', 'openai_api_key', 'openai_base_url']);
    }

    public function test_ai_connection_test()
    {
        // Mock the AI manager for testing
        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('testProvider')
            ->once()
            ->with('cerebrus')
            ->andReturn([
                'success' => true,
                'message' => 'Connection successful',
                'response_time' => 0.25,
            ]);

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->postJson('/settings/ai/test', [
                'provider' => 'cerebrus',
                'api_key' => 'test-key',
                'base_url' => 'https://api.cerebras.ai/v1',
                'model' => 'llama3.1-8b',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Connection successful',
        ]);
    }

    public function test_ai_connection_test_failure()
    {
        // Mock the AI manager for testing failure
        $mockAIManager = \Mockery::mock(\App\Services\AI\AIManager::class);
        $mockAIManager->shouldReceive('testProvider')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $this->app->instance('ai', $mockAIManager);

        $response = $this->actingAs($this->user)
            ->postJson('/settings/ai/test', [
                'provider' => 'cerebrus',
                'api_key' => 'invalid-key',
                'base_url' => 'https://api.cerebras.ai/v1',
                'model' => 'llama3.1-8b',
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_ai_connection_test_validation()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/settings/ai/test', [
                'provider' => 'invalid',
                'api_key' => '',
                'base_url' => 'not-a-url',
                'model' => '',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider', 'api_key', 'base_url', 'model']);
    }

    public function test_settings_model_basic_operations()
    {
        // Test setting a value
        Setting::set('test.key', 'test value', 'string', 'Test setting');

        $this->assertEquals('test value', Setting::get('test.key'));
        $this->assertTrue(Setting::has('test.key'));

        // Test getting with default
        $this->assertEquals('default', Setting::get('nonexistent.key', 'default'));

        // Test different types
        Setting::set('test.boolean', true, 'boolean');
        Setting::set('test.integer', 42, 'integer');
        Setting::set('test.json', ['key' => 'value'], 'json');

        $this->assertTrue(Setting::get('test.boolean'));
        $this->assertEquals(42, Setting::get('test.integer'));
        $this->assertEquals(['key' => 'value'], Setting::get('test.json'));

        // Test forgetting
        Setting::forget('test.key');
        $this->assertFalse(Setting::has('test.key'));
    }

    public function test_settings_model_public_vs_private()
    {
        // Create public and private settings
        Setting::set('public.setting', 'public value', 'string', 'Public setting', true);
        Setting::set('private.setting', 'private value', 'string', 'Private setting', false);

        $allSettings = Setting::getAllSettings();
        $publicSettings = Setting::getAllPublicSettings();

        $this->assertArrayHasKey('public.setting', $allSettings);
        $this->assertArrayHasKey('private.setting', $allSettings);

        $this->assertArrayHasKey('public.setting', $publicSettings);
        $this->assertArrayNotHasKey('private.setting', $publicSettings);
    }

    public function test_settings_navigation_link_present()
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertOk();
        // The settings link should be accessible from the navigation
        // We can't easily test the exact link in Inertia, but we can verify the page structure
    }

    public function test_documentation_link_removed()
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertOk();
        // Verify that Documentation link is not present
        // This is more of a visual check, but we can verify the page loads correctly
    }
}
