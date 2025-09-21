<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddDeploymentHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add deployment version header
        $deploymentVersion = $this->getDeploymentVersion();
        $response->headers->set('X-Deployment-Version', $deploymentVersion);
        $response->headers->set('X-Deployment-Timestamp', $this->getDeploymentTimestamp());

        // Add cache control headers for HTML responses
        if ($response instanceof \Illuminate\Http\Response) {
            $contentType = $response->headers->get('Content-Type', '');
            if (is_string($contentType) && str_starts_with($contentType, 'text/html')) {
                // For HTML pages, use no-cache to ensure fresh CSRF tokens
                $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');
            }
        }

        // For API responses, allow short caching
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-cache, must-revalidate, max-age=0');
        }

        return $response;
    }

    /**
     * Get the deployment version from various sources
     */
    private function getDeploymentVersion(): string
    {
        // Try to get version from deployment file first
        $deploymentFile = storage_path('app/deployment.json');
        if (file_exists($deploymentFile)) {
            $deployment = json_decode(file_get_contents($deploymentFile), true);
            if (isset($deployment['version'])) {
                return $deployment['version'];
            }
        }

        // Fallback to git commit hash if available
        $gitHash = $this->getGitCommitHash();
        if ($gitHash) {
            return substr($gitHash, 0, 8);
        }

        // Final fallback to app version
        return config('app.version', '1.0.0');
    }

    /**
     * Get deployment timestamp
     */
    private function getDeploymentTimestamp(): string
    {
        $deploymentFile = storage_path('app/deployment.json');
        if (file_exists($deploymentFile)) {
            $deployment = json_decode(file_get_contents($deploymentFile), true);
            if (isset($deployment['timestamp'])) {
                return $deployment['timestamp'];
            }
        }

        return date('c'); // ISO 8601 timestamp
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
                return $ref; // Direct commit hash
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }
}
