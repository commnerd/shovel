<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Check if user is approved (not pending)
        if ($user->pending_approval) {
            return redirect()->route('dashboard')->with([
                'status' => 'pending-approval',
                'message' => 'Your account is still pending approval from your organization administrator.',
            ]);
        }

        return $next($request);
    }
}
