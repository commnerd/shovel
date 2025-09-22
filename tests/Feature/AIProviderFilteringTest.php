<?php

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\AIConfigurationService;
use App\Services\AI\Facades\AI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\OrganizationSeeder::class);
});

test('getAvailableProviders only returns configured providers', function () {
    // Mock AI facade to return providers with different configuration states
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
            'anthropic' => [
                'name' => 'Anthropic',
                'configured' => false,
                'error' => 'No API key configured',
            ],
        ]);

    $availableProviders = AIConfigurationService::getAvailableProviders();

    // Should only include configured providers
    expect($availableProviders)->toHaveKey('cerebrus');
    expect($availableProviders)->not->toHaveKey('openai');
    expect($availableProviders)->not->toHaveKey('anthropic');

    // Should include provider metadata
    expect($availableProviders['cerebrus'])->toHaveKey('name');
    expect($availableProviders['cerebrus'])->toHaveKey('models');
    expect($availableProviders['cerebrus'])->toHaveKey('configured');
    expect($availableProviders['cerebrus']['configured'])->toBe(true);
});

test('getAvailableProviders returns empty array when no providers configured', function () {
    // Mock AI facade to return no configured providers
    AI::shouldReceive('getAvailableProviders')
        ->andReturn([
            'cerebrus' => [
                'name' => 'Cerebras',
                'configured' => false,
                'error' => 'No API key configured',
            ],
            'openai' => [
                'name' => 'OpenAI',
                'configured' => false,
                'error' => 'No API key configured',
            ],
        ]);

    $availableProviders = AIConfigurationService::getAvailableProviders();

    expect($availableProviders)->toBeEmpty();
});

test('getAvailableProviders handles AI manager exceptions gracefully', function () {
    // Mock AI facade to throw an exception
    AI::shouldReceive('getAvailableProviders')
        ->andThrow(new \Exception('AI service unavailable'));

    $availableProviders = AIConfigurationService::getAvailableProviders();

    // Should return empty array and not crash
    expect($availableProviders)->toBeEmpty();
});

test('getAllProviders returns all providers regardless of configuration', function () {
    $allProviders = AIConfigurationService::getAllProviders();

    // Should include all known providers
    expect($allProviders)->toHaveKey('cerebrus');
    expect($allProviders)->toHaveKey('openai');
    expect($allProviders)->toHaveKey('anthropic');

    // Should have expected structure
    expect($allProviders['cerebrus'])->toHaveKey('name');
    expect($allProviders['cerebrus'])->toHaveKey('models');
    expect($allProviders['cerebrus'])->toHaveKey('fields');
});

test('project creation validates only configured providers', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

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
    $response = $this->post('/dashboard/projects', [
        'title' => 'Test Project',
        'description' => 'A test project description',
        'group_id' => $group->id,
        'ai_provider' => 'cerebrus',
        'ai_model' => 'llama-4-scout-17b-16e-instruct',
        'project_type' => 'iterative',
    ]);

    $response->assertRedirect();

    // Should reject unconfigured provider
    $response = $this->post('/dashboard/projects', [
        'title' => 'Test Project 2',
        'description' => 'Another test project description',
        'group_id' => $group->id,
        'ai_provider' => 'openai', // Not configured
        'ai_model' => 'gpt-4',
        'project_type' => 'iterative',
    ]);

    $response->assertSessionHasErrors(['ai_provider']);
});

test('project creation works without ai provider when none configured', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);

    // Mock no providers configured
    AI::shouldReceive('getAvailableProviders')
        ->andReturn([]);

    $this->actingAs($user);

    // Create project directly without going through the controller to test the core logic
    $project = Project::create([
        'user_id' => $user->id,
        'group_id' => $group->id,
        'title' => 'Test Project No AI',
        'description' => 'A test project description without AI',
        'status' => 'active',
        'project_type' => 'iterative',
        'ai_provider' => null,
        'ai_model' => null,
    ]);

    expect($project->exists)->toBe(true);
    expect($project->project_type)->toBe('iterative');
    expect($project->ai_provider)->toBe(null);
});
