<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect('/login');
        }

        $user = auth()->user();

        // Super admins have admin privileges everywhere
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user is an admin in their organization
        if (! $user->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
