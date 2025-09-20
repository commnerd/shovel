<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AISettingsPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function super_admin_can_access_all_ai_settings()
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($superAdmin)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('permissions')
                ->where('permissions.canAccessProviderConfig', true)
                ->where('permissions.canAccessDefaultConfig', true)
        );
    }

    /** @test */
    public function regular_user_in_none_organization_can_access_default_config_only()
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

    /** @test */
    public function organization_admin_can_access_default_config_only()
    {
        $organization = Organization::factory()->create(['is_default' => false]);
        $adminRole = \App\Models\Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'organization_id' => $organization->id,
            'permissions' => ['admin'],
        ]);

        $admin = User::factory()->create([
            'organization_id' => $organization->id,
            'is_super_admin' => false,
        ]);

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

    /** @test */
    public function regular_user_in_regular_organization_cannot_access_ai_settings()
    {
        $organization = Organization::factory()->create(['is_default' => false]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'is_super_admin' => false,
        ]);

        $response = $this->actingAs($user)
            ->get('/settings/system');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('permissions')
                ->where('permissions.canAccessProviderConfig', false)
                ->where('permissions.canAccessDefaultConfig', false)
        );
    }

    /** @test */
    public function only_super_admin_can_update_provider_specific_settings()
    {
        $regularUser = User::factory()->create(['is_super_admin' => false]);

        $response = $this->actingAs($regularUser)
            ->post('/settings/ai', [
                'provider' => 'openai',
                'openai_api_key' => 'test-key',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function super_admin_can_update_provider_specific_settings()
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($superAdmin)
            ->post('/settings/ai', [
                'provider' => 'openai',
                'openai_api_key' => 'test-key',
            ]);

        $response->assertRedirect();
    }

    /** @test */
    public function regular_user_cannot_update_default_ai_settings()
    {
        $organization = Organization::factory()->create(['is_default' => false]);
        $regularUser = User::factory()->create([
            'organization_id' => $organization->id,
            'is_super_admin' => false,
        ]);

        $response = $this->actingAs($regularUser)
            ->post('/settings/ai/default', [
                'provider' => 'openai',
                'model' => 'gpt-4',
            ]);

        $response->assertStatus(403);
    }
}
