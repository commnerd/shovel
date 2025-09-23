<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\AI\Facades\AI;
use App\Services\AIConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\OrganizationSeeder::class);
});

test('ai providers use settings from database for configuration', function () {
    // Set API key in database settings
    Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebras API Key');
    Setting::set('ai.openai.api_key', 'test-openai-key', 'string', 'OpenAI API Key');

    $providers = AI::getAvailableProviders();

    // Both providers should now be configured
    expect($providers)->toHaveKey('cerebras');
    expect($providers)->toHaveKey('openai');
    expect($providers['cerebras']['configured'])->toBe(true);
    expect($providers['openai']['configured'])->toBe(true);
    expect($providers['cerebras']['config']['api_key'])->toBe('test-cerebras-key');
    expect($providers['openai']['config']['api_key'])->toBe('test-openai-key');
});

test('ai providers show as unconfigured when no database settings', function () {
    // Clear any existing settings
    Setting::where('key', 'like', 'ai.%.api_key')->delete();

    $providers = AI::getAvailableProviders();

    // Should show as unconfigured
    foreach ($providers as $provider) {
        if (isset($provider['configured'])) {
            expect($provider['configured'])->toBe(false);
        }
    }
});

test('settings page shows configured providers in default configuration', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    // Set API key in database settings
    Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebras API Key');

    $response = $this->actingAs($user)->get('/settings/system');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->has('configuredProviders.cerebras')
             ->where('configuredProviders.cerebras.configured', true)
             ->missing('configuredProviders.openai') // Should not be there since no API key
             ->has('availableProviders.cerebras') // Still available for configuration
             ->has('availableProviders.openai')   // Still available for configuration
    );
});

test('default ai configuration accepts configured providers', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    // Set API key in database settings
    Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebras API Key');

    $this->actingAs($user);

    $response = $this->post('/settings/ai/default', [
        'provider' => 'cerebras',
        'model' => 'llama3.1-8b',
    ]);

    $response->assertRedirect('/settings/system');
    expect(Setting::get('ai.default.provider'))->toBe('cerebras');
    expect(Setting::get('ai.default.model'))->toBe('llama3.1-8b');
});

test('default ai configuration rejects unconfigured providers', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    // Only set cerebras, leave openai unconfigured
    Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebras API Key');
    Setting::where('key', 'ai.openai.api_key')->delete();

    $this->actingAs($user);

    $response = $this->post('/settings/ai/default', [
        'provider' => 'openai', // Not configured
        'model' => 'gpt-4',
    ]);

    $response->assertSessionHasErrors(['provider']);
});

test('ai configuration service correctly filters based on database settings', function () {
    // Set only cerebras API key
    Setting::set('ai.cerebras.api_key', 'test-cerebras-key', 'string', 'Cerebras API Key');
    Setting::where('key', 'ai.openai.api_key')->delete();
    Setting::where('key', 'ai.anthropic.api_key')->delete();

    $availableProviders = AIConfigurationService::getAvailableProviders();
    $allProviders = AIConfigurationService::getAllProviders();

    // Available should only include cerebras
    expect($availableProviders)->toHaveKey('cerebras');
    expect($availableProviders)->not->toHaveKey('openai');
    expect($availableProviders)->not->toHaveKey('anthropic');

    // All should include everything (for configuration UI)
    expect($allProviders)->toHaveKey('cerebras');
    expect($allProviders)->toHaveKey('openai');
    expect($allProviders)->toHaveKey('anthropic');
});
