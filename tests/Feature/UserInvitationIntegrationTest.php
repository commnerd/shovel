<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, UserInvitation, Organization, Role, Group};
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class UserInvitationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_user_invitation_workflow()
    {
        Notification::fake();

        // Step 1: Setup - Create organization, admin, and roles/groups
        $organization = Organization::factory()->create(['name' => 'Test Corp']);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'organization_id' => $organization->id,
        ]);

        $defaultGroup = Group::factory()->create([
            'name' => 'Everyone',
            'organization_id' => $organization->id,
            'is_default' => true,
        ]);

        $userRole = Role::factory()->create([
            'name' => 'User',
            'organization_id' => $organization->id,
        ]);

        // Step 2: Super Admin creates invitation
        $inviteResponse = $this->actingAs($superAdmin)
            ->post('/admin/invitations', [
                'email' => 'newuser@testcorp.com',
                'organization_id' => $organization->id,
            ]);

        $inviteResponse->assertRedirect('/admin/invitations');
        $inviteResponse->assertSessionHas('success');

        // Verify invitation was created
        $invitation = UserInvitation::where('email', 'newuser@testcorp.com')->first();
        $this->assertNotNull($invitation);
        $this->assertEquals($organization->id, $invitation->organization_id);
        $this->assertEquals($superAdmin->id, $invitation->invited_by);
        $this->assertFalse($invitation->isExpired());
        $this->assertFalse($invitation->isAccepted());
        $this->assertTrue($invitation->isPending());

        // Verify email was sent
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            UserInvitationNotification::class
        );

        // Step 3: User visits set password page
        $setPasswordResponse = $this->get("/invitation/{$invitation->token}");

        $setPasswordResponse->assertStatus(200);
        $setPasswordResponse->assertInertia(fn ($page) =>
            $page->component('auth/SetPassword')
                 ->where('email', 'newuser@testcorp.com')
                 ->where('organization.name', 'Test Corp')
        );

        // Step 4: User sets password and creates account
        $createAccountResponse = $this->post("/invitation/{$invitation->token}", [
            'name' => 'John Doe',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $createAccountResponse->assertRedirect('/login');
        $createAccountResponse->assertSessionHas('success');

        // Step 5: Verify user was created correctly
        $user = User::where('email', 'newuser@testcorp.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals($organization->id, $user->organization_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->approved_at);
        $this->assertFalse($user->is_super_admin); // Not first user

        // Step 6: Verify user was assigned to default group
        $this->assertTrue($user->groups()->where('group_id', $defaultGroup->id)->exists());

        // Step 7: Verify user was assigned default role
        $this->assertTrue($user->roles()->where('role_id', $userRole->id)->exists());

        // Step 8: Verify invitation was marked as accepted
        $invitation->refresh();
        $this->assertTrue($invitation->isAccepted());
        $this->assertNotNull($invitation->accepted_at);

        // Step 9: Verify user can login
        $loginResponse = $this->post('/login', [
            'email' => 'newuser@testcorp.com',
            'password' => 'SecurePassword123!',
        ]);

        $loginResponse->assertRedirect('/dashboard');

        // Step 10: Verify user cannot use invitation link again
        $secondUseResponse = $this->get("/invitation/{$invitation->token}");
        $secondUseResponse->assertRedirect('/login');
        $secondUseResponse->assertSessionHas('message');
    }

    public function test_super_admin_invitation_without_organization_workflow()
    {
        Notification::fake();

        // Setup default organization
        $defaultOrg = Organization::factory()->create([
            'name' => 'None',
            'is_default' => true,
        ]);

        $defaultGroup = Group::factory()->create([
            'name' => 'Everyone',
            'organization_id' => $defaultOrg->id,
            'is_default' => true,
        ]);

        $userRole = Role::factory()->create([
            'name' => 'User',
            'organization_id' => $defaultOrg->id,
        ]);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        // Super Admin invites user without specific organization
        $inviteResponse = $this->actingAs($superAdmin)
            ->post('/admin/invitations', [
                'email' => 'platformuser@example.com',
                'organization_id' => null,
            ]);

        $inviteResponse->assertRedirect('/admin/invitations');

        $invitation = UserInvitation::where('email', 'platformuser@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertNull($invitation->organization_id);

        // User sets password
        $createAccountResponse = $this->post("/invitation/{$invitation->token}", [
            'name' => 'Platform User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $createAccountResponse->assertRedirect('/login');

        // Verify user was assigned to default organization
        $user = User::where('email', 'platformuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($defaultOrg->id, $user->organization_id);
    }

    public function test_admin_can_only_invite_within_domain_restrictions()
    {
        $organization = Organization::factory()->create([
            'name' => 'Acme Corp',
            'is_default' => false,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@acmecorp.com',
            'organization_id' => $organization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'organization_id' => $organization->id,
        ]);
        $admin->roles()->attach($adminRole->id);

        // Admin can invite from same domain
        $validResponse = $this->actingAs($admin)
            ->post('/admin/invitations', [
                'email' => 'colleague@acmecorp.com',
                'organization_id' => $organization->id,
            ]);

        $validResponse->assertRedirect('/admin/invitations');
        $validResponse->assertSessionHas('success');

        // Admin cannot invite from different domain
        $invalidResponse = $this->actingAs($admin)
            ->post('/admin/invitations', [
                'email' => 'outsider@othercorp.com',
                'organization_id' => $organization->id,
            ]);

        $invalidResponse->assertSessionHasErrors('email');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'colleague@acmecorp.com',
        ]);

        $this->assertDatabaseMissing('user_invitations', [
            'email' => 'outsider@othercorp.com',
        ]);
    }

    public function test_invitation_email_content_and_functionality()
    {
        $organization = Organization::factory()->create(['name' => 'Test Organization']);
        $inviter = User::factory()->create([
            'name' => 'John Inviter',
            'organization_id' => $organization->id,
        ]);

        $invitation = UserInvitation::createInvitation(
            'invited@example.com',
            $organization->id,
            $inviter->id
        );

        $notification = new UserInvitationNotification($invitation);
        $mailMessage = $notification->toMail(new \Illuminate\Notifications\AnonymousNotifiable);

        // Test email content
        $this->assertStringContainsString('John Inviter', $mailMessage->introLines[0]);
        $this->assertStringContainsString('Test Organization', $mailMessage->introLines[0]);
        $this->assertStringContainsString('Set Password', $mailMessage->actionText);
        $this->assertStringContainsString('/invitation/' . $invitation->token, $mailMessage->actionUrl);

        // Test array representation
        $arrayData = $notification->toArray(new \Illuminate\Notifications\AnonymousNotifiable);
        $this->assertEquals($invitation->id, $arrayData['invitation_id']);
        $this->assertEquals('invited@example.com', $arrayData['email']);
        $this->assertEquals('Test Organization', $arrayData['organization']);
        $this->assertEquals('John Inviter', $arrayData['invited_by']);
    }

    public function test_permission_based_invitation_access()
    {
        $organization = Organization::factory()->create();

        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $regularUser = User::factory()->create(['organization_id' => $organization->id]);

        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'organization_id' => $organization->id,
        ]);
        $admin->roles()->attach($adminRole->id);

        // Super Admin can access
        $superAdminResponse = $this->actingAs($superAdmin)
            ->get('/admin/invitations');
        $superAdminResponse->assertStatus(200);

        // Admin can access
        $adminResponse = $this->actingAs($admin)
            ->get('/admin/invitations');
        $adminResponse->assertStatus(200);

        // Regular user cannot access
        $userResponse = $this->actingAs($regularUser)
            ->get('/admin/invitations');
        $userResponse->assertStatus(403);

        // Guest cannot access - gets 403 from admin middleware
        $guestResponse = $this->get('/admin/invitations');
        $guestResponse->assertStatus(403);
    }
}
