<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\AI\Facades\AI;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\OrganizationSeeder::class);
});

test('settings page only shows configured providers in default configuration', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    // Mock only cerebrus as configured
    AI::shouldReceive('getAvailableProviders')
        ->andReturn([
            'cerebrus' => [
                'name' => 'Cerebras',
                'configured' => true,
                'config' => ['api_key' => 'test-key'],
            ],
            'openai' => [
                'name' => 'OpenAI',
                'configured' => false,
                'error' => 'No API key configured',
            ],
        ]);

    $response = $this->actingAs($user)->get('/settings/system');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->has('configuredProviders.cerebrus')
             ->missing('configuredProviders.openai')
             ->missing('configuredProviders.anthropic')
             ->has('availableProviders.cerebrus') // Still available for configuration
             ->has('availableProviders.openai')   // Still available for configuration
    );
});

test('default AI settings validation only accepts configured providers', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    // Mock only cerebrus as configured
    AI::shouldReceive('getAvailableProviders')
        ->andReturn([
            'cerebrus' => [
                'name' => 'Cerebras',
                'configured' => true,
                'config' => ['api_key' => 'test-key'],
            ],
            'openai' => [
                'name' => 'OpenAI',
                'configured' => false,
                'error' => 'No API key configured',
            ],
        ]);

    $this->actingAs($user);

    // Should accept configured provider
    $response = $this->post('/settings/ai/default', [
        'provider' => 'cerebrus',
        'model' => 'llama3.1-8b',
    ]);

    $response->assertRedirect();
    expect(Setting::get('ai.default.provider'))->toBe('cerebrus');

    // Should reject unconfigured provider
    $response = $this->post('/settings/ai/default', [
        'provider' => 'openai',
        'model' => 'gpt-4',
    ]);

    $response->assertSessionHasErrors(['provider']);
});

test('default AI settings shows error when no providers configured', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    // Mock no providers configured
    AI::shouldReceive('getAvailableProviders')
        ->andReturn([]);

    $this->actingAs($user);

    $response = $this->post('/settings/ai/default', [
        'provider' => 'cerebrus',
        'model' => 'llama3.1-8b',
    ]);

    $response->assertSessionHasErrors(['provider']);
    $response->assertSessionHasErrorsIn('default', ['provider' => 'No AI providers are configured. Please configure at least one provider first.']);
});

test('organization AI settings validation only accepts configured providers', function () {
    $organization = Organization::factory()->create(['is_default' => false]);

    // Create admin role for the organization
    $adminRole = $organization->roles()->create([
        'name' => 'admin',
        'display_name' => 'Administrator',
        'permissions' => ['manage_projects', 'manage_users'],
    ]);

    $user = User::factory()->create(['organization_id' => $organization->id]);
    $user->assignRole($adminRole);

    // Mock only cerebrus as configured
    AI::shouldReceive('getAvailableProviders')
        ->andReturn([
            'cerebrus' => [
                'name' => 'Cerebras',
                'configured' => true,
                'config' => ['api_key' => 'test-key'],
            ],
            'openai' => [
                'name' => 'OpenAI',
                'configured' => false,
                'error' => 'No API key configured',
            ],
        ]);

    $this->actingAs($user);

    // Should accept configured provider
    $response = $this->post('/settings/ai/organization', [
        'provider' => 'cerebrus',
        'model' => 'llama3.1-8b',
    ]);

    $response->assertRedirect();
    expect(Setting::get("ai.organization.{$organization->id}.provider"))->toBe('cerebrus');

    // Should reject unconfigured provider
    $response = $this->post('/settings/ai/organization', [
        'provider' => 'openai',
        'model' => 'gpt-4',
    ]);

    $response->assertSessionHasErrors(['provider']);
});

test('settings page shows empty state when no providers configured', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    // Mock no providers configured
    AI::shouldReceive('getAvailableProviders')
        ->andReturn([]);

    $response = $this->actingAs($user)->get('/settings/system');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->where('configuredProviders', [])
             ->has('availableProviders') // Still shows all for configuration
    );
});
