<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SuperAdminController extends Controller
{
    /**
     * Show the super admin dashboard.
     */
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'total_organizations' => Organization::count(),
            'pending_users' => User::where('pending_approval', true)->count(),
            'super_admins' => User::where('is_super_admin', true)->count(),
        ];

        return Inertia::render('SuperAdmin/Index', [
            'stats' => $stats,
        ]);
    }

    /**
     * Search users for super admin management (AJAX endpoint).
     */
    public function searchUsers(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = $request->input('query');
        $limit = $request->input('limit', 10);

        $users = User::with(['organization'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderByRaw("
                CASE
                    WHEN name LIKE ? THEN 1
                    WHEN email LIKE ? THEN 2
                    WHEN name LIKE ? THEN 3
                    WHEN email LIKE ? THEN 4
                    ELSE 5
                END
            ", [
                "{$query}%",    // Name starts with query
                "{$query}%",    // Email starts with query
                "%{$query}%",   // Name contains query
                "%{$query}%"    // Email contains query
            ])
            ->limit($limit)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'organization_name' => $user->organization?->name ?? 'No Organization',
                    'organization_id' => $user->organization_id,
                    'is_admin' => $user->isAdmin(),
                    'is_super_admin' => $user->is_super_admin,
                    'pending_approval' => $user->pending_approval,
                    'avatar' => $user->name[0] ?? 'U', // First letter for avatar
                ];
            });

        return response()->json([
            'users' => $users,
            'query' => $query,
            'total' => $users->count(),
        ]);
    }

    /**
     * Show all users for super admin management.
     */
    public function users(Request $request)
    {
        $query = User::with(['organization', 'groups', 'roles']);

        // Filter by organization if specified
        if ($request->has('organization')) {
            $query->where('organization_id', $request->organization);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString()
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'organization_id' => $user->organization_id,
                    'organization_name' => $user->organization?->name,
                    'is_admin' => $user->isAdmin(),
                    'is_super_admin' => $user->is_super_admin,
                    'pending_approval' => $user->pending_approval,
                    'approved_at' => $user->approved_at?->toISOString(),
                    'created_at' => $user->created_at->toISOString(),
                    'groups_count' => $user->groups->count(),
                    'roles_count' => $user->roles->count(),
                ];
            });

        return Inertia::render('SuperAdmin/Users', [
            'users' => $users,
            'filters' => [
                'organization' => $request->organization,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Show all organizations for super admin management.
     */
    public function organizations(Request $request)
    {
        $query = Organization::with(['creator', 'groups', 'users'])
            ->withCount(['users', 'groups']);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $organizations = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'address' => $org->address,
                    'domain_suffix' => $org->domain,
                    'creator_id' => $org->creator_id,
                    'creator_name' => $org->creator?->name,
                    'users_count' => $org->users_count,
                    'groups_count' => $org->groups_count,
                    'created_at' => $org->created_at->toISOString(),
                ];
            });

        return Inertia::render('SuperAdmin/Organizations', [
            'organizations' => $organizations,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Login as another user (super admin only).
     */
    public function loginAsUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Prevent self-impersonation
        if ($user->id === auth()->id()) {
            return redirect('/dashboard')->with('message', 'Cannot login as yourself');
        }

        // Log the action for audit purposes
        \Log::info('Super admin login as user', [
            'super_admin_id' => auth()->id(),
            'super_admin_email' => auth()->user()->email,
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
            'reason' => $validated['reason'] ?? 'No reason provided',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Store the original user ID in session for returning later
        session(['original_super_admin_id' => auth()->id()]);

        // Login as the target user
        Auth::login($user);

        return redirect('/dashboard')->with('message', 'You are now logged in as ' . $user->name);
    }

    /**
     * Return to original super admin account.
     */
    public function returnToSuperAdmin()
    {
        $originalSuperAdminId = session('original_super_admin_id');

        if (!$originalSuperAdminId) {
            abort(403, 'No original super admin session found.');
        }

        $originalSuperAdmin = User::find($originalSuperAdminId);

        if (!$originalSuperAdmin || !$originalSuperAdmin->isSuperAdmin()) {
            abort(403, 'Original super admin account not found or no longer valid.');
        }

        // Clear the session
        session()->forget('original_super_admin_id');

        // Log the return action
        \Log::info('Super admin returned to original account', [
            'super_admin_id' => $originalSuperAdmin->id,
            'super_admin_email' => $originalSuperAdmin->email,
            'impersonated_user_id' => auth()->id(),
            'impersonated_user_email' => auth()->user()->email,
        ]);

        // Login back as super admin
        Auth::login($originalSuperAdmin);

        return redirect('/super-admin')->with('message', 'Returned to super admin account');
    }

    /**
     * Assign super admin role to a user.
     */
    public function assignSuperAdmin(Request $request, User $user)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Log the action
        \Log::info('Super admin role assigned', [
            'assigned_by' => auth()->id(),
            'assigned_to' => $user->id,
            'reason' => $validated['reason'],
        ]);

        $user->makeSuperAdmin();

        return redirect()->back()->with('message', "Super admin role assigned to {$user->name}");
    }

    /**
     * Remove super admin role from a user.
     */
    public function removeSuperAdmin(Request $request, User $user)
    {
        // Prevent removing super admin from self
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'You cannot remove super admin role from yourself.']);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Log the action
        \Log::info('Super admin role removed', [
            'removed_by' => auth()->id(),
            'removed_from' => $user->id,
            'reason' => $validated['reason'],
        ]);

        $user->removeSuperAdmin();

        return redirect()->back()->with('message', "Super admin role removed from {$user->name}");
    }
}
