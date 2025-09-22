<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheBustingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add cache busting headers for all responses
        $this->addCacheBustingHeaders($response, $request);

        return $response;
    }

    /**
     * Add cache busting headers to the response
     */
    private function addCacheBustingHeaders(Response $response, Request $request): void
    {
        $timestamp = time();
        $version = $this->getDeploymentVersion();

        // Add cache busting headers
        $response->headers->set('X-Cache-Bust-Timestamp', (string) $timestamp);
        $response->headers->set('X-Cache-Bust-Version', $version);
        $response->headers->set('X-Cache-Bust-Random', (string) rand(100000, 999999));

        // For HTML responses, add additional cache control
        if ($this->isHtmlResponse($response)) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            // Add ETag for cache validation
            $etag = md5($version . $timestamp . $request->url());
            $response->headers->set('ETag', $etag);
        }

        // For API responses, add cache busting parameters
        if ($request->is('api/*') || $request->is('dashboard/*')) {
            $response->headers->set('Cache-Control', 'no-cache, must-revalidate, max-age=0');
            $response->headers->set('X-API-Cache-Bust', $timestamp);
        }

        // Add Last-Modified header
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', $timestamp) . ' GMT');
    }

    /**
     * Check if the response is HTML
     */
    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        return is_string($contentType) && str_starts_with($contentType, 'text/html');
    }

    /**
     * Get deployment version for cache busting
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
