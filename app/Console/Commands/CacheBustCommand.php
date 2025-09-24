<?php

namespace App\Console\Commands;

use App\Services\AggressiveCacheBusterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CacheBustCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:bust
                            {--aggressive : Perform aggressive cache busting}
                            {--stats : Show cache statistics}
                            {--dry-run : Show what would be cleared without actually clearing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform aggressive cache busting across all layers';

    /**
     * Execute the console command.
     */
    public function handle(AggressiveCacheBusterService $cacheBuster): int
    {
        $this->info('🚀 Starting cache busting process...');

        if ($this->option('stats')) {
            $this->showCacheStatistics($cacheBuster);
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->showDryRunResults($cacheBuster);
            return Command::SUCCESS;
        }

        if ($this->option('aggressive')) {
            $this->performAggressiveCacheBusting($cacheBuster);
        } else {
            $this->performStandardCacheBusting();
        }

        $this->info('✅ Cache busting completed successfully!');
        return Command::SUCCESS;
    }

    /**
     * Perform aggressive cache busting
     */
    private function performAggressiveCacheBusting(AggressiveCacheBusterService $cacheBuster): void
    {
        $this->info('🔥 Performing aggressive cache busting...');

        $results = $cacheBuster->bustAllCaches();

        if ($results['status'] === 'error') {
            $this->error('❌ Cache busting failed: ' . $results['error']);
            return;
        }

        $this->displayResults($results);
    }

    /**
     * Perform standard cache busting
     */
    private function performStandardCacheBusting(): void
    {
        $this->info('🧹 Performing standard cache clearing...');

        $commands = [
            'cache:clear' => 'Application cache',
            'config:clear' => 'Configuration cache',
            'route:clear' => 'Route cache',
            'view:clear' => 'View cache',
            'event:clear' => 'Event cache',
        ];

        foreach ($commands as $command => $description) {
            $this->line("Clearing {$description}...");
            $this->call($command);
        }

        $this->info('✅ Standard cache clearing completed!');
    }

    /**
     * Display cache busting results
     */
    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 Cache Busting Results:');
        $this->newLine();

        $sections = [
            'laravel_caches' => 'Laravel Caches',
            'database_cache' => 'Database Cache',
            'file_caches' => 'File System Caches',
            'compiled_assets' => 'Compiled Assets',
            'browser_cache' => 'Browser Cache Headers',
            'session_cache' => 'Session Cache',
            'redis_cache' => 'Redis Cache',
            'deployment_version' => 'Deployment Version',
            'custom_caches' => 'Custom Caches',
        ];

        foreach ($sections as $key => $title) {
            if (isset($results[$key])) {
                $this->line("📁 <fg=blue>{$title}</>");

                if (isset($results[$key]['error'])) {
                    $this->line("  ❌ Error: {$results[$key]['error']}");
                } else {
                    foreach ($results[$key] as $item => $status) {
                        if ($item !== 'error') {
                            $icon = $status === 'cleared' || $status === 'deleted' || $status === 'truncated' ? '✅' : 'ℹ️';
                            $this->line("  {$icon} {$item}: {$status}");
                        }
                    }
                }
                $this->newLine();
            }
        }

        if (isset($results['deployment_version']['new_version'])) {
            $this->info("🎯 New deployment version: {$results['deployment_version']['new_version']}");
        }
    }

    /**
     * Show cache statistics
     */
    private function showCacheStatistics(AggressiveCacheBusterService $cacheBuster): void
    {
        $this->info('📈 Cache Statistics:');
        $this->newLine();

        $stats = $cacheBuster->getCacheStatistics();

        if (isset($stats['error'])) {
            $this->error('❌ Failed to get cache statistics: ' . $stats['error']);
            return;
        }

        $sections = [
            'database_cache_entries' => 'Database Cache Entries',
            'cache_locks' => 'Cache Locks',
            'framework_cache_files' => 'Framework Cache Files',
            'compiled_view_files' => 'Compiled View Files',
            'session_files' => 'Session Files',
            'build_files' => 'Build Files',
            'current_version' => 'Current Version',
            'deployment_timestamp' => 'Deployment Timestamp',
        ];

        foreach ($sections as $key => $title) {
            if (isset($stats[$key])) {
                $value = $stats[$key];
                if (is_numeric($value)) {
                    $this->line("📊 {$title}: <fg=green>{$value}</>");
                } else {
                    $this->line("📊 {$title}: <fg=blue>{$value}</>");
                }
            }
        }
    }

    /**
     * Show dry run results
     */
    private function showDryRunResults(AggressiveCacheBusterService $cacheBuster): void
    {
        $this->info('🔍 Dry Run - What would be cleared:');
        $this->newLine();

        $stats = $cacheBuster->getCacheStatistics();

        $this->line('📁 <fg=yellow>Laravel Caches</>');
        $this->line('  🗑️ Application cache');
        $this->line('  🗑️ Configuration cache');
        $this->line('  🗑️ Route cache');
        $this->line('  🗑️ View cache');
        $this->line('  🗑️ Event cache');
        $this->line('  🗑️ Queue cache');
        $this->newLine();

        if (isset($stats['database_cache_entries']) && $stats['database_cache_entries'] > 0) {
            $this->line("📁 <fg=yellow>Database Cache</> ({$stats['database_cache_entries']} entries)");
            $this->line('  🗑️ Cache table');
            $this->line('  🗑️ Cache locks table');
            $this->newLine();
        }

        if (isset($stats['framework_cache_files']) && $stats['framework_cache_files'] > 0) {
            $this->line("📁 <fg=yellow>File System Caches</> ({$stats['framework_cache_files']} files)");
            $this->line('  🗑️ Framework cache directory');
            $this->line('  🗑️ Compiled views');
            $this->line('  🗑️ Session files');
            $this->newLine();
        }

        if (isset($stats['build_files']) && $stats['build_files'] > 0) {
            $this->line("📁 <fg=yellow>Compiled Assets</> ({$stats['build_files']} files)");
            $this->line('  🗑️ Build directory');
            $this->line('  🗑️ Vite manifest');
            $this->line('  🗑️ Mix manifest');
            $this->newLine();
        }

        $this->line('📁 <fg=yellow>Browser Cache</>');
        $this->line('  🗑️ Update .htaccess headers');
        $this->newLine();

        $this->line('📁 <fg=yellow>Session Cache</>');
        $this->line('  🗑️ Regenerate session ID');
        $this->line('  🗑️ Clear session data');
        $this->line('  🗑️ Regenerate CSRF token');
        $this->newLine();

        $this->line('📁 <fg=yellow>Deployment Version</>');
        $this->line('  🗑️ Generate new version');
        $this->line('  🗑️ Update deployment marker');
        $this->newLine();

        $this->warn('⚠️  This is a dry run. No caches were actually cleared.');
        $this->info('💡 Run with --aggressive flag to perform actual cache busting.');
    }
}

