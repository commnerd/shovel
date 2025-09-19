<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewOrganizationMemberNotification;

class OrganizationEmailWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    public function test_organization_email_checked_with_unique_domain_shows_organization_form()
    {
        // User checks 'Organization Email' with a unique domain
        $response = $this->post('/register', [
            'name' => 'Company Founder',
            'email' => 'founder@newcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true, // CHECKED
        ]);

        // Should be redirected to organization creation form
        $response->assertRedirect('/organization/create');

        // Verify session data is stored
        $this->assertEquals('Company Founder', session('registration_data.name'));
        $this->assertEquals('founder@newcompany.com', session('registration_data.email'));
        $this->assertNotNull(session('registration_data.password'));
    }

    public function test_organization_creation_form_submission_creates_organization_and_redirects_to_dashboard()
    {
        // Set up session data (as would be done by registration)
        session([
            'registration_data' => [
                'name' => 'Company Founder',
                'email' => 'founder@newcompany.com',
                'password' => \Hash::make('password'),
            ]
        ]);

        // Submit organization creation form
        $response = $this->post('/organization/create', [
            'organization_name' => 'New Company Inc',
            'organization_address' => '123 Business Park Drive',
        ]);

        // Should redirect to dashboard
        $response->assertRedirect('/dashboard');

        // Verify organization was created
        $organization = Organization::where('domain', 'newcompany.com')->first();
        $this->assertNotNull($organization);
        $this->assertEquals('New Company Inc', $organization->name);
        $this->assertEquals('123 Business Park Drive', $organization->address);

        // Verify user was created as organization creator
        $user = User::where('email', 'founder@newcompany.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Company Founder', $user->name);
        $this->assertEquals($organization->id, $user->organization_id);
        $this->assertEquals($user->id, $organization->creator_id);

        // Verify user has admin role
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasPermission('manage_users'));

        // Verify default group and roles were created
        $defaultGroup = $organization->defaultGroup();
        $this->assertNotNull($defaultGroup);
        $this->assertTrue($user->belongsToGroup($defaultGroup->id));

        $adminRole = $organization->getAdminRole();
        $userRole = $organization->getUserRole();
        $this->assertNotNull($adminRole);
        $this->assertNotNull($userRole);
    }

    public function test_organization_email_checked_with_existing_domain_delivers_pending_notification()
    {
        Notification::fake();

        // Create existing organization with creator
        $existingCreator = User::factory()->create([
            'email' => 'admin@existingcompany.com',
        ]);

        $existingOrg = Organization::factory()->create([
            'domain' => 'existingcompany.com',
            'creator_id' => $existingCreator->id,
        ]);

        $roles = $existingOrg->createDefaultRoles();
        $defaultGroup = $existingOrg->createDefaultGroup();

        $existingCreator->update(['organization_id' => $existingOrg->id, 'pending_approval' => false]);
        $existingCreator->assignRole($roles['admin']);
        $existingCreator->joinGroup($defaultGroup);

        // User checks 'Organization Email' with existing domain
        $response = $this->post('/register', [
            'name' => 'New Employee',
            'email' => 'employee@existingcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true, // CHECKED
        ]);

        // Should redirect to login with pending status
        $response->assertRedirect('/login');
        $response->assertSessionHas('status', 'registration-pending');
        $response->assertSessionHas('message', 'Your account has been created and is pending approval from your organization administrator. You will receive an email when approved.');

        // Verify user was created as pending
        $newUser = User::where('email', 'employee@existingcompany.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->pending_approval);
        $this->assertEquals($existingOrg->id, $newUser->organization_id);

        // Verify notification was sent to organization creator
        Notification::assertSentTo(
            $existingCreator,
            NewOrganizationMemberNotification::class,
            function ($notification) use ($newUser, $existingOrg) {
                return $notification->newUser->id === $newUser->id
                    && $notification->organization->id === $existingOrg->id;
            }
        );
    }

    public function test_complete_new_organization_workflow()
    {
        // Step 1: User registers with organization email (unique domain)
        $response = $this->post('/register', [
            'name' => 'Tech Founder',
            'email' => 'founder@techstartup.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        $response->assertRedirect('/organization/create');

        // Step 2: User fills out organization creation form
        $response = $this->get('/organization/create');
        $response->assertOk();

        // Step 3: User submits organization creation form
        $response = $this->post('/organization/create', [
            'organization_name' => 'Tech Startup LLC',
            'organization_address' => '456 Innovation Drive, Tech City, TC 12345',
        ]);

        $response->assertRedirect('/dashboard');

        // Step 4: Verify complete organization structure
        $organization = Organization::where('domain', 'techstartup.com')->first();
        $user = User::where('email', 'founder@techstartup.com')->first();

        // Organization verification
        $this->assertEquals('Tech Startup LLC', $organization->name);
        $this->assertEquals('456 Innovation Drive, Tech City, TC 12345', $organization->address);
        $this->assertEquals('techstartup.com', $organization->domain);

        // User verification
        $this->assertEquals('Tech Founder', $user->name);
        $this->assertFalse($user->pending_approval); // Creator is immediately approved
        $this->assertNotNull($user->approved_at);

        // Permissions verification
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('user'));
        $this->assertTrue($user->hasPermission('manage_users'));
        $this->assertTrue($user->hasPermission('create_projects'));

        // Group membership verification
        $defaultGroup = $organization->defaultGroup();
        $this->assertTrue($user->belongsToGroup($defaultGroup->id));

        // Step 5: Verify user can access admin panel
        $response = $this->actingAs($user)->get('/admin/users');
        $response->assertOk();
    }

    public function test_complete_existing_organization_workflow()
    {
        Notification::fake();

        // Step 1: Set up existing organization
        $admin = User::factory()->create([
            'email' => 'admin@bigcorp.com',
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        $existingOrg = Organization::factory()->create([
            'domain' => 'bigcorp.com',
            'name' => 'Big Corporation',
            'creator_id' => $admin->id,
        ]);

        $roles = $existingOrg->createDefaultRoles();
        $defaultGroup = $existingOrg->createDefaultGroup();

        $admin->update(['organization_id' => $existingOrg->id]);
        $admin->assignRole($roles['admin']);
        $admin->joinGroup($defaultGroup);

        // Step 2: New user registers with organization email (existing domain)
        $response = $this->post('/register', [
            'name' => 'New Employee',
            'email' => 'newbie@bigcorp.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true, // CHECKED
        ]);

        // Step 3: User should be immediately registered as pending
        $response->assertRedirect('/login');
        $response->assertSessionHas('status', 'registration-pending');

        // Step 4: Verify user status and notification
        $newUser = User::where('email', 'newbie@bigcorp.com')->first();
        $this->assertTrue($newUser->pending_approval);
        $this->assertEquals($existingOrg->id, $newUser->organization_id);

        Notification::assertSentTo($admin, NewOrganizationMemberNotification::class);

        // Step 5: Admin approves user
        $response = $this->actingAs($admin)
            ->post("/admin/users/{$newUser->id}/approve");

        $response->assertRedirect();

        // Step 6: Verify user is now approved
        $newUser->refresh();
        $this->assertFalse($newUser->pending_approval);
        $this->assertNotNull($newUser->approved_at);
        $this->assertEquals($admin->id, $newUser->approved_by);
    }

    public function test_organization_email_workflow_comparison()
    {
        // This test demonstrates the difference between checked and unchecked organization email

        // Scenario A: Organization Email UNCHECKED with existing domain
        $existingOrg = Organization::factory()->create([
            'domain' => 'company.com',
            'name' => 'Existing Company',
        ]);

        $response = $this->post('/register', [
            'name' => 'Cautious User',
            'email' => 'user@company.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false, // UNCHECKED
        ]);

        // Should go to confirmation page
        $response->assertRedirect('/registration/confirm-organization');

        // Scenario B: Organization Email CHECKED with same domain
        $response = $this->post('/register', [
            'name' => 'Confident User',
            'email' => 'confident@company.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true, // CHECKED
        ]);

        // Should go directly to login with pending status
        $response->assertRedirect('/login');
        $response->assertSessionHas('status', 'registration-pending');

        // Clear session to avoid conflicts
        session()->flush();

        // Scenario C: Organization Email CHECKED with unique domain
        $response = $this->post('/register', [
            'name' => 'Entrepreneur',
            'email' => 'founder@uniquestartup123.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true, // CHECKED
        ]);

        // Should go to organization creation form
        $response->assertRedirect('/organization/create');
    }

    public function test_organization_creation_form_requires_session_data()
    {
        // Try to access organization creation without session data
        $response = $this->get('/organization/create');
        $response->assertRedirect('/register');

        // Try to submit organization creation without session data
        $response = $this->post('/organization/create', [
            'organization_name' => 'Test Company',
            'organization_address' => 'Test Address',
        ]);

        $response->assertRedirect('/register');
    }

    public function test_organization_creation_form_validation()
    {
        // Set up session data
        session([
            'registration_data' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => \Hash::make('password'),
            ]
        ]);

        // Test missing organization name
        $response = $this->post('/organization/create', [
            'organization_address' => 'Valid Address',
        ]);

        $response->assertSessionHasErrors(['organization_name']);

        // Test missing organization address
        $response = $this->post('/organization/create', [
            'organization_name' => 'Valid Name',
        ]);

        $response->assertSessionHasErrors(['organization_address']);

        // Test empty values
        $response = $this->post('/organization/create', [
            'organization_name' => '',
            'organization_address' => '',
        ]);

        $response->assertSessionHasErrors(['organization_name', 'organization_address']);
    }
}
