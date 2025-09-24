<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AggressiveCacheBusterService
{
    /**
     * Perform aggressive cache busting across all layers
     */
    public function bustAllCaches(): array
    {
        $results = [];

        try {
            // 1. Clear Laravel application caches
            $results['laravel_caches'] = $this->clearLaravelCaches();

            // 2. Clear database cache
            $results['database_cache'] = $this->clearDatabaseCache();

            // 3. Clear file system caches
            $results['file_caches'] = $this->clearFileCaches();

            // 4. Clear compiled assets
            $results['compiled_assets'] = $this->clearCompiledAssets();

            // 5. Clear browser caches (via headers)
            $results['browser_cache'] = $this->clearBrowserCaches();

            // 6. Clear session and CSRF tokens
            $results['session_cache'] = $this->clearSessionCache();

            // 7. Clear Redis caches if available
            $results['redis_cache'] = $this->clearRedisCache();

            // 8. Generate new deployment version
            $results['deployment_version'] = $this->generateNewDeploymentVersion();

            // 9. Clear any custom caches
            $results['custom_caches'] = $this->clearCustomCaches();

            $results['status'] = 'success';
            $results['timestamp'] = now()->toISOString();

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            $results['timestamp'] = now()->toISOString();

            Log::error('Cache busting failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $results;
    }

    /**
     * Clear all Laravel application caches
     */
    private function clearLaravelCaches(): array
    {
        $results = [];

        try {
            // Clear application cache
            Artisan::call('cache:clear');
            $results['application_cache'] = 'cleared';

            // Clear configuration cache
            Artisan::call('config:clear');
            $results['config_cache'] = 'cleared';

            // Clear route cache
            Artisan::call('route:clear');
            $results['route_cache'] = 'cleared';

            // Clear view cache
            Artisan::call('view:clear');
            $results['view_cache'] = 'cleared';

            // Clear event cache
            Artisan::call('event:clear');
            $results['event_cache'] = 'cleared';

            // Clear queue cache
            Artisan::call('queue:clear');
            $results['queue_cache'] = 'cleared';

            // Clear optimize cache
            if (File::exists(base_path('bootstrap/cache/packages.php'))) {
                Artisan::call('optimize:clear');
                $results['optimize_cache'] = 'cleared';
            }

            // Clear package discovery cache
            Artisan::call('package:discover');
            $results['package_discovery'] = 'refreshed';

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Failed to clear some Laravel caches', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clear database cache
     */
    private function clearDatabaseCache(): array
    {
        $results = [];

        try {
            // Clear cache table if using database cache
            if (config('cache.default') === 'database') {
                DB::table('cache')->truncate();
                $results['cache_table'] = 'truncated';
            }

            // Clear cache locks table
            if (Schema::hasTable('cache_locks')) {
                DB::table('cache_locks')->truncate();
                $results['cache_locks_table'] = 'truncated';
            }

            // Clear any cached queries
            DB::flushQueryLog();
            $results['query_cache'] = 'cleared';

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Failed to clear database cache', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clear file system caches
     */
    private function clearFileCaches(): array
    {
        $results = [];

        try {
            // Clear framework cache directory
            $cacheDir = storage_path('framework/cache');
            if (is_dir($cacheDir)) {
                File::cleanDirectory($cacheDir);
                $results['framework_cache'] = 'cleared';
            }

            // Clear sessions directory
            $sessionDir = storage_path('framework/sessions');
            if (is_dir($sessionDir)) {
                File::cleanDirectory($sessionDir);
                $results['sessions'] = 'cleared';
            }

            // Clear views directory
            $viewDir = storage_path('framework/views');
            if (is_dir($viewDir)) {
                File::cleanDirectory($viewDir);
                $results['compiled_views'] = 'cleared';
            }

            // Clear logs (optional - be careful in production)
            if (app()->environment(['local', 'testing'])) {
                $logDir = storage_path('logs');
                if (is_dir($logDir)) {
                    $logFiles = File::files($logDir);
                    foreach ($logFiles as $file) {
                        if ($file->getExtension() === 'log') {
                            File::put($file->getPathname(), '');
                        }
                    }
                    $results['logs'] = 'cleared';
                }
            }

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Failed to clear file caches', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clear compiled assets
     */
    private function clearCompiledAssets(): array
    {
        $results = [];

        try {
            // Clear Vite manifest
            $viteManifest = public_path('build/manifest.json');
            if (File::exists($viteManifest)) {
                File::delete($viteManifest);
                $results['vite_manifest'] = 'deleted';
            }

            // Clear build directory
            $buildDir = public_path('build');
            if (is_dir($buildDir)) {
                File::cleanDirectory($buildDir);
                $results['build_directory'] = 'cleared';
            }

            // Clear hot file
            $hotFile = public_path('hot');
            if (File::exists($hotFile)) {
                File::delete($hotFile);
                $results['hot_file'] = 'deleted';
            }

            // Clear mix manifest
            $mixManifest = public_path('mix-manifest.json');
            if (File::exists($mixManifest)) {
                File::delete($mixManifest);
                $results['mix_manifest'] = 'deleted';
            }

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Failed to clear compiled assets', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clear browser caches via headers
     */
    private function clearBrowserCaches(): array
    {
        $results = [];

        try {
            // Create cache busting headers file
            $headersFile = public_path('.htaccess');
            $cacheBustingRules = '
# Aggressive cache busting headers
<IfModule mod_headers.c>
    # Disable caching for HTML files
    <FilesMatch "\.(html|htm)$">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Pragma "no-cache"
        Header set Expires "0"
    </FilesMatch>

    # Short cache for CSS/JS with versioning
    <FilesMatch "\.(css|js)$">
        Header set Cache-Control "public, max-age=3600"
    </FilesMatch>

    # Long cache for images with versioning
    <FilesMatch "\.(png|jpg|jpeg|gif|ico|svg)$">
        Header set Cache-Control "public, max-age=86400"
    </FilesMatch>

    # Disable caching for API endpoints
    <FilesMatch "\.(php)$">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Pragma "no-cache"
        Header set Expires "0"
    </FilesMatch>
</IfModule>

# Force cache busting for static assets
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{QUERY_STRING} v=
    RewriteRule ^(.*)$ $1 [L]
</IfModule>
';

            if (File::exists($headersFile)) {
                $currentContent = File::get($headersFile);
                if (strpos($currentContent, 'Aggressive cache busting headers') === false) {
                    File::append($headersFile, $cacheBustingRules);
                    $results['htaccess_updated'] = 'cache_busting_rules_added';
                } else {
                    $results['htaccess_updated'] = 'cache_busting_rules_already_present';
                }
            } else {
                File::put($headersFile, $cacheBustingRules);
                $results['htaccess_created'] = 'cache_busting_rules_added';
            }

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Failed to update browser cache headers', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clear session and CSRF caches
     */
    private function clearSessionCache(): array
    {
        $results = [];

        try {
            // Regenerate session ID if session is started
            if (session()->isStarted()) {
                session()->regenerate(true);
                $results['session_regenerated'] = 'true';
            }

            // Clear all session data
            session()->flush();
            $results['session_data'] = 'cleared';

            // Clear CSRF token
            csrf_token(); // This will generate a new token
            $results['csrf_token'] = 'regenerated';

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Failed to clear session cache', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clear Redis cache if available
     */
    private function clearRedisCache(): array
    {
        $results = [];

        try {
            if (config('cache.default') === 'redis') {
                Cache::store('redis')->flush();
                $results['redis_cache'] = 'flushed';
            }

            // Try to clear Redis database cache
            if (config('database.redis.cache.database')) {
                $redis = app('redis')->connection('cache');
                $redis->flushdb();
                $results['redis_database'] = 'flushed';
            }

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Failed to clear Redis cache', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Generate new deployment version
     */
    private function generateNewDeploymentVersion(): array
    {
        $results = [];

        try {
            $deploymentService = app(DeploymentVersionService::class);
            $version = $deploymentService->generateVersion();
            $deploymentService->createDeploymentMarker();

            $results['new_version'] = $version['version'];
            $results['timestamp'] = $version['timestamp'];
            $results['build_number'] = $version['build_number'];

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Failed to generate deployment version', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clear custom application caches
     */
    private function clearCustomCaches(): array
    {
        $results = [];

        try {
            // Clear AI usage cache
            Cache::forget('ai_usage_api');
            $results['ai_usage_cache'] = 'cleared';

            // Clear any other custom caches
            $customCacheKeys = [
                'user_preferences',
                'app_settings',
                'feature_flags',
                'api_responses',
                'computed_data'
            ];

            foreach ($customCacheKeys as $key) {
                Cache::forget($key);
            }

            $results['custom_cache_keys'] = 'cleared';

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Failed to clear custom caches', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStatistics(): array
    {
        $stats = [];

        try {
            // Database cache stats
            if (config('cache.default') === 'database') {
                $stats['database_cache_entries'] = DB::table('cache')->count();
                $stats['cache_locks'] = Schema::hasTable('cache_locks') ? DB::table('cache_locks')->count() : 0;
            }

            // File system cache stats
            $cacheDir = storage_path('framework/cache');
            if (is_dir($cacheDir)) {
                $stats['framework_cache_files'] = count(File::files($cacheDir));
            }

            $viewDir = storage_path('framework/views');
            if (is_dir($viewDir)) {
                $stats['compiled_view_files'] = count(File::files($viewDir));
            }

            // Session stats
            $sessionDir = storage_path('framework/sessions');
            if (is_dir($sessionDir)) {
                $stats['session_files'] = count(File::files($sessionDir));
            }

            // Build directory stats
            $buildDir = public_path('build');
            if (is_dir($buildDir)) {
                $stats['build_files'] = count(File::allFiles($buildDir));
            }

            // Current deployment version
            $deploymentService = app(DeploymentVersionService::class);
            $version = $deploymentService->getCurrentVersion();
            $stats['current_version'] = $version['version'];
            $stats['deployment_timestamp'] = $version['timestamp'];

        } catch (\Exception $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Schedule periodic cache busting
     */
    public function schedulePeriodicCacheBusting(): void
    {
        // This would typically be called from a scheduled command
        // For now, we'll just log that it's scheduled
        Log::info('Periodic cache busting scheduled', [
            'timestamp' => now()->toISOString()
        ]);
    }
}
