<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Display organization users.
     */
    public function index(): Response
    {
        $organization = auth()->user()->organization;

        $users = $organization->users()
            ->with(['roles', 'groups'])
            ->latest()
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'pending_approval' => $user->pending_approval,
                    'approved_at' => $user->approved_at?->format('Y-m-d H:i'),
                    'created_at' => $user->created_at->toISOString(),
                    'roles' => $user->roles->pluck('display_name')->toArray(),
                    'groups' => $user->groups->pluck('name')->toArray(),
                    'is_admin' => $user->isAdmin(),
                ];
            });

        $pendingUsers = $users->where('pending_approval', true);
        $approvedUsers = $users->where('pending_approval', false);

        return Inertia::render('Admin/Users/Index', [
            'pendingUsers' => $pendingUsers->values(),
            'approvedUsers' => $approvedUsers->values(),
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
        ]);
    }

    /**
     * Approve a pending user.
     */
    public function approve(User $user): RedirectResponse
    {
        // Verify user belongs to same organization
        if ($user->organization_id !== auth()->user()->organization_id) {
            abort(403, 'You can only manage users in your organization.');
        }

        if (!$user->pending_approval) {
            return back()->withErrors(['error' => 'User is already approved.']);
        }

        $user->update([
            'pending_approval' => false,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        // Add user to default group if not already added
        $defaultGroup = $user->organization->defaultGroup();
        if ($defaultGroup && !$user->belongsToGroup($defaultGroup->id)) {
            $user->joinGroup($defaultGroup);
        }

        // Send approval notification to the user
        $user->notify(new \App\Notifications\UserApprovedNotification($user->organization, auth()->user()));

        return back()->with('message', "User {$user->name} has been approved successfully.");
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(Request $request, User $user): RedirectResponse
    {
        // Verify user belongs to same organization
        if ($user->organization_id !== auth()->user()->organization_id) {
            abort(403, 'You can only manage users in your organization.');
        }

        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::find($request->role_id);

        // Verify role belongs to same organization
        if ($role->organization_id !== auth()->user()->organization_id) {
            abort(403, 'Invalid role for this organization.');
        }

        $user->assignRole($role, auth()->user());

        return back()->with('message', "Role '{$role->display_name}' assigned to {$user->name}.");
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(Request $request, User $user): RedirectResponse
    {
        // Verify user belongs to same organization
        if ($user->organization_id !== auth()->user()->organization_id) {
            abort(403, 'You can only manage users in your organization.');
        }

        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::find($request->role_id);

        // Verify role belongs to same organization
        if ($role->organization_id !== auth()->user()->organization_id) {
            abort(403, 'Invalid role for this organization.');
        }

        $user->removeRole($role);

        return back()->with('message', "Role '{$role->display_name}' removed from {$user->name}.");
    }

    /**
     * Add user to a group.
     */
    public function addToGroup(Request $request, User $user): RedirectResponse
    {
        // Verify user belongs to same organization
        if ($user->organization_id !== auth()->user()->organization_id) {
            abort(403, 'You can only manage users in your organization.');
        }

        $request->validate([
            'group_id' => 'required|exists:groups,id',
        ]);

        $group = Group::find($request->group_id);

        // Verify group belongs to same organization
        if ($group->organization_id !== auth()->user()->organization_id) {
            abort(403, 'Invalid group for this organization.');
        }

        $user->joinGroup($group);

        return back()->with('message', "User {$user->name} added to group '{$group->name}'.");
    }

    /**
     * Remove user from a group.
     */
    public function removeFromGroup(Request $request, User $user): RedirectResponse
    {
        // Verify user belongs to same organization
        if ($user->organization_id !== auth()->user()->organization_id) {
            abort(403, 'You can only manage users in your organization.');
        }

        $request->validate([
            'group_id' => 'required|exists:groups,id',
        ]);

        $group = Group::find($request->group_id);

        // Verify group belongs to same organization
        if ($group->organization_id !== auth()->user()->organization_id) {
            abort(403, 'Invalid group for this organization.');
        }

        $success = $user->leaveGroup($group);

        if ($success) {
            return back()->with('message', "User {$user->name} removed from group '{$group->name}'.");
        } else {
            return back()->withErrors(['error' => 'Cannot remove user from default group.']);
        }
    }
}
