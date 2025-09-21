<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Organization, Role, Setting};
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleBasedSystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $regularUser;
    private Organization $organization;
    private Organization $defaultOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organizations
        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'is_default' => false,
        ]);

        $this->defaultOrganization = Organization::factory()->create([
            'name' => 'None',
            'is_default' => true,
        ]);

        // Create users
        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'organization_id' => $this->organization->id,
        ]);

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->regularUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Assign admin role (lowercase to match isAdmin() method)
        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'organization_id' => $this->organization->id,
        ]);
        $this->admin->roles()->attach($adminRole->id);
    }

    public function test_super_admin_can_access_system_settings()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('settings/System')
                 ->has('permissions')
                 ->where('permissions.canAccessProviderConfig', true)
                 ->where('permissions.canAccessDefaultConfig', true)
                 ->where('permissions.canAccessOrganizationConfig', false) // Super admin doesn't need org config
                 ->has('user')
                 ->where('user.is_super_admin', true)
        );
    }

    public function test_admin_can_access_system_settings()
    {
        $response = $this->actingAs($this->admin)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('settings/System')
                 ->has('permissions')
                 ->where('permissions.canAccessProviderConfig', false)
                 ->where('permissions.canAccessDefaultConfig', true)
                 ->where('permissions.canAccessOrganizationConfig', true)
                 ->has('user')
                 ->where('user.is_admin', true)
                 ->where('user.is_super_admin', false)
        );
    }

    public function test_regular_user_cannot_access_system_settings()
    {
        $response = $this->actingAs($this->regularUser)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('settings/System')
                 ->has('permissions')
                 ->where('permissions.canAccessProviderConfig', false)
                 ->where('permissions.canAccessDefaultConfig', false)
                 ->where('permissions.canAccessOrganizationConfig', false)
        );
    }

    public function test_super_admin_can_update_provider_specific_settings()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/settings/ai', [
                'provider' => 'openai',
                'openai_api_key' => 'test-api-key',
                'openai_base_url' => 'https://api.openai.com/v1',
            ]);

        $response->assertRedirect('/settings/system');
        $response->assertSessionHas('message');

        $this->assertEquals('test-api-key', Setting::get('ai.openai.api_key'));
        $this->assertEquals('https://api.openai.com/v1', Setting::get('ai.openai.base_url'));
        // Model is no longer stored in system-wide provider configuration
        $this->assertNull(Setting::get('ai.openai.model'));
    }

    public function test_admin_cannot_update_provider_specific_settings()
    {
        $response = $this->actingAs($this->admin)
            ->post('/settings/ai', [
                'provider' => 'openai',
                'openai_api_key' => 'test-api-key',
                'openai_base_url' => 'https://api.openai.com/v1',
                'openai_model' => 'gpt-4',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_organization_ai_settings()
    {
        $response = $this->actingAs($this->admin)
            ->post('/settings/ai/organization', [
                'provider' => 'anthropic',
                'model' => 'claude-3-sonnet-20240229',
            ]);

        $response->assertRedirect('/settings/system');
        $response->assertSessionHas('message');

        $orgId = $this->organization->id;
        $this->assertEquals('anthropic', Setting::get("ai.organization.{$orgId}.provider"));
        $this->assertEquals('claude-3-sonnet-20240229', Setting::get("ai.organization.{$orgId}.model"));
    }

    public function test_super_admin_cannot_update_organization_ai_settings()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/settings/ai/organization', [
                'provider' => 'anthropic',
                'model' => 'claude-3-sonnet-20240229',
            ]);

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_update_organization_ai_settings()
    {
        $response = $this->actingAs($this->regularUser)
            ->post('/settings/ai/organization', [
                'provider' => 'anthropic',
                'model' => 'claude-3-sonnet-20240229',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_from_default_organization_cannot_update_organization_settings()
    {
        $defaultAdmin = User::factory()->create([
            'organization_id' => $this->defaultOrganization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'organization_id' => $this->defaultOrganization->id,
        ]);
        $defaultAdmin->roles()->attach($adminRole->id);

        $response = $this->actingAs($defaultAdmin)
            ->post('/settings/ai/organization', [
                'provider' => 'anthropic',
                'model' => 'claude-3-sonnet-20240229',
            ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_update_default_ai_settings()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/settings/ai/default', [
                'provider' => 'cerebrus',
                'model' => 'llama3.1-70b',
            ]);

        $response->assertRedirect('/settings/system');
        $response->assertSessionHas('message');

        $this->assertEquals('cerebrus', Setting::get('ai.default.provider'));
        $this->assertEquals('llama3.1-70b', Setting::get('ai.default.model'));
    }

    public function test_admin_can_update_default_ai_settings()
    {
        $response = $this->actingAs($this->admin)
            ->post('/settings/ai/default', [
                'provider' => 'cerebrus',
                'model' => 'llama3.1-70b',
            ]);

        $response->assertRedirect('/settings/system');
        $response->assertSessionHas('message');
    }

    public function test_organization_ai_settings_validation()
    {
        // Test required provider
        $response = $this->actingAs($this->admin)
            ->post('/settings/ai/organization', [
                'model' => 'gpt-4',
            ]);

        $response->assertSessionHasErrors('provider');

        // Test required model
        $response = $this->actingAs($this->admin)
            ->post('/settings/ai/organization', [
                'provider' => 'openai',
            ]);

        $response->assertSessionHasErrors('model');

        // Test invalid provider
        $response = $this->actingAs($this->admin)
            ->post('/settings/ai/organization', [
                'provider' => 'invalid-provider',
                'model' => 'gpt-4',
            ]);

        $response->assertSessionHasErrors('provider');
    }

    public function test_system_settings_page_shows_organization_ai_settings_for_admin()
    {
        // Set some organization AI settings first
        $orgId = $this->organization->id;
        Setting::set("ai.organization.{$orgId}.provider", 'openai');
        Setting::set("ai.organization.{$orgId}.model", 'gpt-4');

        $response = $this->actingAs($this->admin)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('organizationAISettings')
                 ->where('organizationAISettings.provider', 'openai')
                 ->where('organizationAISettings.model', 'gpt-4')
        );
    }

    public function test_system_settings_page_shows_null_organization_settings_for_super_admin()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->where('organizationAISettings', null)
        );
    }

    public function test_system_settings_includes_user_information()
    {
        $response = $this->actingAs($this->admin)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('user')
                 ->where('user.is_admin', true)
                 ->where('user.is_super_admin', false)
                 ->has('user.organization')
                 ->where('user.organization.name', 'Test Organization')
                 ->where('user.organization.is_default', false)
        );
    }

    public function test_permission_matrix_for_different_user_types()
    {
        // Super Admin permissions
        $superAdminResponse = $this->actingAs($this->superAdmin)
            ->get('/settings/system');

        $superAdminResponse->assertInertia(fn ($page) =>
            $page->where('permissions.canAccessProviderConfig', true)
                 ->where('permissions.canAccessDefaultConfig', true)
                 ->where('permissions.canAccessOrganizationConfig', false)
        );

        // Admin permissions
        $adminResponse = $this->actingAs($this->admin)
            ->get('/settings/system');

        $adminResponse->assertInertia(fn ($page) =>
            $page->where('permissions.canAccessProviderConfig', false)
                 ->where('permissions.canAccessDefaultConfig', true)
                 ->where('permissions.canAccessOrganizationConfig', true)
        );

        // Regular user permissions
        $userResponse = $this->actingAs($this->regularUser)
            ->get('/settings/system');

        $userResponse->assertInertia(fn ($page) =>
            $page->where('permissions.canAccessProviderConfig', false)
                 ->where('permissions.canAccessDefaultConfig', false)
                 ->where('permissions.canAccessOrganizationConfig', false)
        );
    }
}
