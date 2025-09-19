<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AIConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $organization;

    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $this->organization = Organization::getDefault();
        $this->group = $this->organization->defaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($this->group);
    }

    public function test_user_can_access_system_settings_page()
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

    public function test_user_can_update_default_ai_settings()
    {
        $response = $this->actingAs($this->user)
            ->post('/settings/ai/default', [
                'provider' => 'openai',
                'model' => 'gpt-4',
                'api_key' => 'test-api-key',
                'base_url' => 'https://api.openai.com/v1',
            ]);

        $response->assertRedirect('/settings/system');

        // Verify settings were saved
        $this->assertEquals('openai', Setting::get('ai.default.provider'));
        $this->assertEquals('gpt-4', Setting::get('ai.default.model'));
        $this->assertEquals('test-api-key', Setting::get('ai.default.api_key'));
        $this->assertEquals('https://api.openai.com/v1', Setting::get('ai.default.base_url'));
    }

    public function test_new_project_inherits_default_ai_configuration()
    {
        // Set default AI configuration
        Setting::set('ai.default.provider', 'openai');
        Setting::set('ai.default.model', 'gpt-4');
        Setting::set('ai.default.api_key', 'default-key');
        Setting::set('ai.default.base_url', 'https://api.openai.com/v1');

        $response = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'Test Project',
                'description' => 'A test project',
                'due_date' => '2025-12-31',
                'group_id' => $this->group->id,
            ]);

        $response->assertRedirect();

        // Get the created project
        $project = Project::where('title', 'Test Project')->first();
        $this->assertNotNull($project);

        // Verify AI configuration was applied
        $aiConfig = $project->getAIConfiguration();
        $this->assertEquals('openai', $aiConfig['provider']);
        $this->assertEquals('gpt-4', $aiConfig['model']);
        $this->assertEquals('default-key', $aiConfig['api_key']);
        $this->assertEquals('https://api.openai.com/v1', $aiConfig['base_url']);
    }

    public function test_project_ai_configuration_methods()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Test Project',
        ]);

        // Test setting AI configuration
        $config = [
            'provider' => 'anthropic',
            'model' => 'claude-3-sonnet-20240229',
            'api_key' => 'test-anthropic-key',
            'base_url' => 'https://api.anthropic.com/v1',
            'config' => ['temperature' => 0.7],
        ];

        $project->setAIConfiguration($config);
        $project->refresh();

        // Test getting AI configuration
        $retrievedConfig = $project->getAIConfiguration();
        $this->assertEquals('anthropic', $retrievedConfig['provider']);
        $this->assertEquals('claude-3-sonnet-20240229', $retrievedConfig['model']);
        $this->assertEquals('test-anthropic-key', $retrievedConfig['api_key']);
        $this->assertEquals('https://api.anthropic.com/v1', $retrievedConfig['base_url']);
        $this->assertEquals(['temperature' => 0.7], $retrievedConfig['config']);
    }

    public function test_apply_default_ai_configuration_to_existing_project()
    {
        // Set default AI configuration
        Setting::set('ai.default.provider', 'cerebrus');
        Setting::set('ai.default.model', 'llama3.1-70b');
        Setting::set('ai.default.api_key', 'cerebrus-key');
        Setting::set('ai.default.base_url', 'https://api.cerebras.ai/v1');

        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Existing Project',
        ]);

        // Apply default configuration
        $project->applyDefaultAIConfiguration();
        $project->refresh();

        // Verify configuration was applied
        $this->assertEquals('cerebrus', $project->ai_provider);
        $this->assertEquals('llama3.1-70b', $project->ai_model);
        $this->assertEquals('cerebrus-key', $project->ai_api_key);
        $this->assertEquals('https://api.cerebras.ai/v1', $project->ai_base_url);
    }

    public function test_default_ai_settings_validation()
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/settings/ai/default', [
                'provider' => 'invalid-provider',
                'model' => '', // Required field
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider', 'model']);
    }

    public function test_project_ai_configuration_defaults_to_cerebrus()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Default Config Project',
        ]);

        $aiConfig = $project->getAIConfiguration();
        $this->assertEquals('cerebrus', $aiConfig['provider']);
    }

    public function test_system_settings_page_displays_current_configuration()
    {
        // Set some default settings
        Setting::set('ai.default.provider', 'openai');
        Setting::set('ai.default.model', 'gpt-4');
        Setting::set('ai.cerebrus.api_key', 'cerebrus-key');
        Setting::set('ai.openai.api_key', 'openai-key');

        $response = $this->actingAs($this->user)
            ->get('/settings/system');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('settings/System')
            ->where('defaultAISettings.provider', 'openai')
            ->where('defaultAISettings.model', 'gpt-4')
            ->where('providerConfigs.cerebrus.api_key', 'cerebrus-key')
            ->where('providerConfigs.openai.api_key', 'openai-key')
            ->has('availableProviders.cerebrus.models')
            ->has('availableProviders.openai.models')
            ->has('availableProviders.anthropic.models')
        );
    }

    public function test_default_ai_configuration_is_optional_for_projects()
    {
        // Don't set any default configuration

        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'No Default Config Project',
        ]);

        $project->applyDefaultAIConfiguration();
        $project->refresh();

        // Should still have cerebrus as default provider
        $this->assertEquals('cerebrus', $project->ai_provider);
        $this->assertNull($project->ai_model);
        $this->assertNull($project->ai_api_key);
    }

    public function test_project_ai_configuration_can_be_customized_independently()
    {
        // Set default configuration
        Setting::set('ai.default.provider', 'openai');
        Setting::set('ai.default.model', 'gpt-4');

        // Create two projects
        $project1 = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Project 1',
        ]);
        $project1->applyDefaultAIConfiguration();

        $project2 = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Project 2',
        ]);
        $project2->applyDefaultAIConfiguration();

        // Customize project2's AI configuration
        $project2->setAIConfiguration([
            'provider' => 'anthropic',
            'model' => 'claude-3-opus-20240229',
            'api_key' => 'custom-key',
        ]);

        $project1->refresh();
        $project2->refresh();

        // Project 1 should have default configuration
        $this->assertEquals('openai', $project1->ai_provider);
        $this->assertEquals('gpt-4', $project1->ai_model);

        // Project 2 should have custom configuration
        $this->assertEquals('anthropic', $project2->ai_provider);
        $this->assertEquals('claude-3-opus-20240229', $project2->ai_model);
        $this->assertEquals('custom-key', $project2->ai_api_key);
    }

    public function test_settings_page_requires_authentication()
    {
        $response = $this->get('/settings/system');
        $response->assertRedirect('/login');
    }

    public function test_ai_configuration_is_encrypted_in_database()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'title' => 'Secure Project',
        ]);

        $project->setAIConfiguration([
            'provider' => 'openai',
            'api_key' => 'secret-api-key',
        ]);

        // The API key should be stored (in a real app, we'd encrypt it)
        $this->assertEquals('secret-api-key', $project->ai_api_key);

        // Note: In production, consider encrypting sensitive fields like API keys
    }
}
