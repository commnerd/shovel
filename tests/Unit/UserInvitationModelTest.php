<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\{UserInvitation, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class UserInvitationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_invitation_can_be_created()
    {
        $organization = Organization::factory()->create();
        $inviter = User::factory()->create();

        $invitation = UserInvitation::factory()->create([
            'email' => 'test@example.com',
            'organization_id' => $organization->id,
            'invited_by' => $inviter->id,
        ]);

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'test@example.com',
            'organization_id' => $organization->id,
            'invited_by' => $inviter->id,
        ]);
    }

    public function test_invitation_belongs_to_organization()
    {
        $organization = Organization::factory()->create();
        $invitation = UserInvitation::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertInstanceOf(Organization::class, $invitation->organization);
        $this->assertEquals($organization->id, $invitation->organization->id);
    }

    public function test_invitation_belongs_to_inviter()
    {
        $inviter = User::factory()->create();
        $invitation = UserInvitation::factory()->create([
            'invited_by' => $inviter->id,
        ]);

        $this->assertInstanceOf(User::class, $invitation->invitedBy);
        $this->assertEquals($inviter->id, $invitation->invitedBy->id);
    }

    public function test_invitation_can_have_no_organization()
    {
        $invitation = UserInvitation::factory()->withoutOrganization()->create();

        $this->assertNull($invitation->organization_id);
        $this->assertNull($invitation->organization);
    }

    public function test_is_expired_returns_true_for_expired_invitation()
    {
        $invitation = UserInvitation::factory()->expired()->create();

        $this->assertTrue($invitation->isExpired());
    }

    public function test_is_expired_returns_false_for_valid_invitation()
    {
        $invitation = UserInvitation::factory()->create([
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->assertFalse($invitation->isExpired());
    }

    public function test_is_accepted_returns_true_for_accepted_invitation()
    {
        $invitation = UserInvitation::factory()->accepted()->create();

        $this->assertTrue($invitation->isAccepted());
    }

    public function test_is_accepted_returns_false_for_pending_invitation()
    {
        $invitation = UserInvitation::factory()->create([
            'accepted_at' => null,
        ]);

        $this->assertFalse($invitation->isAccepted());
    }

    public function test_is_pending_returns_true_for_valid_unaccepted_invitation()
    {
        $invitation = UserInvitation::factory()->create([
            'expires_at' => Carbon::now()->addDays(7),
            'accepted_at' => null,
        ]);

        $this->assertTrue($invitation->isPending());
    }

    public function test_is_pending_returns_false_for_expired_invitation()
    {
        $invitation = UserInvitation::factory()->expired()->create();

        $this->assertFalse($invitation->isPending());
    }

    public function test_is_pending_returns_false_for_accepted_invitation()
    {
        $invitation = UserInvitation::factory()->accepted()->create();

        $this->assertFalse($invitation->isPending());
    }

    public function test_generate_token_returns_60_character_string()
    {
        $token = UserInvitation::generateToken();

        $this->assertIsString($token);
        $this->assertEquals(60, strlen($token));
    }

    public function test_create_invitation_static_method()
    {
        $organization = Organization::factory()->create();
        $inviter = User::factory()->create();

        $invitation = UserInvitation::createInvitation(
            'test@example.com',
            $organization->id,
            $inviter->id
        );

        $this->assertEquals('test@example.com', $invitation->email);
        $this->assertEquals($organization->id, $invitation->organization_id);
        $this->assertEquals($inviter->id, $invitation->invited_by);
        $this->assertNotNull($invitation->token);
        $this->assertNotNull($invitation->expires_at);
        $this->assertNull($invitation->accepted_at);
    }

    public function test_create_invitation_without_organization()
    {
        $inviter = User::factory()->create();

        $invitation = UserInvitation::createInvitation(
            'test@example.com',
            null,
            $inviter->id
        );

        $this->assertEquals('test@example.com', $invitation->email);
        $this->assertNull($invitation->organization_id);
        $this->assertEquals($inviter->id, $invitation->invited_by);
    }

    public function test_accept_method_sets_accepted_at()
    {
        $invitation = UserInvitation::factory()->create([
            'accepted_at' => null,
        ]);

        $this->assertNull($invitation->accepted_at);

        $invitation->accept();

        $this->assertNotNull($invitation->accepted_at);
        $this->assertInstanceOf(Carbon::class, $invitation->accepted_at);
    }

    public function test_invitation_fillable_attributes()
    {
        $invitation = new UserInvitation();

        $expectedFillable = [
            'email',
            'token',
            'organization_id',
            'invited_by',
            'expires_at',
            'accepted_at',
        ];

        $this->assertEquals($expectedFillable, $invitation->getFillable());
    }

    public function test_invitation_casts_dates()
    {
        $invitation = UserInvitation::factory()->create();

        $this->assertInstanceOf(Carbon::class, $invitation->expires_at);

        if ($invitation->accepted_at) {
            $this->assertInstanceOf(Carbon::class, $invitation->accepted_at);
        }
    }
}
