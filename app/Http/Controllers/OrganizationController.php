<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Show the organization creation form.
     */
    public function create(): Response|RedirectResponse
    {
        // Check if we have registration data in session
        $registrationData = session('registration_data');

        if (! $registrationData) {
            return redirect()->route('register');
        }

        return Inertia::render('auth/CreateOrganization', [
            'email' => $registrationData['email'],
        ]);
    }

    /**
     * Store a new organization and user.
     */
    public function store(Request $request): RedirectResponse
    {
        $registrationData = session('registration_data');

        if (! $registrationData) {
            return redirect()->route('register');
        }

        $request->validate([
            'organization_name' => 'required|string|max:255',
            'organization_address' => 'required|string|max:1000',
        ]);

        $emailDomain = substr(strrchr($registrationData['email'], '@'), 1);

        // Create the organization
        $organization = Organization::create([
            'name' => $request->organization_name,
            'domain' => $emailDomain,
            'address' => $request->organization_address,
            'is_default' => false,
        ]);

        // Create the user as the organization creator
        $user = User::create([
            'name' => $registrationData['name'],
            'email' => $registrationData['email'],
            'password' => $registrationData['password'], // Already hashed
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Update organization creator
        $organization->update(['creator_id' => $user->id]);

        // Create default 'Everyone' group
        $defaultGroup = $organization->createDefaultGroup();

        // Create default roles
        $roles = $organization->createDefaultRoles();

        // Add user to default group
        $user->groups()->attach($defaultGroup->id, ['joined_at' => now()]);

        // Assign admin role to organization creator
        $user->assignRole($roles['admin']);

        // Also assign user role (users can have multiple roles)
        $user->assignRole($roles['user']);

        // Clear session data
        session()->forget(['registration_data']);

        event(new Registered($user));
        Auth::login($user);

        return to_route('dashboard')->with([
            'message' => "Welcome! Your organization '{$organization->name}' has been created successfully.",
        ]);
    }

    /**
     * Show confirmation page for non-organization registration.
     */
    public function confirmRegistration(): Response|RedirectResponse
    {
        $registrationData = session('registration_data');
        $existingOrg = session('existing_organization');

        if (! $registrationData || ! $existingOrg) {
            return redirect()->route('register');
        }

        return Inertia::render('auth/ConfirmOrganization', [
            'email' => $registrationData['email'],
            'organization' => $existingOrg,
        ]);
    }

    /**
     * Handle confirmed non-organization registration.
     */
    public function confirmStore(Request $request): RedirectResponse
    {
        $registrationData = session('registration_data');

        if (! $registrationData) {
            return redirect()->route('register');
        }

        $request->validate([
            'join_organization' => 'required|boolean',
        ]);

        if ($request->boolean('join_organization')) {
            // User decided to join the organization after all
            $existingOrg = Organization::where('domain', substr(strrchr($registrationData['email'], '@'), 1))->first();

            if ($existingOrg) {
                $user = User::create([
                    'name' => $registrationData['name'],
                    'email' => $registrationData['email'],
                    'password' => $registrationData['password'], // Already hashed
                    'organization_id' => $existingOrg->id,
                    'pending_approval' => true,
                ]);

                // Assign default user role
                $userRole = $existingOrg->getUserRole();
                if ($userRole) {
                    $user->assignRole($userRole);
                }

                event(new Registered($user));

                // Send notification to all organization admins
                $admins = $existingOrg->users()->whereHas('roles', function ($query) {
                    $query->where('name', 'admin');
                })->get();

                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\NewOrganizationMemberNotification($user, $existingOrg));
                }

                // Clear session data
                session()->forget(['registration_data', 'existing_organization']);

                // Do not log in pending users - redirect to login with message
                return redirect()->route('login')->with([
                    'status' => 'registration-pending',
                    'message' => 'Your account has been created and is pending approval from your organization administrator. You will receive an email when approved.',
                ]);
            }
        }

        // User confirmed they don't want to join organization
        $defaultOrg = Organization::getDefault();
        $defaultGroup = $defaultOrg->defaultGroup();

        $user = User::create([
            'name' => $registrationData['name'],
            'email' => $registrationData['email'],
            'password' => $registrationData['password'], // Already hashed
            'organization_id' => $defaultOrg->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Add user to default group
        $user->groups()->attach($defaultGroup->id, ['joined_at' => now()]);

        // Assign default user role
        $userRole = $defaultOrg->getUserRole();
        if ($userRole) {
            $user->assignRole($userRole);
        }

        event(new Registered($user));
        Auth::login($user);

        // Clear session data
        session()->forget(['registration_data', 'existing_organization']);

        return to_route('dashboard');
    }
}
