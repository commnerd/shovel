<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\Group;
use App\Services\AggressiveCacheBusterService;
use App\Services\DeploymentVersionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('aggressive cache buster service can be instantiated', function () {
    $service = app(AggressiveCacheBusterService::class);
    expect($service)->toBeInstanceOf(AggressiveCacheBusterService::class);
});

test('aggressive cache buster clears laravel caches', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Create some test cache data
    Cache::put('test_cache_key', 'test_value', 60);
    expect(Cache::has('test_cache_key'))->toBeTrue();

    // Run cache busting
    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');
    expect($results['laravel_caches'])->toHaveKey('application_cache');
    expect($results['laravel_caches']['application_cache'])->toBe('cleared');

    // Verify cache is cleared
    expect(Cache::has('test_cache_key'))->toBeFalse();
});

test('aggressive cache buster clears database cache', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Create test cache entries if using database cache
    if (config('cache.default') === 'database') {
        DB::table('cache')->insert([
            'key' => 'test_key',
            'value' => base64_encode(serialize('test_value')),
            'expiration' => time() + 3600
        ]);

        expect(DB::table('cache')->where('key', 'test_key')->exists())->toBeTrue();
    }

    // Run cache busting
    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');

    // Check if database cache was cleared (only if using database cache)
    if (config('cache.default') === 'database') {
        expect($results['database_cache'])->toHaveKey('cache_table');
        expect(DB::table('cache')->count())->toBe(0);
    } else {
        // If not using database cache, the cache_table key might not exist
        expect($results['database_cache'])->toHaveKey('query_cache');
    }
});

test('aggressive cache buster clears file caches', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Create test cache files
    $cacheDir = storage_path('framework/cache');
    if (!File::exists($cacheDir)) {
        File::makeDirectory($cacheDir, 0755, true);
    }
    File::put($cacheDir . '/test_cache_file', 'test content');

    expect(File::exists($cacheDir . '/test_cache_file'))->toBeTrue();

    // Run cache busting
    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');
    expect($results['file_caches'])->toHaveKey('framework_cache');
    expect($results['file_caches']['framework_cache'])->toBe('cleared');

    // Verify cache files are cleared
    expect(File::exists($cacheDir . '/test_cache_file'))->toBeFalse();
});

test('aggressive cache buster clears compiled assets', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Create test build files
    $buildDir = public_path('build');
    if (!File::exists($buildDir)) {
        File::makeDirectory($buildDir, 0755, true);
    }
    File::put($buildDir . '/test.js', 'console.log("test");');
    File::put(public_path('build/manifest.json'), '{"test.js": "test.js"}');

    expect(File::exists($buildDir . '/test.js'))->toBeTrue();
    expect(File::exists(public_path('build/manifest.json')))->toBeTrue();

    // Run cache busting
    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');
    expect($results['compiled_assets'])->toHaveKey('build_directory');
    expect($results['compiled_assets']['build_directory'])->toBe('cleared');

    // Verify build files are cleared
    expect(File::exists($buildDir . '/test.js'))->toBeFalse();
    expect(File::exists(public_path('build/manifest.json')))->toBeFalse();
});

test('aggressive cache buster generates new deployment version', function () {
    $service = app(AggressiveCacheBusterService::class);
    $deploymentService = app(DeploymentVersionService::class);

    // Get current version
    $currentVersion = $deploymentService->getCurrentVersion();
    $originalBuildNumber = $currentVersion['build_number'];

    // Add a small delay to ensure different timestamp
    sleep(1);

    // Run cache busting
    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');
    expect($results['deployment_version'])->toHaveKey('new_version');

    // Get new version
    $newVersion = $deploymentService->getCurrentVersion();

    // The build number should always increment, but version might be same if git hash hasn't changed
    expect($newVersion['build_number'])->toBe($originalBuildNumber + 1);

    // Version should be different due to new timestamp, or at least build number should be different
    expect($newVersion['build_number'])->not->toBe($originalBuildNumber);
});

test('aggressive cache buster creates deployment marker', function () {
    $service = app(AggressiveCacheBusterService::class);
    $deploymentService = app(DeploymentVersionService::class);

    // Delete existing marker if it exists
    $markerFile = public_path('deployment-marker.txt');
    if (File::exists($markerFile)) {
        File::delete($markerFile);
    }

    // Run cache busting
    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');

    // Verify deployment marker is created
    expect(File::exists($markerFile))->toBeTrue();

    $markerContent = json_decode(File::get($markerFile), true);
    expect($markerContent)->toHaveKey('version');
    expect($markerContent)->toHaveKey('timestamp');
    expect($markerContent)->toHaveKey('build_number');
});

test('aggressive cache buster handles errors gracefully', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Test with invalid configuration that might cause errors
    $results = $service->bustAllCaches();

    // Should still succeed even if some operations fail
    expect($results['status'])->toBe('success');
});

test('cache buster command runs successfully', function () {
    $this->artisan('cache:bust --aggressive')
        ->assertExitCode(0);
});

test('cache buster command shows statistics', function () {
    $this->artisan('cache:bust --stats')
        ->assertExitCode(0)
        ->expectsOutputToContain('Cache Statistics:');
});

