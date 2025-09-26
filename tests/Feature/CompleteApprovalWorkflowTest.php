<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewOrganizationMemberNotification;
use App\Notifications\UserApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CompleteApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
    }

    public function test_complete_organization_member_approval_lifecycle()
    {
        Notification::fake();

        // Step 1: Create organization with admin
        $admin = User::factory()->create([
            'email' => 'admin@lifecycle.com',
            'pending_approval' => false,
            'approved_at' => now(),
            'is_super_admin' => false, // Explicitly set to false
        ]);

        $organization = Organization::factory()->create([
            'domain' => 'lifecycle.com',
            'name' => 'Lifecycle Company',
            'creator_id' => $admin->id,
        ]);

        $roles = $organization->createDefaultRoles();
        $defaultGroup = $organization->createDefaultGroup();

        $admin->update(['organization_id' => $organization->id]);
        $admin->assignRole($roles['admin']);
        $admin->joinGroup($defaultGroup);

        // Step 2: New user attempts to join organization
        $registrationResponse = $this->post('/register', [
            'name' => 'New Member',
            'email' => 'member@lifecycle.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true, // CHECKED - wants to join
        ]);

        // Should redirect to login (not dashboard) since user is pending
        $registrationResponse->assertRedirect('/login');
        $registrationResponse->assertSessionHas('status', 'registration-pending');

        // Step 3: Verify user is created as pending
        $newUser = User::where('email', 'member@lifecycle.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->pending_approval);
        $this->assertEquals($organization->id, $newUser->organization_id);

        // Step 4: Verify admin receives notification
        Notification::assertSentTo(
            $admin,
            NewOrganizationMemberNotification::class,
            function ($notification) use ($newUser, $organization) {
                return $notification->newUser->id === $newUser->id
                    && $notification->organization->id === $organization->id;
            }
        );

        // Step 5: User cannot login while pending
        $loginAttempt = $this->post('/login', [
            'email' => 'member@lifecycle.com',
            'password' => 'password',
        ]);

        $loginAttempt->assertSessionHasErrors(['email']);
        $this->assertGuest();

        // Step 6: Admin approves user
        $approvalResponse = $this->actingAs($admin)
            ->post("/admin/users/{$newUser->id}/approve");

        $approvalResponse->assertRedirect();
        $approvalResponse->assertSessionHas('message', "User {$newUser->name} has been approved successfully.");

        // Step 7: Verify user is approved and receives notification
        $newUser->refresh();
        $this->assertFalse($newUser->pending_approval);
        $this->assertNotNull($newUser->approved_at);
        $this->assertEquals($admin->id, $newUser->approved_by);

        Notification::assertSentTo(
            $newUser,
            UserApprovedNotification::class,
            function ($notification) use ($organization, $admin) {
                return $notification->organization->id === $organization->id
                    && $notification->approvedBy->id === $admin->id;
            }
        );

        // Step 8: User can now login successfully
        $successfulLogin = $this->post('/login', [
            'email' => 'member@lifecycle.com',
            'password' => 'password',
        ]);

        $successfulLogin->assertRedirect('/dashboard');

        // Step 9: Verify user has access to organization features by acting as them
        $dashboardResponse = $this->actingAs($newUser->fresh())->get('/dashboard');
        $dashboardResponse->assertOk();

        // Step 10: Verify user is in default group
        $this->assertTrue($newUser->belongsToGroup($defaultGroup->id));
    }

    public function test_organization_email_unchecked_approval_workflow()
    {
        Notification::fake();

        // Step 1: Create organization
        $organization = Organization::factory()->create([
            'domain' => 'unchecked.com',
            'name' => 'Unchecked Company',
        ]);

        $roles = $organization->createDefaultRoles();
        $admin = User::factory()->create([
            'email' => 'admin@unchecked.com',
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $admin->assignRole($roles['admin']);

        // Step 2: User registers with organization email UNCHECKED
        $registrationResponse = $this->post('/register', [
            'name' => 'Cautious User',
            'email' => 'cautious@unchecked.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false, // UNCHECKED
        ]);

        // Should redirect to confirmation page
        $registrationResponse->assertRedirect('/registration/confirm-organization');

        // Step 3: User decides to join organization
        $confirmationResponse = $this->post('/registration/confirm-organization', [
            'join_organization' => true,
        ]);

        $confirmationResponse->assertRedirect('/login');
        $confirmationResponse->assertSessionHas('status', 'registration-pending');

        // Step 4: Verify user is pending and admin gets notification
        $newUser = User::where('email', 'cautious@unchecked.com')->first();
        $this->assertTrue($newUser->pending_approval);

        Notification::assertSentTo($admin, NewOrganizationMemberNotification::class);

        // Step 5: User cannot login
        $loginAttempt = $this->post('/login', [
            'email' => 'cautious@unchecked.com',
            'password' => 'password',
        ]);

        $loginAttempt->assertSessionHasErrors(['email']);

        // Step 6: Admin approves and user gets notification
        $this->actingAs($admin)->post("/admin/users/{$newUser->id}/approve");

        Notification::assertSentTo($newUser, UserApprovedNotification::class);

        // Step 7: User can now login
        $successfulLogin = $this->post('/login', [
            'email' => 'cautious@unchecked.com',
            'password' => 'password',
        ]);

        $successfulLogin->assertRedirect('/dashboard');
    }

    public function test_organization_email_unchecked_decline_workflow()
    {
        // Step 1: Create organization
        $organization = Organization::factory()->create([
            'domain' => 'decline.com',
            'name' => 'Decline Company',
        ]);

        // Step 2: User registers with organization email UNCHECKED
        $registrationResponse = $this->post('/register', [
            'name' => 'Independent User',
            'email' => 'independent@decline.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false, // UNCHECKED
        ]);

        $registrationResponse->assertRedirect('/registration/confirm-organization');

        // Step 3: User decides NOT to join organization
        $confirmationResponse = $this->post('/registration/confirm-organization', [
            'join_organization' => false,
        ]);

        $confirmationResponse->assertRedirect('/dashboard');

        // Step 4: Verify user is in 'None' organization and can login immediately
        $newUser = User::where('email', 'independent@decline.com')->first();
        $this->assertNotNull($newUser);

        $defaultOrg = Organization::getDefault();
        $this->assertEquals($defaultOrg->id, $newUser->organization_id);
        $this->assertEquals('None', $defaultOrg->name);
        $this->assertFalse($newUser->pending_approval);

        // Step 5: User should already be logged in and can access dashboard
        $this->assertAuthenticated();
        $this->assertEquals($newUser->id, auth()->id());
    }

    public function test_admin_panel_shows_pending_users_correctly()
    {
        $organization = Organization::factory()->create();
        $roles = $organization->createDefaultRoles();
        $defaultGroup = $organization->createDefaultGroup();

        $admin = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $admin->assignRole($roles['admin']);
        $admin->joinGroup($defaultGroup);

        // Create pending and approved users
        $pendingUser1 = User::factory()->create([
            'name' => 'Pending One',
            'email' => 'pending1@test.com',
            'organization_id' => $organization->id,
            'pending_approval' => true,
        ]);

        $pendingUser2 = User::factory()->create([
            'name' => 'Pending Two',
            'email' => 'pending2@test.com',
            'organization_id' => $organization->id,
            'pending_approval' => true,
        ]);

        $approvedUser = User::factory()->create([
            'name' => 'Approved User',
            'email' => 'approved@test.com',
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Access admin panel
        $response = $this->actingAs($admin)->get('/admin/users');
        $response->assertOk();

        $pendingUsers = $response->viewData('page')['props']['pendingUsers'];
        $approvedUsers = $response->viewData('page')['props']['approvedUsers'];

        // Verify correct categorization
        $this->assertCount(2, $pendingUsers);
        $this->assertCount(2, $approvedUsers); // admin + approved user

        $pendingEmails = collect($pendingUsers)->pluck('email')->toArray();
        $this->assertContains('pending1@test.com', $pendingEmails);
        $this->assertContains('pending2@test.com', $pendingEmails);

        $approvedEmails = collect($approvedUsers)->pluck('email')->toArray();
        $this->assertContains('approved@test.com', $approvedEmails);
        $this->assertContains($admin->email, $approvedEmails);
    }

    public function test_notification_system_handles_large_admin_groups()
    {
        Notification::fake();

        $organization = Organization::factory()->create([
            'domain' => 'bigorg.com',
        ]);

        $roles = $organization->createDefaultRoles();

        // Create multiple admins
        $admins = [];
        for ($i = 1; $i <= 5; $i++) {
            $admin = User::factory()->create([
                'email' => "admin{$i}@bigorg.com",
                'organization_id' => $organization->id,
                'pending_approval' => false,
            ]);
            $admin->assignRole($roles['admin']);
            $admins[] = $admin;
        }

        // User joins organization
        $response = $this->post('/register', [
            'name' => 'Big Org Member',
            'email' => 'member@bigorg.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        // Verify all admins received notifications
        foreach ($admins as $admin) {
            Notification::assertSentTo($admin, NewOrganizationMemberNotification::class);
        }

        // Verify total notification count
        Notification::assertSentTimes(NewOrganizationMemberNotification::class, 5);
    }
}
