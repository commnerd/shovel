<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewOrganizationMemberNotification;
use App\Notifications\UserApprovedNotification;

class UserApprovalProcessTest extends TestCase
{
    use RefreshDatabase;

    protected $organization;
    protected $admin;
    protected $adminRole;
    protected $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        // Create organization with admin
        $this->organization = Organization::factory()->create([
            'domain' => 'testcompany.com',
            'name' => 'Test Company',
        ]);

        $roles = $this->organization->createDefaultRoles();
        $this->adminRole = $roles['admin'];
        $this->userRole = $roles['user'];

        $defaultGroup = $this->organization->createDefaultGroup();

        $this->admin = User::factory()->create([
            'email' => 'admin@testcompany.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        $this->organization->update(['creator_id' => $this->admin->id]);
        $this->admin->assignRole($this->adminRole);
        $this->admin->joinGroup($defaultGroup);
    }

    public function test_pending_user_cannot_login()
    {
        // Create pending user
        $pendingUser = User::factory()->create([
            'email' => 'pending@testcompany.com',
            'password' => \Hash::make('password'),
            'organization_id' => $this->organization->id,
            'pending_approval' => true,
        ]);

        // Attempt to login with pending user
        $response = $this->post('/login', [
            'email' => 'pending@testcompany.com',
            'password' => 'password',
        ]);

        // Should be redirected back with error (might go to / due to landing route)
        $this->assertTrue(in_array($response->status(), [302]));
        $response->assertSessionHasErrors(['email']);

        // Verify error message
        $errors = session('errors');
        $this->assertStringContainsString('pending approval', $errors->get('email')[0]);

        // Verify user is not authenticated
        $this->assertGuest();
    }

    public function test_approved_user_can_login()
    {
        // Create approved user
        $approvedUser = User::factory()->create([
            'email' => 'approved@testcompany.com',
            'password' => \Hash::make('password'),
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Attempt to login with approved user
        $response = $this->post('/login', [
            'email' => 'approved@testcompany.com',
            'password' => 'password',
        ]);

        // Should be redirected to dashboard
        $response->assertRedirect('/dashboard');

        // Verify user is authenticated
        $this->assertAuthenticated();
        $this->assertEquals($approvedUser->id, auth()->id());
    }

    public function test_registration_creates_pending_user_and_sends_admin_notifications()
    {
        Notification::fake();

        // User registers to join existing organization
        $response = $this->post('/register', [
            'name' => 'New Employee',
            'email' => 'employee@testcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        // Should redirect to login (not dashboard) since user is pending
        $response->assertRedirect('/login');
        $response->assertSessionHas('status', 'registration-pending');
        $response->assertSessionHas('message', 'Your account has been created and is pending approval from your organization administrator. You will receive an email when approved.');

        // Verify user was created as pending
        $newUser = User::where('email', 'employee@testcompany.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->pending_approval);
        $this->assertEquals($this->organization->id, $newUser->organization_id);

        // Verify notification was sent to admin
        Notification::assertSentTo(
            $this->admin,
            NewOrganizationMemberNotification::class,
            function ($notification) use ($newUser) {
                return $notification->newUser->id === $newUser->id
                    && $notification->organization->id === $this->organization->id;
            }
        );

        // Verify user cannot login yet
        $loginResponse = $this->post('/login', [
            'email' => 'employee@testcompany.com',
            'password' => 'password',
        ]);

        $this->assertTrue(in_array($loginResponse->status(), [302]));
        $loginResponse->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_admin_approval_sends_notification_to_user()
    {
        Notification::fake();

        // Create pending user
        $pendingUser = User::factory()->create([
            'email' => 'pending@testcompany.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => true,
        ]);

        // Admin approves user
        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$pendingUser->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('message', "User {$pendingUser->name} has been approved successfully.");

        // Verify user is now approved
        $pendingUser->refresh();
        $this->assertFalse($pendingUser->pending_approval);
        $this->assertNotNull($pendingUser->approved_at);
        $this->assertEquals($this->admin->id, $pendingUser->approved_by);

        // Verify approval notification was sent to user
        Notification::assertSentTo(
            $pendingUser,
            UserApprovedNotification::class,
            function ($notification) {
                return $notification->organization->id === $this->organization->id
                    && $notification->approvedBy->id === $this->admin->id;
            }
        );

        // Verify user can now login
        $loginResponse = $this->post('/login', [
            'email' => 'pending@testcompany.com',
            'password' => 'password',
        ]);
        $loginResponse->assertRedirect('/dashboard');
    }

    public function test_multiple_admins_receive_new_member_notifications()
    {
        Notification::fake();

        // Create additional admin
        $admin2 = User::factory()->create([
            'email' => 'admin2@testcompany.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $admin2->assignRole($this->adminRole);

        // User registers to join organization
        $response = $this->post('/register', [
            'name' => 'Multi Admin Test',
            'email' => 'multitest@testcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        // Verify both admins received notifications
        Notification::assertSentTo($this->admin, NewOrganizationMemberNotification::class);
        Notification::assertSentTo($admin2, NewOrganizationMemberNotification::class);

        // Verify notification count
        Notification::assertSentTimes(NewOrganizationMemberNotification::class, 2);
    }

    public function test_organization_without_admins_handles_gracefully()
    {
        Notification::fake();

        // Create organization without any admins
        $orgWithoutAdmins = Organization::factory()->create([
            'domain' => 'noadmins.com',
            'name' => 'No Admins Org',
        ]);
        $orgWithoutAdmins->createDefaultRoles();

        // User registers to join organization
        $response = $this->post('/register', [
            'name' => 'Orphan User',
            'email' => 'user@noadmins.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        // Should still create user but no notifications sent
        $response->assertRedirect('/login');

        $user = User::where('email', 'user@noadmins.com')->first();
        $this->assertTrue($user->pending_approval);

        // No notifications should be sent since there are no admins
        Notification::assertNothingSent();
    }

    public function test_approval_notification_contains_correct_information()
    {
        $pendingUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@testcompany.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => true,
        ]);

        $notification = new UserApprovedNotification($this->organization, $this->admin);
        $mailMessage = $notification->toMail($pendingUser);

        // Test subject and content
        $this->assertStringContainsString('Welcome to Test Company', $mailMessage->subject);
        $this->assertStringContainsString('approved', $mailMessage->subject);
        $this->assertStringContainsString('Test User', $mailMessage->greeting);
        $this->assertStringContainsString('Test Company', $mailMessage->introLines[0]);

        // Check that admin name appears somewhere in the message
        $allContent = implode(' ', $mailMessage->introLines);
        $this->assertStringContainsString($this->admin->name, $allContent);

        // Test array representation
        $arrayData = $notification->toArray($pendingUser);
        $this->assertEquals($this->organization->id, $arrayData['organization_id']);
        $this->assertEquals('Test Company', $arrayData['organization_name']);
        $this->assertEquals($this->admin->id, $arrayData['approved_by_id']);
        $this->assertEquals($this->admin->name, $arrayData['approved_by_name']);
    }

    public function test_complete_approval_workflow()
    {
        Notification::fake();

        // Step 1: User registers
        $response = $this->post('/register', [
            'name' => 'Workflow User',
            'email' => 'workflow@testcompany.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        $response->assertRedirect('/login');

        // Step 2: Verify user is pending and cannot login
        $user = User::where('email', 'workflow@testcompany.com')->first();
        $this->assertTrue($user->pending_approval);

        $loginResponse = $this->post('/login', [
            'email' => 'workflow@testcompany.com',
            'password' => 'password',
        ]);
        $loginResponse->assertSessionHasErrors(['email']);

        // Step 3: Admin receives notification
        Notification::assertSentTo($this->admin, NewOrganizationMemberNotification::class);

        // Step 4: Admin approves user
        $approvalResponse = $this->actingAs($this->admin)
            ->post("/admin/users/{$user->id}/approve");

        $approvalResponse->assertRedirect();

        // Step 5: User receives approval notification
        Notification::assertSentTo($user, UserApprovedNotification::class);

        // Step 6: User can now login
        $user->refresh();
        $this->assertFalse($user->pending_approval);

        $finalLoginResponse = $this->post('/login', [
            'email' => 'workflow@testcompany.com',
            'password' => 'password',
        ]);
        $finalLoginResponse->assertRedirect('/dashboard');
    }

    public function test_non_admin_cannot_approve_users()
    {
        // Create regular user
        $regularUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $regularUser->assignRole($this->userRole);

        // Create pending user
        $pendingUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => true,
        ]);

        // Regular user tries to approve
        $response = $this->actingAs($regularUser)
            ->post("/admin/users/{$pendingUser->id}/approve");

        $response->assertStatus(403);

        // Verify user is still pending
        $this->assertTrue($pendingUser->fresh()->pending_approval);
    }
}
