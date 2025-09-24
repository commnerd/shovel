<?php

namespace App\Http\Middleware;

use App\Services\DeploymentVersionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AggressiveCacheBustingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add aggressive cache busting headers
        $this->addCacheBustingHeaders($response, $request);

        return $response;
    }

    /**
     * Add aggressive cache busting headers
     */
    private function addCacheBustingHeaders(Response $response, Request $request): void
    {
        $deploymentService = app(DeploymentVersionService::class);
        $version = $deploymentService->getCurrentVersion();

        // Get the deployment version for cache busting
        $deploymentVersion = $version['version'] ?? '1.0.0-dev';
        $timestamp = $version['timestamp'] ?? now()->toISOString();

        // Add deployment version header
        $response->headers->set('X-Deployment-Version', $deploymentVersion);
        $response->headers->set('X-Deployment-Timestamp', $timestamp);

        // Determine cache control based on request type
        if ($this->isApiRequest($request)) {
            $this->addApiCacheHeaders($response);
        } elseif ($this->isStaticAsset($request)) {
            $this->addStaticAssetCacheHeaders($response, $deploymentVersion);
        } else {
            $this->addPageCacheHeaders($response);
        }

        // Add cache busting query parameter to internal links
        $this->addCacheBustingToContent($response, $deploymentVersion);
    }

    /**
     * Add cache headers for API requests
     */
    private function addApiCacheHeaders(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-Cache-Status', 'disabled');
    }

    /**
     * Add cache headers for static assets
     */
    private function addStaticAssetCacheHeaders(Response $response, string $version): void
    {
        // Short cache for CSS/JS with versioning
        if ($this->isCssOrJs($response)) {
            $response->headers->set('Cache-Control', 'public, max-age=3600, immutable');
            $response->headers->set('X-Cache-Status', 'versioned');
        }
        // Long cache for images with versioning
        elseif ($this->isImage($response)) {
            $response->headers->set('Cache-Control', 'public, max-age=86400, immutable');
            $response->headers->set('X-Cache-Status', 'versioned');
        }
        // Medium cache for fonts
        elseif ($this->isFont($response)) {
            $response->headers->set('Cache-Control', 'public, max-age=604800, immutable');
            $response->headers->set('X-Cache-Status', 'versioned');
        }
    }

    /**
     * Add cache headers for page requests
     */
    private function addPageCacheHeaders(Response $response): void
    {
        // Disable caching for HTML pages
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-Cache-Status', 'disabled');

        // Add ETag for conditional requests
        $etag = md5($response->getContent() . now()->format('Y-m-d-H'));
        $response->headers->set('ETag', '"' . $etag . '"');
    }

    /**
     * Add cache busting query parameters to content
     */
    private function addCacheBustingToContent(Response $response, string $version): void
    {
        $content = $response->getContent();

        if (empty($content)) {
            return;
        }

        // Only modify HTML content, not JSON responses (like Inertia)
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/json')) {
            $content = $this->addVersionToAssetUrls($content, $version);
            $response->setContent($content);
        }
    }

    /**
     * Add version parameter to asset URLs
     */
    private function addVersionToAssetUrls(string $content, string $version): string
    {
        // Pattern to match various asset URLs
        $patterns = [
            // CSS files
            '/href=["\']([^"\']*\.css[^"\']*)["\']/' => 'href="$1?v=' . $version . '"',
            // JS files
            '/src=["\']([^"\']*\.js[^"\']*)["\']/' => 'src="$1?v=' . $version . '"',
            // Images
            '/src=["\']([^"\']*\.(?:png|jpg|jpeg|gif|svg|webp|ico)[^"\']*)["\']/' => 'src="$1?v=' . $version . '"',
            // Background images in CSS
            '/url\(["\']?([^"\']*\.(?:png|jpg|jpeg|gif|svg|webp|ico)[^"\']*)["\']?\)/' => 'url("$1?v=' . $version . '")',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    /**
     * Check if request is an API request
     */
    private function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') ||
               $request->expectsJson() ||
               $request->header('Accept') === 'application/json';
    }

    /**
     * Check if request is for a static asset
     */
    private function isStaticAsset(Request $request): bool
    {
        $path = $request->path();

        return str_ends_with($path, '.css') ||
               str_ends_with($path, '.js') ||
               str_ends_with($path, '.png') ||
               str_ends_with($path, '.jpg') ||
               str_ends_with($path, '.jpeg') ||
               str_ends_with($path, '.gif') ||
               str_ends_with($path, '.svg') ||
               str_ends_with($path, '.webp') ||
               str_ends_with($path, '.ico') ||
               str_ends_with($path, '.woff') ||
               str_ends_with($path, '.woff2') ||
               str_ends_with($path, '.ttf') ||
               str_ends_with($path, '.eot');
    }

    /**
     * Check if response is CSS or JS
     */
    private function isCssOrJs(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/css') ||
               str_contains($contentType, 'application/javascript') ||
               str_contains($contentType, 'text/javascript');
    }

    /**
     * Check if response is an image
     */
    private function isImage(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_starts_with($contentType, 'image/');
    }

    /**
     * Check if response is a font
     */
    private function isFont(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'font/') ||
               str_contains($contentType, 'application/font-');
    }
}
