<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, UserInvitation, Organization, Role};
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $regularUser;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organizations
        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'testorganization.com'
        ]);

        // Create users
        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'organization_id' => $this->organization->id,
        ]);

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'admin@testorganization.com',
        ]);

        $this->regularUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Assign admin role
        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'organization_id' => $this->organization->id,
        ]);
        $this->admin->roles()->attach($adminRole->id);
    }

    public function test_super_admin_can_access_invitations_index()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/invitations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/UserInvitations/Index')
                 ->has('invitations')
                 ->where('can_invite_users', true)
                 ->where('is_super_admin', true)
        );
    }

    public function test_admin_can_access_invitations_index()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/invitations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/UserInvitations/Index')
                 ->has('invitations')
                 ->where('can_invite_users', true)
                 ->where('is_super_admin', false)
        );
    }

    public function test_regular_user_cannot_access_invitations()
    {
        $response = $this->actingAs($this->regularUser)
            ->get('/admin/invitations');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_invitations()
    {
        $response = $this->get('/admin/invitations');

        $response->assertRedirect('/login');
    }

    public function test_super_admin_can_access_create_invitation_form()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/invitations/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/UserInvitations/Create')
                 ->has('organizations')
                 ->where('is_super_admin', true)
        );
    }

    public function test_admin_can_access_create_invitation_form()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/invitations/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/UserInvitations/Create')
                 ->where('is_super_admin', false)
                 ->has('user_organization')
        );
    }

    public function test_super_admin_can_create_invitation_with_organization()
    {
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => 'newuser@example.com',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertRedirect('/admin/invitations');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'newuser@example.com',
            'organization_id' => $this->organization->id,
            'invited_by' => $this->superAdmin->id,
        ]);

        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            UserInvitationNotification::class
        );
    }

    public function test_super_admin_can_create_invitation_without_organization()
    {
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => 'newuser@example.com',
                'organization_id' => null,
            ]);

        $response->assertRedirect('/admin/invitations');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'newuser@example.com',
            'organization_id' => null,
            'invited_by' => $this->superAdmin->id,
        ]);
    }

    public function test_admin_can_create_invitation_for_their_organization()
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)
            ->post('/admin/invitations', [
                'email' => 'newuser@testorganization.com',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertRedirect('/admin/invitations');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'newuser@testorganization.com',
            'organization_id' => $this->organization->id,
            'invited_by' => $this->admin->id,
        ]);
    }

    public function test_admin_cannot_create_invitation_for_different_organization()
    {
        $otherOrg = Organization::factory()->create(['name' => 'Other Organization']);

        $response = $this->actingAs($this->admin)
            ->post('/admin/invitations', [
                'email' => 'newuser@example.com',
                'organization_id' => $otherOrg->id,
            ]);

        $response->assertSessionHasErrors('organization_id');

        $this->assertDatabaseMissing('user_invitations', [
            'email' => 'newuser@example.com',
            'organization_id' => $otherOrg->id,
        ]);
    }

    public function test_cannot_invite_existing_user()
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => 'existing@example.com',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('user_invitations', [
            'email' => 'existing@example.com',
        ]);
    }

    public function test_cannot_create_duplicate_pending_invitation()
    {
        UserInvitation::factory()->create([
            'email' => 'pending@example.com',
            'organization_id' => $this->organization->id,
            'invited_by' => $this->superAdmin->id,
            'expires_at' => Carbon::now()->addDays(7),
            'accepted_at' => null,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => 'pending@example.com',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_can_create_invitation_for_expired_invitation_email()
    {
        UserInvitation::factory()->create([
            'email' => 'expired@example.com',
            'organization_id' => $this->organization->id,
            'invited_by' => $this->superAdmin->id,
            'expires_at' => Carbon::now()->subDays(1), // Expired
            'accepted_at' => null,
        ]);

        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => 'expired@testorganization.com',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertRedirect('/admin/invitations');
        $response->assertSessionHas('success');
    }

    public function test_super_admin_can_delete_any_invitation()
    {
        $invitation = UserInvitation::factory()->create([
            'email' => 'test@example.com',
            'organization_id' => $this->organization->id,
            'invited_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete("/admin/invitations/{$invitation->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('user_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_admin_can_delete_invitation_for_their_organization()
    {
        $invitation = UserInvitation::factory()->create([
            'email' => 'test@example.com',
            'organization_id' => $this->organization->id,
            'invited_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/admin/invitations/{$invitation->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('user_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_admin_cannot_delete_invitation_for_different_organization()
    {
        $otherOrg = Organization::factory()->create();
        $invitation = UserInvitation::factory()->create([
            'email' => 'test@example.com',
            'organization_id' => $otherOrg->id,
            'invited_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/admin/invitations/{$invitation->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('user_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_can_resend_pending_invitation()
    {
        Notification::fake();

        $invitation = UserInvitation::factory()->create([
            'email' => 'pending@example.com',
            'organization_id' => $this->organization->id,
            'invited_by' => $this->superAdmin->id,
            'expires_at' => Carbon::now()->addDays(7),
            'accepted_at' => null,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post("/admin/invitations/{$invitation->id}/resend");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            UserInvitationNotification::class
        );
    }

    public function test_cannot_resend_accepted_invitation()
    {
        $invitation = UserInvitation::factory()->create([
            'email' => 'accepted@example.com',
            'organization_id' => $this->organization->id,
            'invited_by' => $this->superAdmin->id,
            'expires_at' => Carbon::now()->addDays(7),
            'accepted_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post("/admin/invitations/{$invitation->id}/resend");

        $response->assertRedirect();
        $response->assertSessionHasErrors('message');
    }

    public function test_cannot_resend_expired_invitation()
    {
        $invitation = UserInvitation::factory()->create([
            'email' => 'expired@example.com',
            'organization_id' => $this->organization->id,
            'invited_by' => $this->superAdmin->id,
            'expires_at' => Carbon::now()->subDays(1),
            'accepted_at' => null,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post("/admin/invitations/{$invitation->id}/resend");

        $response->assertRedirect();
        $response->assertSessionHasErrors('message');
    }

    public function test_invitation_validation_rules()
    {
        // Test required email
        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => '',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertSessionHasErrors('email');

        // Test invalid email format
        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => 'invalid-email',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertSessionHasErrors('email');

        // Test non-existent organization
        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => 'test@example.com',
                'organization_id' => 999999,
            ]);

        $response->assertSessionHasErrors('organization_id');
    }
}
