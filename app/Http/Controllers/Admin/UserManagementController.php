<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    /**
     * Search users within the admin's organization (AJAX endpoint).
     */
    public function searchUsers(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = $request->input('query');
        $limit = $request->input('limit', 10);
        $adminUser = auth()->user();

        // Only search users within the same organization
        $users = User::with(['organization'])
            ->where('organization_id', $adminUser->organization_id)
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
     * Show user management page for organization admins.
     */
    public function index(Request $request)
    {
        $adminUser = auth()->user();

        // Get users from the same organization with pagination
        $query = User::with(['organization', 'groups', 'roles'])
            ->where('organization_id', $adminUser->organization_id);

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

        // Also get pending and approved users for the UI
        $pendingUsers = User::where('organization_id', $adminUser->organization_id)
            ->where('pending_approval', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at->toISOString(),
                ];
            });

        $approvedUsers = User::where('organization_id', $adminUser->organization_id)
            ->where('pending_approval', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->isAdmin(),
                    'approved_at' => $user->approved_at?->toISOString(),
                ];
            });

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'pendingUsers' => $pendingUsers,
            'approvedUsers' => $approvedUsers,
            'organization' => [
                'id' => $adminUser->organization_id,
                'name' => $adminUser->organization->name,
            ],
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Login as another user within the same organization (admin only).
     */
    public function loginAsUser(Request $request, User $user)
    {
        $adminUser = auth()->user();

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Prevent self-impersonation
        if ($user->id === $adminUser->id) {
            return redirect('/dashboard')->with('message', 'Cannot login as yourself');
        }

        // Ensure the target user is in the same organization
        if ($user->organization_id !== $adminUser->organization_id) {
            abort(403, 'Cannot login as users from other organizations');
        }

        // Log the action for audit purposes
        \Log::info('Admin login as user', [
            'admin_id' => $adminUser->id,
            'admin_email' => $adminUser->email,
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
            'organization_id' => $adminUser->organization_id,
            'reason' => $request->input('reason', 'No reason provided'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Store the original admin ID in session for returning later
        session(['original_admin_id' => $adminUser->id]);

        // Login as the target user
        Auth::login($user);

        return redirect('/dashboard')->with('message', 'You are now logged in as ' . $user->name);
    }

    /**
     * Return to original admin account.
     */
    public function returnToAdmin()
    {
        $originalAdminId = session('original_admin_id');

        if (!$originalAdminId) {
            abort(403, 'No original admin session found.');
        }

        $originalAdmin = User::find($originalAdminId);

        if (!$originalAdmin || !$originalAdmin->isAdmin()) {
            abort(403, 'Original admin account not found or no longer valid.');
        }

        // Clear the session
        session()->forget('original_admin_id');

        // Log the return action
        \Log::info('Admin returned to original account', [
            'admin_id' => $originalAdmin->id,
            'admin_email' => $originalAdmin->email,
            'impersonated_user_id' => auth()->id(),
            'impersonated_user_email' => auth()->user()->email,
            'organization_id' => $originalAdmin->organization_id,
        ]);

        // Login back as admin
        Auth::login($originalAdmin);

        return redirect('/admin')->with('message', 'Returned to admin account');
    }

    /**
     * Approve a pending user.
     */
    public function approve(Request $request, User $user)
    {
        $adminUser = auth()->user();

        // Ensure admin can only approve users from their organization
        if ($user->organization_id !== $adminUser->organization_id) {
            abort(403, 'Cannot approve users from other organizations');
        }

        // Ensure user is actually pending approval
        if (!$user->pending_approval) {
            return redirect()->back()->withErrors(['error' => 'User is already approved']);
        }

        // Approve the user
        $user->update([
            'pending_approval' => false,
            'approved_at' => now(),
            'approved_by' => $adminUser->id,
        ]);

        // Add user to default group
        $defaultGroup = $user->organization->defaultGroup();
        if ($defaultGroup && !$user->belongsToGroup($defaultGroup->id)) {
            $user->joinGroup($defaultGroup);
        }

        // Send approval notification
        try {
            $user->notify(new \App\Notifications\UserApprovedNotification($adminUser));
        } catch (\Exception $e) {
            \Log::warning('Failed to send approval notification', [
                'user_id' => $user->id,
                'admin_id' => $adminUser->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Log the approval action
        \Log::info('Admin approved user', [
            'admin_id' => $adminUser->id,
            'user_id' => $user->id,
            'organization_id' => $adminUser->organization_id,
        ]);

        return redirect()->back()->with('message', "User {$user->name} has been approved successfully.");
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(Request $request, User $user)
    {
        $adminUser = auth()->user();

        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        // Ensure admin can only assign roles to users from their organization
        if ($user->organization_id !== $adminUser->organization_id) {
            abort(403, 'Cannot assign roles to users from other organizations');
        }

        $role = \App\Models\Role::findOrFail($request->role_id);

        // Ensure role belongs to the same organization
        if ($role->organization_id !== $adminUser->organization_id) {
            abort(403, 'Cannot assign roles from other organizations');
        }

        // Assign the role
        $user->assignRole($role);

        // Log the action
        \Log::info('Admin assigned role to user', [
            'admin_id' => $adminUser->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'role_name' => $role->name,
            'organization_id' => $adminUser->organization_id,
        ]);

        return redirect()->back()->with('message', "Role {$role->display_name} assigned to {$user->name} successfully.");
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(Request $request, User $user)
    {
        $adminUser = auth()->user();

        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        // Ensure admin can only remove roles from users in their organization
        if ($user->organization_id !== $adminUser->organization_id) {
            abort(403, 'Cannot remove roles from users in other organizations');
        }

        $role = \App\Models\Role::findOrFail($request->role_id);

        // Ensure role belongs to the same organization
        if ($role->organization_id !== $adminUser->organization_id) {
            return redirect()->back()->withErrors(['role_id' => 'Role must belong to your organization']);
        }

        // Remove the role
        $user->removeRole($role);

        // Log the action
        \Log::info('Admin removed role from user', [
            'admin_id' => $adminUser->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'role_name' => $role->name,
            'organization_id' => $adminUser->organization_id,
        ]);

        return redirect()->back()->with('message', "Role {$role->display_name} removed from {$user->name} successfully.");
    }

    /**
     * Remove a user from a group.
     */
    public function removeFromGroup(Request $request, User $user)
    {
        $adminUser = auth()->user();

        $request->validate([
            'group_id' => 'required|exists:groups,id',
        ]);

        // Ensure admin can only manage users from their organization
        if ($user->organization_id !== $adminUser->organization_id) {
            abort(403, 'Cannot manage users from other organizations');
        }

        $group = \App\Models\Group::findOrFail($request->group_id);

        // Ensure group belongs to the same organization
        if ($group->organization_id !== $adminUser->organization_id) {
            return redirect()->back()->withErrors(['group_id' => 'Group must belong to your organization']);
        }

        // Prevent removing users from default group
        if ($group->is_default) {
            return redirect()->back()->withErrors(['error' => 'Cannot remove users from the default group']);
        }

        // Remove user from group
        $user->leaveGroup($group);

        // Log the action
        \Log::info('Admin removed user from group', [
            'admin_id' => $adminUser->id,
            'user_id' => $user->id,
            'group_id' => $group->id,
            'group_name' => $group->name,
            'organization_id' => $adminUser->organization_id,
        ]);

        return redirect()->back()->with('message', "User {$user->name} removed from group {$group->name} successfully.");
    }

    /**
     * Add a user to a group.
     */
    public function addToGroup(Request $request, User $user)
    {
        $adminUser = auth()->user();

        $request->validate([
            'group_id' => 'required|exists:groups,id',
        ]);

        // Ensure admin can only manage users from their organization
        if ($user->organization_id !== $adminUser->organization_id) {
            abort(403, 'Cannot manage users from other organizations');
        }

        $group = \App\Models\Group::findOrFail($request->group_id);

        // Ensure group belongs to the same organization
        if ($group->organization_id !== $adminUser->organization_id) {
            return redirect()->back()->withErrors(['group_id' => 'Group must belong to your organization']);
        }

        // Check if user is already in the group
        if ($user->belongsToGroup($group->id)) {
            return redirect()->back()->withErrors(['error' => 'User is already a member of this group']);
        }

        // Add user to group
        $user->joinGroup($group);

        // Log the action
        \Log::info('Admin added user to group', [
            'admin_id' => $adminUser->id,
            'user_id' => $user->id,
            'group_id' => $group->id,
            'group_name' => $group->name,
            'organization_id' => $adminUser->organization_id,
        ]);

        return redirect()->back()->with('message', "User {$user->name} added to group {$group->name} successfully.");
    }
}
