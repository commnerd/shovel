<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

class OrganizationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure default organization exists
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    public function test_user_can_register_without_organization_email()
    {
        $response = $this->post('/register', [
            'name' => 'Individual User',
            'email' => 'individual@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'individual@example.com')->first();
        $defaultOrg = Organization::getDefault();
        
        $this->assertEquals($defaultOrg->id, $user->organization_id);
        $this->assertFalse($user->pending_approval);
        $this->assertNotNull($user->approved_at);
    }

    public function test_user_can_create_new_organization()
    {
        // Step 1: Register with organization email
        $response = $this->post('/register', [
            'name' => 'Organization Creator',
            'email' => 'creator@newcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        $response->assertRedirect('/organization/create');

        // Step 2: Create organization
        $response = $this->post('/organization/create', [
            'organization_name' => 'New Company Inc.',
            'organization_address' => '123 Business St, City, State 12345',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        // Verify organization was created
        $organization = Organization::where('domain', 'newcompany.com')->first();
        $this->assertNotNull($organization);
        $this->assertEquals('New Company Inc.', $organization->name);

        // Verify user was created as organization creator
        $user = User::where('email', 'creator@newcompany.com')->first();
        $this->assertEquals($organization->id, $user->organization_id);
        $this->assertEquals($user->id, $organization->creator_id);
        $this->assertFalse($user->pending_approval);

        // Verify default group was created
        $defaultGroup = $organization->defaultGroup();
        $this->assertNotNull($defaultGroup);
        $this->assertEquals('Everyone', $defaultGroup->name);
        $this->assertTrue($defaultGroup->is_default);

        // Verify user was added to default group
        $this->assertTrue($user->groups->contains($defaultGroup));
    }

    public function test_user_can_join_existing_organization()
    {
        // Create existing organization
        $existingOrg = Organization::factory()->create([
            'name' => 'Existing Company',
            'domain' => 'existing.com',
        ]);
        $existingGroup = Group::factory()->everyone()->create([
            'organization_id' => $existingOrg->id,
        ]);

        $response = $this->post('/register', [
            'name' => 'New Employee',
            'email' => 'employee@existing.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();

        $user = User::where('email', 'employee@existing.com')->first();
        $this->assertEquals($existingOrg->id, $user->organization_id);
        $this->assertTrue($user->pending_approval);
        $this->assertNull($user->approved_at);
    }

    public function test_user_can_decline_organization_and_register_individually()
    {
        // Create existing organization
        $existingOrg = Organization::factory()->create([
            'name' => 'Company Corp',
            'domain' => 'company.com',
        ]);

        // Step 1: Register without organization email but with matching domain
        $response = $this->post('/register', [
            'name' => 'Individual User',
            'email' => 'user@company.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        $response->assertRedirect('/registration/confirm-organization');

        // Step 2: Confirm individual registration
        $response = $this->post('/registration/confirm-organization', [
            'join_organization' => false,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'user@company.com')->first();
        $defaultOrg = Organization::getDefault();
        
        $this->assertEquals($defaultOrg->id, $user->organization_id);
        $this->assertFalse($user->pending_approval);
    }

    public function test_user_can_change_mind_and_join_organization_during_confirmation()
    {
        // Create existing organization
        $existingOrg = Organization::factory()->create([
            'name' => 'Company Corp',
            'domain' => 'company.com',
        ]);

        // Step 1: Register without organization email but with matching domain
        $response = $this->post('/register', [
            'name' => 'Changing User',
            'email' => 'user@company.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        $response->assertRedirect('/registration/confirm-organization');

        // Step 2: Change mind and join organization
        $response = $this->post('/registration/confirm-organization', [
            'join_organization' => true,
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();

        $user = User::where('email', 'user@company.com')->first();
        $this->assertEquals($existingOrg->id, $user->organization_id);
        $this->assertTrue($user->pending_approval);
    }

    public function test_organization_creation_form_requires_session_data()
    {
        $response = $this->get('/organization/create');
        $response->assertRedirect('/register');

        $response = $this->post('/organization/create', [
            'organization_name' => 'Test Org',
            'organization_address' => 'Test Address',
        ]);
        $response->assertRedirect('/register');
    }

    public function test_organization_creation_validates_input()
    {
        // Set up session data
        session([
            'registration_data' => [
                'name' => 'Test User',
                'email' => 'test@neworg.com',
                'password' => Hash::make('password'),
            ]
        ]);

        $response = $this->post('/organization/create', [
            'organization_name' => '', // Empty name
            'organization_address' => '',
        ]);

        $response->assertSessionHasErrors(['organization_name', 'organization_address']);
    }

    public function test_registration_assigns_correct_email_domain()
    {
        $response = $this->post('/register', [
            'name' => 'Domain User',
            'email' => 'user@testdomain.co.uk',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        $response->assertRedirect('/organization/create');

        // Complete organization creation
        $response = $this->post('/organization/create', [
            'organization_name' => 'Test Domain Company',
            'organization_address' => '456 Domain St',
        ]);

        $organization = Organization::where('domain', 'testdomain.co.uk')->first();
        $this->assertNotNull($organization);
        $this->assertEquals('Test Domain Company', $organization->name);
    }
}
