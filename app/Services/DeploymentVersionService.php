<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DeploymentVersionService
{
    /**
     * Generate a new deployment version
     */
    public function generateVersion(): array
    {
        $version = [
            'version' => $this->generateVersionNumber(),
            'timestamp' => now()->toISOString(),
            'build_number' => $this->getNextBuildNumber(),
            'git_hash' => $this->getGitCommitHash(),
            'environment' => app()->environment(),
        ];

        // Store deployment info
        $this->storeDeploymentInfo($version);

        // Clear application caches
        $this->clearApplicationCaches();

        return $version;
    }

    /**
     * Get current deployment version
     */
    public function getCurrentVersion(): array
    {
        $deploymentFile = storage_path('app/deployment.json');

        if (file_exists($deploymentFile)) {
            $version = json_decode(file_get_contents($deploymentFile), true);
            if ($version && isset($version['version'])) {
                return $version;
            }
        }

        // Return default version if no deployment file exists
        return [
            'version' => '1.0.0-dev',
            'timestamp' => now()->toISOString(),
            'build_number' => 1,
            'git_hash' => null,
            'environment' => app()->environment(),
        ];
    }

    /**
     * Generate a semantic version number
     */
    private function generateVersionNumber(): string
    {
        $gitHash = $this->getGitCommitHash();
        $shortHash = $gitHash ? substr($gitHash, 0, 8) : 'dev';

        // Use timestamp for version if in production
        if (app()->environment('production')) {
            $timestamp = now()->format('Y.m.d.Hi');
            return "1.0.{$timestamp}";
        }

        // Use git hash for development
        return "1.0.0-{$shortHash}";
    }

    /**
     * Get next build number
     */
    private function getNextBuildNumber(): int
    {
        $deploymentFile = storage_path('app/deployment.json');

        if (file_exists($deploymentFile)) {
            $deployment = json_decode(file_get_contents($deploymentFile), true);
            if (isset($deployment['build_number'])) {
                return $deployment['build_number'] + 1;
            }
        }

        return 1;
    }

    /**
     * Get git commit hash
     */
    private function getGitCommitHash(): ?string
    {
        $gitDir = base_path('.git');
        if (!is_dir($gitDir)) {
            return null;
        }

        try {
            $ref = trim(file_get_contents($gitDir . '/HEAD'));
            if (str_starts_with($ref, 'ref: ')) {
                $ref = substr($ref, 5);
                $commitFile = $gitDir . '/' . $ref;
                if (file_exists($commitFile)) {
                    return trim(file_get_contents($commitFile));
                }
            } else {
                return $ref;
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Store deployment information
     */
    private function storeDeploymentInfo(array $version): void
    {
        $deploymentFile = storage_path('app/deployment.json');

        // Ensure storage directory exists
        $storageDir = dirname($deploymentFile);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        file_put_contents($deploymentFile, json_encode($version, JSON_PRETTY_PRINT));
    }

    /**
     * Clear application caches
     */
    private function clearApplicationCaches(): void
    {
        try {
            // Clear Laravel caches
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');

            // Clear session data to force new CSRF tokens
            if (session()->isStarted()) {
                session()->regenerate();
            }
        } catch (\Exception $e) {
            // Log error but don't fail deployment
            \Log::warning('Failed to clear some caches during deployment', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create a deployment marker file for cache busting
     */
    public function createDeploymentMarker(): void
    {
        $markerFile = public_path('deployment-marker.txt');
        $version = $this->getCurrentVersion();

        file_put_contents($markerFile, json_encode([
            'version' => $version['version'],
            'timestamp' => $version['timestamp'],
            'build_number' => $version['build_number']
        ], JSON_PRETTY_PRINT));
    }
}
