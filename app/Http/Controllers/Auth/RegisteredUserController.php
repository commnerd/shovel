<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'organization_email' => 'boolean',
        ]);

        $isOrganizationEmail = $request->boolean('organization_email');
        $emailDomain = substr(strrchr($request->email, '@'), 1);

        // Check if an organization with this domain already exists
        $existingOrg = Organization::where('domain', $emailDomain)->first();

        if ($isOrganizationEmail) {
            if ($existingOrg) {
                // User wants to join existing organization
                return $this->handleExistingOrganizationRegistration($request, $existingOrg);
            } else {
                // User wants to create new organization
                return $this->redirectToOrganizationCreation($request);
            }
        } else {
            // User doesn't want organization email
            if ($existingOrg) {
                // Confirm they don't want to join the existing organization
                return $this->confirmNonOrganizationRegistration($request, $existingOrg);
            } else {
                // Assign to default 'None' organization
                return $this->createUserInDefaultOrganization($request);
            }
        }
    }

    /**
     * Handle registration for existing organization.
     */
    private function handleExistingOrganizationRegistration(Request $request, Organization $organization): RedirectResponse
    {
        // Check if this is the first user in the system (no users exist at all)
        $isFirstUser = User::count() === 0;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'organization_id' => $organization->id,
            'pending_approval' => !$isFirstUser, // First user doesn't need approval
            'approved_at' => $isFirstUser ? now() : null,
            'is_super_admin' => $isFirstUser,
        ]);

        // Assign default user role (even for pending users)
        $userRole = $organization->getUserRole();
        if ($userRole) {
            $user->assignRole($userRole);
        }

        event(new Registered($user));

        // Send notification to all organization admins
        $admins = $organization->users()->whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\NewOrganizationMemberNotification($user, $organization));
        }

        // Do not log in pending users - redirect to login with message
        return redirect()->route('login')->with([
            'status' => 'registration-pending',
            'message' => 'Your account has been created and is pending approval from your organization administrator. You will receive an email when approved.',
        ]);
    }

    /**
     * Redirect to organization creation page.
     */
    private function redirectToOrganizationCreation(Request $request): RedirectResponse
    {
        // Store registration data in session
        session([
            'registration_data' => [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ],
        ]);

        return redirect()->route('organization.create');
    }

    /**
     * Confirm user doesn't want to join existing organization.
     */
    private function confirmNonOrganizationRegistration(Request $request, Organization $organization): RedirectResponse
    {
        // Store data and redirect to confirmation page
        session([
            'registration_data' => [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ],
            'existing_organization' => $organization->toArray(),
        ]);

        return redirect()->route('registration.confirm-organization');
    }

    /**
     * Create user in default 'None' organization.
     */
    private function createUserInDefaultOrganization(Request $request): RedirectResponse
    {
        $defaultOrg = Organization::getDefault();

        // If no default organization exists, create it
        if (!$defaultOrg) {
            $defaultOrg = Organization::create([
                'name' => 'None',
                'domain' => null,
                'address' => null,
                'creator_id' => null,
                'is_default' => true,
            ]);

            // Create the default 'Everyone' group
            Group::create([
                'name' => 'Everyone',
                'description' => 'Default group for individual users',
                'organization_id' => $defaultOrg->id,
                'is_default' => true,
            ]);

            // Create default roles
            Role::create([
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Organization administrator with full management rights',
                'organization_id' => $defaultOrg->id,
                'permissions' => Role::getAdminPermissions(),
            ]);

            Role::create([
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Standard organization member',
                'organization_id' => $defaultOrg->id,
                'permissions' => Role::getUserPermissions(),
            ]);
        }

        $defaultGroup = $defaultOrg->defaultGroup();

        // Ensure default group exists
        if (!$defaultGroup) {
            throw new \Exception('Default organization exists but has no default group.');
        }

        // Check if this is the first user in the system (no users exist at all)
        $isFirstUser = User::count() === 0;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'organization_id' => $defaultOrg->id,
            'pending_approval' => false,
            'approved_at' => now(),
            'is_super_admin' => $isFirstUser,
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

        return to_route('dashboard');
    }
}
