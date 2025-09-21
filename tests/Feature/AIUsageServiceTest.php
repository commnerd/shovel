<?php

use App\Services\AI\AIUsageService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('AIUsageService can be instantiated without API key configured', function () {
    // Ensure no API key is configured
    Config::set('ai.providers.openai.api_key', null);

    // Should not throw any exceptions (this was the original type error)
    $service = new AIUsageService();

    expect($service)->toBeInstanceOf(AIUsageService::class);

    // Should be able to call methods without type errors
    $metrics = $service->getUsageMetrics();
    expect($metrics)->toBeArray();
});

test('AIUsageService handles null API key without type errors', function () {
    // Test various null/empty configurations
    $testCases = [null, '', '   '];

    foreach ($testCases as $apiKey) {
        Config::set('ai.providers.openai.api_key', $apiKey);

        // Should not throw TypeError: Cannot assign null to property
        $service = new AIUsageService();
        $metrics = $service->getUsageMetrics();

        expect($metrics)
            ->toHaveKey('status', 'success')
            ->toHaveKey('api_usage', null)
            ->toHaveKey('quota_info', null);
    }
});

test('AIUsageService returns success status when no API key is configured', function () {
    // Ensure no API key is configured
    Config::set('ai.providers.openai.api_key', null);
    Config::set('ai.providers.openai.base_url', 'https://api.openai.com/v1');

    $service = new AIUsageService();
    $metrics = $service->getUsageMetrics();

    expect($metrics)
        ->toHaveKey('status', 'success')
        ->toHaveKey('api_usage', null)
        ->toHaveKey('local_usage')
        ->toHaveKey('quota_info', null)
        ->toHaveKey('last_updated');

    // Should not have error key when API key is missing but local usage is available
    expect($metrics)->not->toHaveKey('error');
});

test('AIUsageService returns local usage when no API key is configured', function () {
    // Ensure no API key is configured
    Config::set('ai.providers.openai.api_key', null);

    $service = new AIUsageService();
    $metrics = $service->getUsageMetrics();

    expect($metrics['local_usage'])
        ->toHaveKey('today')
        ->toHaveKey('month')
        ->toHaveKey('recent_requests');

    // Today usage should have expected structure
    expect($metrics['local_usage']['today'])
        ->toHaveKey('requests')
        ->toHaveKey('successful_requests')
        ->toHaveKey('failed_requests')
        ->toHaveKey('tokens_estimated')
        ->toHaveKey('cost_estimated');

    // Month usage should have expected structure
    expect($metrics['local_usage']['month'])
        ->toHaveKey('requests')
        ->toHaveKey('tokens_estimated')
        ->toHaveKey('cost_estimated');

    // All values should be numeric
    expect($metrics['local_usage']['recent_requests'])->toBeInt();
    expect($metrics['local_usage']['today']['requests'])->toBeInt();
    expect($metrics['local_usage']['today']['tokens_estimated'])->toBeInt();
    expect($metrics['local_usage']['today']['cost_estimated'])->toBeNumeric();
    expect($metrics['local_usage']['month']['requests'])->toBeInt();
    expect($metrics['local_usage']['month']['tokens_estimated'])->toBeInt();
    expect($metrics['local_usage']['month']['cost_estimated'])->toBeNumeric();
});

test('AIUsageService works with valid API key configuration', function () {
    // Mock a valid API key
    Config::set('ai.providers.openai.api_key', 'sk-test-key');
    Config::set('ai.providers.openai.base_url', 'https://api.openai.com/v1');

    // Mock successful API responses
    Http::fake([
        'https://api.openai.com/v1/usage*' => Http::response([
            'total_requests' => 100,
            'total_tokens' => 5000,
            'total_cost' => 0.75,
            'date' => now()->format('Y-m-d'),
        ], 200),
        'https://api.openai.com/v1/billing/subscription*' => Http::response([
            'hard_limit_usd' => 100.0,
            'soft_limit_usd' => 80.0,
            'has_payment_method' => true,
        ], 200),
    ]);

    $service = new AIUsageService();
    $metrics = $service->getUsageMetrics();

    expect($metrics)
        ->toHaveKey('status', 'success')
        ->toHaveKey('api_usage')
        ->toHaveKey('local_usage')
        ->toHaveKey('quota_info')
        ->toHaveKey('last_updated');

    // API usage should contain the mocked data
    expect($metrics['api_usage'])
        ->toHaveKey('total_requests', 100)
        ->toHaveKey('total_tokens', 5000)
        ->toHaveKey('total_cost', 0.75);

    // Quota info should contain the mocked data
    expect($metrics['quota_info'])
        ->toHaveKey('hard_limit_usd', 100.0)
        ->toHaveKey('soft_limit_usd', 80.0)
        ->toHaveKey('has_payment_method', true);
});

