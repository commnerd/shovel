<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewOrganizationMemberNotification;

class OrganizationEmailCheckedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    public function test_organization_email_checked_with_unique_domain_redirects_to_organization_form()
    {
        // When 'Organization Email' is CHECKED and domain is UNIQUE
        $response = $this->post('/register', [
            'name' => 'New Founder',
            'email' => 'founder@brandnewcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true, // CHECKED
        ]);

        // Should redirect to organization creation form
        $response->assertRedirect('/organization/create');

        // Verify session data is stored for organization creation
        $this->assertEquals('New Founder', session('registration_data.name'));
        $this->assertEquals('founder@brandnewcompany.com', session('registration_data.email'));
        $this->assertNotNull(session('registration_data.password'));
    }

    public function test_organization_email_checked_with_existing_domain_creates_pending_user()
    {
        Notification::fake();

        // Create existing organization
        $creator = User::factory()->create([
            'email' => 'creator@existingcorp.com',
            'pending_approval' => false,
        ]);

        $existingOrg = Organization::factory()->create([
            'domain' => 'existingcorp.com',
            'name' => 'Existing Corporation',
            'creator_id' => $creator->id,
        ]);

        $roles = $existingOrg->createDefaultRoles();
        $creator->update(['organization_id' => $existingOrg->id]);
        $creator->assignRole($roles['admin']);

        // When 'Organization Email' is CHECKED and domain ALREADY EXISTS
        $response = $this->post('/register', [
            'name' => 'New Employee',
            'email' => 'employee@existingcorp.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true, // CHECKED
        ]);

        // Should redirect to login with pending notification
        $response->assertRedirect('/login');
        $response->assertSessionHas('status', 'registration-pending');
        $response->assertSessionHas('message', 'Your account has been created and is pending approval from your organization administrator. You will receive an email when approved.');

        // Verify user was created as pending
        $newUser = User::where('email', 'employee@existingcorp.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->pending_approval);
        $this->assertEquals($existingOrg->id, $newUser->organization_id);

        // Verify notification was sent to organization creator
        Notification::assertSentTo(
            $creator,
            NewOrganizationMemberNotification::class
        );
    }

    public function test_organization_creation_form_submission_creates_organization_and_goes_to_dashboard()
    {
        // Simulate the organization creation workflow
        session([
            'registration_data' => [
                'name' => 'Startup Founder',
                'email' => 'founder@mystartup.com',
                'password' => \Hash::make('password'),
            ]
        ]);

        // Submit organization creation form
        $response = $this->post('/organization/create', [
            'organization_name' => 'My Startup Inc',
            'organization_address' => '789 Startup Avenue, Innovation City',
        ]);

        // Should redirect to dashboard (successful creation)
        $response->assertRedirect('/dashboard');

        // Verify organization was created
        $organization = Organization::where('domain', 'mystartup.com')->first();
        $this->assertNotNull($organization);
        $this->assertEquals('My Startup Inc', $organization->name);
        $this->assertEquals('789 Startup Avenue, Innovation City', $organization->address);

        // Verify user was created as organization creator
        $user = User::where('email', 'founder@mystartup.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Startup Founder', $user->name);
        $this->assertFalse($user->pending_approval); // Creator is immediately approved
        $this->assertTrue($user->isAdmin());
        $this->assertEquals($organization->id, $user->organization_id);
        $this->assertEquals($user->id, $organization->creator_id);
    }

    public function test_workflow_behavior_summary()
    {
        // This test documents the expected behavior for the user's request

        // Case 1: Organization Email CHECKED + Unique Domain = Organization Creation Form
        $uniqueDomainResponse = $this->post('/register', [
            'name' => 'Unique Founder',
            'email' => 'founder@uniquedomain123.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        $this->assertEquals(302, $uniqueDomainResponse->status());
        $this->assertStringEndsWith('/organization/create', $uniqueDomainResponse->headers->get('Location'));

        // Clear session for next test
        session()->flush();

        // Case 2: Organization Email CHECKED + Existing Domain = Pending Approval Notification
        $existingOrg = Organization::factory()->create(['domain' => 'bigcompany.com']);
        $existingOrg->createDefaultRoles();

        $existingDomainResponse = $this->post('/register', [
            'name' => 'New Joiner',
            'email' => 'joiner@bigcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        $this->assertEquals(302, $existingDomainResponse->status());
        $this->assertStringEndsWith('/login', $existingDomainResponse->headers->get('Location'));
        $this->assertEquals('registration-pending', session('status'));
    }

    public function test_organization_creation_requires_valid_session_data()
    {
        // Test accessing organization creation form without session data
        $response = $this->get('/organization/create');
        $response->assertRedirect('/register');

        // Test submitting organization creation without session data
        $response = $this->post('/organization/create', [
            'organization_name' => 'Test Company',
            'organization_address' => 'Test Address',
        ]);

        $response->assertRedirect('/register');
    }
}
