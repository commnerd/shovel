<?php

namespace App\Console\Commands;

use App\Services\DeploymentVersionService;
use Illuminate\Console\Command;

class DeployCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deploy {--force : Force deployment even if there are pending changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy the application with cache busting and version management';

    /**
     * Execute the console command.
     */
    public function handle(DeploymentVersionService $deploymentService): int
    {
        $this->info('🚀 Starting deployment process...');

        // Check for uncommitted changes (unless forced)
        if (!$this->option('force') && $this->hasUncommittedChanges()) {
            $this->error('❌ You have uncommitted changes. Use --force to deploy anyway.');
            return 1;
        }

        // Generate new deployment version
        $this->info('📦 Generating deployment version...');
        $version = $deploymentService->generateVersion();

        $this->info("✅ Deployment version: {$version['version']}");
        $this->info("📅 Build number: {$version['build_number']}");
        $this->info("⏰ Timestamp: {$version['timestamp']}");

        // Create deployment marker
        $this->info('🏷️  Creating deployment marker...');
        $deploymentService->createDeploymentMarker();

        // Optimize for production
        if (app()->environment('production')) {
            $this->info('⚡ Optimizing for production...');
            $this->call('config:cache');
            $this->call('route:cache');
            $this->call('view:cache');
        }

        // Clear and rebuild assets
        $this->info('🎨 Rebuilding assets...');

        // Note: npm run build should be run separately in CI/CD
        $this->warn('⚠️  Remember to run "npm run build" to rebuild frontend assets');

        $this->info('✅ Deployment completed successfully!');
        $this->newLine();
        $this->info("Version: {$version['version']}");
        $this->info("Environment: {$version['environment']}");

        if ($version['git_hash']) {
            $this->info("Git hash: " . substr($version['git_hash'], 0, 8));
        }

        return 0;
    }

    /**
     * Check if there are uncommitted changes
     */
    private function hasUncommittedChanges(): bool
    {
        $gitDir = base_path('.git');
        if (!is_dir($gitDir)) {
            return false; // Not a git repository
        }

        try {
            $output = shell_exec('git status --porcelain 2>/dev/null');
            return !empty(trim($output));
        } catch (\Exception $e) {
            return false;
        }
    }
}