test('AIUsageService handles API failures gracefully', function () {
    // Configure API key but mock API failure
    Config::set('ai.providers.openai.api_key', 'sk-test-key');
    Config::set('ai.providers.openai.base_url', 'https://api.openai.com/v1');

    // Mock API failure
    Http::fake([
        'https://api.openai.com/v1/usage*' => Http::response([], 500),
        'https://api.openai.com/v1/billing/subscription*' => Http::response([], 500),
    ]);

    $service = new AIUsageService();
    $metrics = $service->getUsageMetrics();

    // Service should still return success status with local usage when API fails
    expect($metrics)
        ->toHaveKey('status', 'success')
        ->toHaveKey('api_usage', null)
        ->toHaveKey('local_usage')
        ->toHaveKey('quota_info', null)
        ->toHaveKey('last_updated');

    // Should not have error key when API fails but local usage is available
    expect($metrics)->not->toHaveKey('error');
});

test('AIUsageService makes API calls when configured', function () {
    // Configure API key
    Config::set('ai.providers.openai.api_key', 'sk-test-key');
    Config::set('ai.providers.openai.base_url', 'https://api.openai.com/v1');

    // Mock API responses
    Http::fake([
        'https://api.openai.com/v1/usage*' => Http::response([
            'total_requests' => 50,
            'total_tokens' => 2500,
            'total_cost' => 0.25,
            'date' => now()->format('Y-m-d'),
        ], 200),
        'https://api.openai.com/v1/billing/subscription*' => Http::response([
            'hard_limit_usd' => 50.0,
            'soft_limit_usd' => 40.0,
            'has_payment_method' => false,
        ], 200),
    ]);

    $service = new AIUsageService();
    $metrics = $service->getUsageMetrics();

    // Should have made API calls
    expect($metrics)
        ->toHaveKey('status', 'success')
        ->toHaveKey('api_usage')
        ->toHaveKey('quota_info');

    // API usage should contain the mocked data
    expect($metrics['api_usage'])
        ->toHaveKey('total_requests', 50)
        ->toHaveKey('total_tokens', 2500)
        ->toHaveKey('total_cost', 0.25);

    // Quota info should contain the mocked data
    expect($metrics['quota_info'])
        ->toHaveKey('hard_limit_usd', 50.0)
        ->toHaveKey('soft_limit_usd', 40.0)
        ->toHaveKey('has_payment_method', false);

    // Verify HTTP requests were made
    Http::assertSentCount(2); // One for usage, one for billing
});

test('AIUsageService handles empty API responses', function () {
    // Configure API key
    Config::set('ai.providers.openai.api_key', 'sk-test-key');
    Config::set('ai.providers.openai.base_url', 'https://api.openai.com/v1');

    // Mock empty API responses
    Http::fake([
        'https://api.openai.com/v1/usage*' => Http::response([
            // Empty response
        ], 200),
        'https://api.openai.com/v1/billing/subscription*' => Http::response([
            // Empty response
        ], 200),
    ]);

    $service = new AIUsageService();
    $metrics = $service->getUsageMetrics();

    expect($metrics)
        ->toHaveKey('status', 'success')
        ->toHaveKey('api_usage')
        ->toHaveKey('local_usage')
        ->toHaveKey('quota_info');

    // API usage should have default values for empty responses
    expect($metrics['api_usage'])
        ->toHaveKey('total_requests', 0)
        ->toHaveKey('total_tokens', 0)
        ->toHaveKey('total_cost', 0);
});
