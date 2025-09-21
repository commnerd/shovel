<?php

namespace App\Http\Controllers;

use App\Models\{UserInvitation, Organization, User};
use App\Notifications\UserInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserInvitationController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Super Admin can see all invitations
        if ($user->isSuperAdmin()) {
            $invitations = UserInvitation::with(['organization', 'invitedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }
        // Admin can see invitations for their organization
        elseif ($user->isAdmin()) {
            $invitations = UserInvitation::with(['organization', 'invitedBy'])
                ->where('organization_id', $user->organization_id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }
        else {
            abort(403, 'Unauthorized access.');
        }

        return Inertia::render('Admin/UserInvitations/Index', [
            'invitations' => $invitations->through(fn ($invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'organization' => $invitation->organization ? [
                    'id' => $invitation->organization->id,
                    'name' => $invitation->organization->name,
                ] : null,
                'invited_by' => [
                    'id' => $invitation->invitedBy->id,
                    'name' => $invitation->invitedBy->name,
                ],
                'status' => $invitation->isAccepted() ? 'accepted' :
                           ($invitation->isExpired() ? 'expired' : 'pending'),
                'created_at' => $invitation->created_at->format('M j, Y g:i A'),
                'expires_at' => $invitation->expires_at->format('M j, Y g:i A'),
                'accepted_at' => $invitation->accepted_at?->format('M j, Y g:i A'),
            ]),
            'can_invite_users' => $user->isSuperAdmin() || $user->isAdmin(),
            'is_super_admin' => $user->isSuperAdmin(),
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $organizations = [];
        if ($user->isSuperAdmin()) {
            $organizations = Organization::orderBy('name')->get(['id', 'name']);
        }

        return Inertia::render('Admin/UserInvitations/Create', [
            'organizations' => $organizations,
            'is_super_admin' => $user->isSuperAdmin(),
            'user_organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $rules = [
            'email' => [
                'required',
                'email',
                'max:255',
                // Prevent inviting existing users
                Rule::unique('users', 'email'),
                // Prevent duplicate pending invitations
                Rule::unique('user_invitations', 'email')->where(function ($query) {
                    return $query->whereNull('accepted_at')
                                 ->where('expires_at', '>', now());
                }),
            ],
            'organization_id' => 'nullable|exists:organizations,id',
        ];

        // Admin validation: can only invite to their organization or no organization
        if (!$user->isSuperAdmin()) {
            $rules['organization_id'] = [
                'nullable',
                Rule::in([null, $user->organization_id])
            ];

            // Admin cannot invite emails that don't belong to their organization domain
            if ($user->organization && !$user->organization->is_default) {
                $organizationDomain = explode('@', $user->email)[1] ?? null;
                if ($organizationDomain) {
                    $rules['email'][] = function ($attribute, $value, $fail) use ($organizationDomain) {
                        $emailDomain = explode('@', $value)[1] ?? null;
                        if ($emailDomain !== $organizationDomain) {
                            $fail('You can only invite users with email addresses from your organization domain.');
                        }
                    };
                }
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($validated, $user) {
                $invitation = UserInvitation::createInvitation(
                    $validated['email'],
                    $validated['organization_id'] ?? null,
                    $user->id
                );

                // Send invitation email
                \Notification::route('mail', $validated['email'])
                    ->notify(new UserInvitationNotification($invitation));
            });

            return redirect()->route('admin.invitations.index')
                ->with('success', 'User invitation sent successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'Failed to send invitation. Please try again.'])->withInput();
        }
    }

    public function destroy(UserInvitation $invitation)
    {
        $user = auth()->user();

        // Super Admin can delete any invitation
        if ($user->isSuperAdmin()) {
            $invitation->delete();
        }
        // Admin can only delete invitations for their organization
        elseif ($user->isAdmin() && $invitation->organization_id === $user->organization_id) {
            $invitation->delete();
        }
        else {
            abort(403, 'Unauthorized access.');
        }

        return back()->with('success', 'Invitation deleted successfully!');
    }

    public function resend(UserInvitation $invitation)
    {
        $user = auth()->user();

        // Check permissions
        if (!$user->isSuperAdmin() &&
            (!$user->isAdmin() || $invitation->organization_id !== $user->organization_id)) {
            abort(403, 'Unauthorized access.');
        }

        // Check if invitation is still valid to resend
        if ($invitation->isAccepted()) {
            return back()->withErrors(['message' => 'Cannot resend an accepted invitation.']);
        }

        if ($invitation->isExpired()) {
            return back()->withErrors(['message' => 'Cannot resend an expired invitation. Please create a new one.']);
        }

        try {
            // Send invitation email again
            \Notification::route('mail', $invitation->email)
                ->notify(new UserInvitationNotification($invitation));

            return back()->with('success', 'Invitation resent successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Failed to resend invitation. Please try again.']);
        }
    }
}
