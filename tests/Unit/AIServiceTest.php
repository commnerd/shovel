<?php

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Providers\CerebrusProvider;

it('can create cerebrus provider', function () {
    $config = [
        'api_key' => 'test-key',
        'base_url' => 'https://api.cerebrus.ai',
        'model' => 'gpt-4',
        'timeout' => 30,
        'max_tokens' => 4000,
        'temperature' => 0.7,
    ];

    $provider = new CerebrusProvider($config);

    expect($provider)->toBeInstanceOf(AIProviderInterface::class);
    expect($provider->getName())->toBe('cerebrus');
    expect($provider->isConfigured())->toBeTrue();
});

it('detects unconfigured provider', function () {
    $config = [
        'api_key' => '', // Empty API key
        'base_url' => 'https://api.cerebrus.ai',
        'model' => 'gpt-4',
        'timeout' => 30,
        'max_tokens' => 4000,
        'temperature' => 0.7,
    ];

    $provider = new CerebrusProvider($config);

    expect($provider->isConfigured())->toBeFalse();
});

it('can get provider configuration', function () {
    $config = [
        'api_key' => 'test-key',
        'base_url' => 'https://api.cerebrus.ai',
        'model' => 'gpt-4',
        'timeout' => 30,
        'max_tokens' => 4000,
        'temperature' => 0.7,
    ];

    $provider = new CerebrusProvider($config);

    expect($provider->getConfig())->toBe($config);
});

it('creates fallback tasks when needed', function () {
    $config = [
        'api_key' => 'test-key',
        'base_url' => 'https://api.cerebrus.ai',
        'model' => 'gpt-4',
        'timeout' => 30,
        'max_tokens' => 4000,
        'temperature' => 0.7,
    ];

    $provider = new CerebrusProvider($config);

    // This will use the fallback since we don't have a real API connection
    $tasks = $provider->createFallbackTasks('Test project description');

    expect($tasks)->toBeArray();
    expect($tasks)->toHaveCount(4);
    expect($tasks[0])->toHaveKeys(['title', 'description', 'priority', 'status', 'subtasks']);
});
