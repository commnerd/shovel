<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AISettingsPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure AI provider for tests to prevent middleware redirects
        Setting::set('ai.cerebrus.api_key', 'test-cerebrus-key', 'string', 'Cerebrus API Key');
    }

    public function test_super_admin_can_access_all_ai_settings()
    {
        $organization = Organization::factory()->create();
        $superAdmin = User::factory()->create([
            'organization_id' => $organization->id,
            'is_super_admin' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('permissions')
                ->where('permissions.canAccessProviderConfig', true)
                ->where('permissions.canAccessDefaultConfig', true)
        );
    }

    public function test_regular_user_in_none_organization_can_access_default_config_only()
    {
        $noneOrg = Organization::create([
            'name' => 'None',
            'is_default' => true,
            'domain' => null,
        ]);

        $user = User::factory()->create([
            'organization_id' => $noneOrg->id,
            'is_super_admin' => false,
        ]);

        $response = $this->actingAs($user)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('permissions')
                ->where('permissions.canAccessProviderConfig', false)
                ->where('permissions.canAccessDefaultConfig', true)
        );
    }

    public function test_organization_admin_can_access_default_config_only()
    {
        $organization = Organization::factory()->create(['is_default' => false]);
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'organization_id' => $organization->id,
            'permissions' => ['admin'],
        ]);

        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->roles()->attach($adminRole->id);

        $response = $this->actingAs($admin)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('permissions')
                ->where('permissions.canAccessProviderConfig', false)
                ->where('permissions.canAccessDefaultConfig', true)
        );
    }

    public function test_regular_user_in_regular_organization_cannot_access_ai_settings()
    {
        $organization = Organization::factory()->create(['is_default' => false]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($user)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('permissions')
                ->where('permissions.canAccessProviderConfig', false)
                ->where('permissions.canAccessDefaultConfig', false)
        );
    }

    public function test_only_super_admin_can_update_provider_specific_settings()
    {
        $organization = Organization::factory()->create();
        $regularUser = User::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($regularUser)
            ->post('/settings/ai', [
                'provider' => 'openai',
                'openai_api_key' => 'test-key',
            ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_update_provider_specific_settings()
    {
        $organization = Organization::factory()->create();
        $superAdmin = User::factory()->create([
            'organization_id' => $organization->id,
            'is_super_admin' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->post('/settings/ai', [
                'provider' => 'openai',
                'openai_api_key' => 'test-key',
            ]);

        $response->assertRedirect();
    }

    public function test_regular_user_cannot_update_default_ai_settings()
    {
        $organization = Organization::factory()->create(['is_default' => false]);
        $regularUser = User::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($regularUser)
            ->post('/settings/ai/default', [
                'provider' => 'openai',
                'model' => 'gpt-4',
            ]);

        $response->assertStatus(403);
    }
}
