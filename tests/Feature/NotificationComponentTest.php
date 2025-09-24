<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewOrganizationMemberNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationComponentTest extends TestCase
{
    use RefreshDatabase;

    protected $organization;

    protected $creator;

    protected $adminRole;

    protected $userRole;

    protected $defaultGroup;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);

        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'test.com',
        ]);

        $roles = $this->organization->createDefaultRoles();
        $this->adminRole = $roles['admin'];
        $this->userRole = $roles['user'];

        $this->defaultGroup = $this->organization->createDefaultGroup();

        $this->creator = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);

        $this->organization->update(['creator_id' => $this->creator->id]);
        $this->creator->assignRole($this->adminRole);
        $this->creator->joinGroup($this->defaultGroup);
    }

    public function test_admin_can_view_pending_users_in_management_interface()
    {
        // Create pending users
        $pendingUser1 = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => true,
            'name' => 'John Doe',
            'email' => 'john@test.com',
        ]);
        $pendingUser1->assignRole($this->userRole);

        $pendingUser2 = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => true,
            'name' => 'Jane Smith',
            'email' => 'jane@test.com',
        ]);
        $pendingUser2->assignRole($this->userRole);

        // Create approved user (should not appear in pending list)
        $approvedUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);
        $approvedUser->assignRole($this->userRole);

        // Access admin interface
        $response = $this->actingAs($this->creator)
            ->get('/admin/users');

        $response->assertOk();

        // Check that pending users are displayed
        $pendingUsers = $response->viewData('page')['props']['pendingUsers'];
        $approvedUsers = $response->viewData('page')['props']['approvedUsers'];

        $this->assertCount(2, $pendingUsers);
        $this->assertCount(2, $approvedUsers); // Creator + approved user

        // Verify pending user data structure
        $pendingUserEmails = collect($pendingUsers)->pluck('email')->toArray();
        $this->assertContains('john@test.com', $pendingUserEmails);
        $this->assertContains('jane@test.com', $pendingUserEmails);

        // Verify approved users don't include pending ones
        $approvedUserEmails = collect($approvedUsers)->pluck('email')->toArray();
        $this->assertNotContains('john@test.com', $approvedUserEmails);
        $this->assertNotContains('jane@test.com', $approvedUserEmails);
    }

    public function test_organization_confirmation_page_displays_correct_information()
    {
        // Create organization for confirmation
        $organization = Organization::factory()->create([
            'domain' => 'testcompany.com',
            'name' => 'Test Company',
        ]);

        // Set up session data for confirmation page
        session([
            'registration_data' => [
                'name' => 'Test User',
                'email' => 'user@testcompany.com',
                'password' => \Hash::make('password'),
            ],
            'existing_organization' => $organization->toArray(),
        ]);

        // Access the confirmation page
        $response = $this->get('/registration/confirm-organization');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('auth/ConfirmOrganization')
            ->has('email')
            ->has('organization')
            ->where('email', 'user@testcompany.com')
            ->where('organization.name', 'Test Company')
            ->where('organization.domain', 'testcompany.com')
        );
    }

    public function test_user_can_join_organization_from_confirmation_page()
    {
        Notification::fake();

        // Set up registration session data
        session([
            'registration_data' => [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'password' => \Hash::make('password'),
            ],
            'existing_organization' => $this->organization,
        ]);

        $response = $this->post('/registration/confirm-organization', [
            'join_organization' => true,
        ]);

        $response->assertRedirect('/login');

        // Verify user was created and is pending
        $newUser = User::where('email', 'newuser@test.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->pending_approval);
        $this->assertEquals($this->organization->id, $newUser->organization_id);

        // Verify notification was sent
        Notification::assertSentTo(
            $this->creator,
            NewOrganizationMemberNotification::class
        );
    }

    public function test_user_can_register_individually_from_confirmation_page()
    {
        Notification::fake();

        // Set up registration session data
        session([
            'registration_data' => [
                'name' => 'Individual User',
                'email' => 'individual@test.com',
                'password' => \Hash::make('password'),
            ],
            'existing_organization' => $this->organization,
        ]);

        $response = $this->post('/registration/confirm-organization', [
            'join_organization' => false,
        ]);

        $response->assertRedirect('/dashboard');

        // Verify user was created in default organization
        $newUser = User::where('email', 'individual@test.com')->first();
        $this->assertNotNull($newUser);
        $this->assertFalse($newUser->pending_approval);

        $defaultOrg = Organization::getDefault();
        $this->assertEquals($defaultOrg->id, $newUser->organization_id);

        // Verify no notification was sent to test org creator
        Notification::assertNothingSentTo($this->creator);
    }

    public function test_notification_contains_correct_user_and_organization_data()
    {
        $newUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $notification = new NewOrganizationMemberNotification($newUser, $this->organization);

        // Test mail representation
        $mailMessage = $notification->toMail($this->creator);

        $this->assertStringContainsString('Test Organization', $mailMessage->subject);
        $this->assertStringContainsString($this->creator->name, $mailMessage->greeting);
        $this->assertStringContainsString('Test User', $mailMessage->introLines[2]);
        $this->assertStringContainsString('test@example.com', $mailMessage->introLines[3]);

        // Test array representation
        $arrayData = $notification->toArray($this->creator);

        $this->assertEquals($newUser->id, $arrayData['new_user_id']);
        $this->assertEquals('Test User', $arrayData['new_user_name']);
        $this->assertEquals('test@example.com', $arrayData['new_user_email']);
        $this->assertEquals($this->organization->id, $arrayData['organization_id']);
        $this->assertEquals('Test Organization', $arrayData['organization_name']);
    }

    public function test_admin_notification_workflow_end_to_end()
    {
        Notification::fake();

        // Step 1: User registers with organization email
        $response = $this->post('/register', [
            'name' => 'New Employee',
            'email' => 'employee@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        $response->assertRedirect('/login');

        // Step 2: Verify user is pending and notification sent
        $newUser = User::where('email', 'employee@test.com')->first();
        $this->assertTrue($newUser->pending_approval);

        Notification::assertSentTo(
            $this->creator,
            NewOrganizationMemberNotification::class,
            function ($notification) use ($newUser) {
                return $notification->newUser->id === $newUser->id;
            }
        );

        // Step 3: Admin views pending users
        $response = $this->actingAs($this->creator)
            ->get('/admin/users');

        $pendingUsers = $response->viewData('page')['props']['pendingUsers'];
        $this->assertCount(1, $pendingUsers);
        $this->assertEquals('employee@test.com', $pendingUsers[0]['email']);

        // Step 4: Admin approves user
        $response = $this->actingAs($this->creator)
            ->post("/admin/users/{$newUser->id}/approve");

        $response->assertRedirect();

        // Step 5: Verify user is approved and added to default group
        $newUser->refresh();
        $this->assertFalse($newUser->pending_approval);
        $this->assertNotNull($newUser->approved_at);
        $this->assertEquals($this->creator->id, $newUser->approved_by);
        $this->assertTrue($newUser->belongsToGroup($this->defaultGroup->id));

        // Step 6: Verify user no longer appears in pending list
        $response = $this->actingAs($this->creator)
            ->get('/admin/users');

        $pendingUsers = $response->viewData('page')['props']['pendingUsers'];
        $approvedUsers = $response->viewData('page')['props']['approvedUsers'];

        $this->assertCount(0, $pendingUsers);
        $this->assertCount(2, $approvedUsers); // Creator + newly approved user

        $approvedEmails = collect($approvedUsers)->pluck('email')->toArray();
        $this->assertContains('employee@test.com', $approvedEmails);
    }

    public function test_non_admin_cannot_access_user_management_interface()
    {
        // Create regular user
        $regularUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $regularUser->assignRole($this->userRole);

        // Attempt to access admin interface
        $response = $this->actingAs($regularUser)
            ->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_organization_domain_matching_works_correctly()
    {
        // Create organization with specific domain
        $techOrg = Organization::factory()->create([
            'name' => 'Tech Company',
            'domain' => 'techcompany.com',
        ]);

        // Test user with matching domain
        $response = $this->post('/register', [
            'name' => 'Tech Employee',
            'email' => 'employee@techcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false, // User doesn't check organization box
        ]);

        // Should be redirected to confirmation page
        $response->assertRedirect('/registration/confirm-organization');

        // Test user with non-matching domain
        $response = $this->post('/register', [
            'name' => 'Individual User',
            'email' => 'user@randomdomain.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        // Should be registered directly to default organization
        $response->assertRedirect('/dashboard');

        $user = User::where('email', 'user@randomdomain.com')->first();
        $defaultOrg = Organization::getDefault();
        $this->assertEquals($defaultOrg->id, $user->organization_id);
    }

    public function test_organization_creator_receives_multiple_notifications()
    {
        Notification::fake();

        // Create multiple users joining the organization
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $user = User::factory()->create([
                'name' => "User {$i}",
                'email' => "user{$i}@test.com",
                'organization_id' => $this->organization->id,
                'pending_approval' => true,
            ]);
            $user->assignRole($this->userRole);
            $users[] = $user;

            // Send notification
            $this->creator->notify(new NewOrganizationMemberNotification($user, $this->organization));
        }

        // Verify all notifications were sent
        Notification::assertSentToTimes($this->creator, NewOrganizationMemberNotification::class, 3);

        // Verify each notification has correct data
        foreach ($users as $user) {
            Notification::assertSentTo(
                $this->creator,
                NewOrganizationMemberNotification::class,
                function ($notification) use ($user) {
                    return $notification->newUser->id === $user->id &&
                           $notification->organization->id === $this->organization->id;
                }
            );
        }
    }
}