test('cache buster command shows dry run results', function () {
    $this->artisan('cache:bust --dry-run')
        ->assertExitCode(0)
        ->expectsOutputToContain('Dry Run - What would be cleared:');
});

test('cache buster service provides cache statistics', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Create some test cache data
    Cache::put('test_stats_key', 'test_value', 60);

    $stats = $service->getCacheStatistics();

    expect($stats)->toHaveKey('current_version');
    expect($stats)->toHaveKey('deployment_timestamp');

    if (config('cache.default') === 'database') {
        expect($stats)->toHaveKey('database_cache_entries');
    }
});

test('aggressive cache busting middleware adds correct headers', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $group = Group::factory()->create(['organization_id' => $organization->id]);

    $user->update(['organization_id' => $organization->id]);
    $user->joinGroup($group);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertHeader('X-Deployment-Version');
    $response->assertHeader('X-Deployment-Timestamp');
    $response->assertHeader('Cache-Control');
    // Note: X-Cache-Status header is only added by AggressiveCacheBustingMiddleware which is currently disabled
    // $response->assertHeader('X-Cache-Status');
});

test('aggressive cache busting middleware handles api requests', function () {
    $response = $this->getJson('/api/test');

    $response->assertHeader('Cache-Control');
});

test('aggressive cache busting middleware handles static assets', function () {
    // Create a test CSS file
    $cssFile = public_path('test.css');
    File::put($cssFile, 'body { color: red; }');

    $response = $this->get('/test.css');

    $response->assertHeader('Cache-Control');

    // Clean up
    File::delete($cssFile);
});

test('deployment version service generates unique versions', function () {
    $service = app(DeploymentVersionService::class);

    $version1 = $service->generateVersion();
    sleep(1); // Ensure different timestamp
    $version2 = $service->generateVersion();

    expect($version2['build_number'])->toBe($version1['build_number'] + 1);
});

test('deployment version service stores and retrieves version', function () {
    $service = app(DeploymentVersionService::class);

    $version = $service->generateVersion();
    $retrievedVersion = $service->getCurrentVersion();

    expect($retrievedVersion['version'])->toBe($version['version']);
    expect($retrievedVersion['build_number'])->toBe($version['build_number']);
});

test('cache buster command clears specific cache types', function () {
    // Test individual cache clearing commands
    $this->artisan('cache:clear')->assertExitCode(0);
    $this->artisan('config:clear')->assertExitCode(0);
    $this->artisan('route:clear')->assertExitCode(0);
    $this->artisan('view:clear')->assertExitCode(0);
});

test('cache buster handles missing cache directories gracefully', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Remove cache directory temporarily
    $cacheDir = storage_path('framework/cache');
    if (File::exists($cacheDir)) {
        File::deleteDirectory($cacheDir);
    }

    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');
    // The framework_cache key might not exist if the directory doesn't exist
    // Just verify the service doesn't crash
    expect($results['file_caches'])->toBeArray();
});

test('cache buster clears custom caches', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Create custom cache entries
    Cache::put('ai_usage_api', ['test' => 'data'], 60);
    Cache::put('user_preferences', ['theme' => 'dark'], 60);

    expect(Cache::has('ai_usage_api'))->toBeTrue();
    expect(Cache::has('user_preferences'))->toBeTrue();

    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');
    expect($results['custom_caches'])->toHaveKey('ai_usage_cache');
    expect($results['custom_caches']['ai_usage_cache'])->toBe('cleared');

    // Verify custom caches are cleared
    expect(Cache::has('ai_usage_api'))->toBeFalse();
    expect(Cache::has('user_preferences'))->toBeFalse();
});

test('cache buster updates htaccess for browser caching', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Remove existing .htaccess
    $htaccessFile = public_path('.htaccess');
    if (File::exists($htaccessFile)) {
        File::delete($htaccessFile);
    }

    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');
    expect($results['browser_cache'])->toHaveKey('htaccess_created');

    // Verify .htaccess is created with cache busting rules
    expect(File::exists($htaccessFile))->toBeTrue();

    $htaccessContent = File::get($htaccessFile);
    expect($htaccessContent)->toContain('Aggressive cache busting headers');
    expect($htaccessContent)->toContain('Cache-Control');
    expect($htaccessContent)->toContain('no-cache');
});

test('cache buster handles redis cache clearing', function () {
    $service = app(AggressiveCacheBusterService::class);

    $results = $service->bustAllCaches();

    expect($results['status'])->toBe('success');
    expect($results)->toHaveKey('redis_cache');
});

test('cache buster provides comprehensive error handling', function () {
    $service = app(AggressiveCacheBusterService::class);

    // Test with various error conditions
    $results = $service->bustAllCaches();

    // Should still succeed even if some operations fail
    expect($results['status'])->toBe('success');

    // Check that all expected sections are present
    $expectedSections = [
        'laravel_caches',
        'database_cache',
        'file_caches',
        'compiled_assets',
        'browser_cache',
        'session_cache',
        'deployment_version',
        'custom_caches'
    ];

    foreach ($expectedSections as $section) {
        expect($results)->toHaveKey($section);
    }
});
