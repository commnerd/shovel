<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{User, UserInvitation, Organization, Role};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, DB, Validator};
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class SetPasswordController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invitation = UserInvitation::where('token', $token)
            ->with(['organization'])
            ->first();

        if (!$invitation) {
            return redirect()->route('login')
                ->withErrors(['token' => 'Invalid invitation link.']);
        }

        if ($invitation->isExpired()) {
            return redirect()->route('login')
                ->withErrors(['token' => 'This invitation has expired.']);
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login')
                ->with('message', 'This invitation has already been used.');
        }

        return Inertia::render('auth/SetPassword', [
            'token' => $token,
            'email' => $invitation->email,
            'organization' => $invitation->organization ? [
                'name' => $invitation->organization->name,
            ] : null,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $invitation = UserInvitation::where('token', $token)->first();

        if (!$invitation) {
            return back()->withErrors(['token' => 'Invalid invitation link.']);
        }

        if ($invitation->isExpired()) {
            return back()->withErrors(['token' => 'This invitation has expired.']);
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors(['token' => 'This invitation has already been used.']);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($invitation, $validated) {
                // Determine if this is the first user (Super Admin logic)
                $isFirstUser = User::count() === 0;

                // Create the user
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($validated['password']),
                    'organization_id' => $invitation->organization_id,
                    'email_verified_at' => now(),
                    'approved_at' => now(), // Auto-approve invited users
                    'is_super_admin' => $isFirstUser,
                ]);

                // Assign to organization and default group if organization exists
                if ($invitation->organization_id) {
                    $organization = Organization::find($invitation->organization_id);
                    if ($organization) {
                        $defaultGroup = $organization->defaultGroup();
                        if ($defaultGroup) {
                            $user->groups()->attach($defaultGroup->id);
                        }

                        // Assign default user role
                        $userRole = $organization->roles()
                            ->where('name', 'User')
                            ->first();
                        if ($userRole) {
                            $user->roles()->attach($userRole->id);
                        }
                    }
                } else {
                    // User invited to no specific organization - assign to default "None" organization
                    $defaultOrganization = Organization::getDefault();
                    if ($defaultOrganization) {
                        $user->update(['organization_id' => $defaultOrganization->id]);

                        $defaultGroup = $defaultOrganization->defaultGroup();
                        if ($defaultGroup) {
                            $user->groups()->attach($defaultGroup->id);
                        }

                        // Assign default user role
                        $userRole = $defaultOrganization->roles()
                            ->where('name', 'User')
                            ->first();
                        if ($userRole) {
                            $user->roles()->attach($userRole->id);
                        }
                    }
                }

                // Mark invitation as accepted
                $invitation->accept();
            });

            return redirect()->route('login')
                ->with('success', 'Account created successfully! You can now log in.');

        } catch (\Exception $e) {
            return back()->withErrors(['password' => 'Failed to create account. Please try again.']);
        }
    }
}
