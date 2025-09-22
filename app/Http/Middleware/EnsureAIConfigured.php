<?php

namespace App\Http\Middleware;

use App\Services\AIConfigurationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAIConfigured
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply this middleware to authenticated users
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // Only apply to super admins (first user or explicitly marked as super admin)
        if (!$user->isSuperAdmin()) {
            return $next($request);
        }

        // Skip if already on settings page or AJAX requests
        if ($request->is('settings/*') || $request->ajax() || $request->wantsJson()) {
            return $next($request);
        }

        // Skip for logout and other auth routes
        if ($request->is('logout') || $request->is('auth/*') || $request->is('api/*')) {
            return $next($request);
        }

        // Check if any AI providers are configured
        try {
            $availableProviders = AIConfigurationService::getAvailableProviders();
            
            if (empty($availableProviders)) {
                // No AI providers configured, redirect to settings with a message
                return redirect()->route('settings.system.index')->with([
                    'warning' => 'Please configure at least one AI provider to unlock the full potential of Foca. AI features enable automatic task generation, project analysis, and intelligent task breakdown.',
                    'ai_setup_required' => true,
                ]);
            }
        } catch (\Exception $e) {
            // If there's an error checking AI configuration, log it but don't block access
            \Log::warning('Failed to check AI configuration in middleware: ' . $e->getMessage());
        }

        return $next($request);
    }
}