<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Organization, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class AdminInvitationDomainRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superAdmin;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure AI provider to prevent middleware redirects
        \App\Models\Setting::set('ai.cerebrus.api_key', 'test-cerebrus-key', 'string', 'Cerebrus API Key');

        $this->organization = Organization::factory()->create([
            'name' => 'Acme Corp',
            'domain' => 'acmecorp.com',
            'is_default' => false,
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@acmecorp.com',
            'organization_id' => $this->organization->id,
        ]);

        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'organization_id' => $this->organization->id,
        ]);

        // Assign admin role (lowercase to match isAdmin() method)
        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'organization_id' => $this->organization->id,
        ]);
        $this->admin->roles()->attach($adminRole->id);
    }

    public function test_admin_can_invite_user_with_same_domain()
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)
            ->post('/admin/invitations', [
                'email' => 'newuser@acmecorp.com',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertRedirect('/admin/invitations');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'newuser@acmecorp.com',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_admin_cannot_invite_user_with_different_domain()
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/invitations', [
                'email' => 'newuser@differentcompany.com',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('user_invitations', [
            'email' => 'newuser@differentcompany.com',
        ]);
    }

    public function test_admin_can_invite_user_without_organization()
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)
            ->post('/admin/invitations', [
                'email' => 'newuser@acmecorp.com',
                'organization_id' => null,
            ]);

        $response->assertRedirect('/admin/invitations');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'newuser@acmecorp.com',
            'organization_id' => null,
        ]);
    }

    public function test_super_admin_can_invite_user_with_any_domain()
    {
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => 'anyone@anydomain.com',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertRedirect('/admin/invitations');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'anyone@anydomain.com',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_super_admin_can_invite_user_to_any_organization()
    {
        $otherOrg = Organization::factory()->create([
            'name' => 'Other Corp',
            'domain' => 'othercorp.com',
        ]);

        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/invitations', [
                'email' => 'user@example.com',
                'organization_id' => $otherOrg->id,
            ]);

        $response->assertRedirect('/admin/invitations');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'user@example.com',
            'organization_id' => $otherOrg->id,
        ]);
    }

    public function test_admin_from_default_organization_has_no_domain_restrictions()
    {
        $defaultOrg = Organization::factory()->create([
            'name' => 'None',
            'is_default' => true,
        ]);

        $defaultAdmin = User::factory()->create([
            'email' => 'admin@defaultorg.com',
            'organization_id' => $defaultOrg->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'organization_id' => $defaultOrg->id,
        ]);
        $defaultAdmin->roles()->attach($adminRole->id);

        Notification::fake();

        $response = $this->actingAs($defaultAdmin)
            ->post('/admin/invitations', [
                'email' => 'anyone@anydomain.com',
                'organization_id' => null,
            ]);

        $response->assertRedirect('/admin/invitations');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'anyone@anydomain.com',
            'organization_id' => null,
        ]);
    }

    public function test_invitation_index_shows_correct_invitations_for_admin()
    {
        // Create invitations for different organizations
        $invitation1 = \App\Models\UserInvitation::factory()->create([
            'organization_id' => $this->organization->id,
            'invited_by' => $this->admin->id,
        ]);

        $otherOrg = Organization::factory()->create();
        $invitation2 = \App\Models\UserInvitation::factory()->create([
            'organization_id' => $otherOrg->id,
            'invited_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/invitations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/UserInvitations/Index')
                 ->has('invitations.data', 1) // Only sees their organization's invitation
        );
    }

    public function test_invitation_index_shows_all_invitations_for_super_admin()
    {
        // Create invitations for different organizations
        $invitation1 = \App\Models\UserInvitation::factory()->create([
            'organization_id' => $this->organization->id,
            'invited_by' => $this->admin->id,
        ]);

        $otherOrg = Organization::factory()->create();
        $invitation2 = \App\Models\UserInvitation::factory()->create([
            'organization_id' => $otherOrg->id,
            'invited_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/invitations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/UserInvitations/Index')
                 ->has('invitations.data', 2) // Sees all invitations
        );
    }
}
