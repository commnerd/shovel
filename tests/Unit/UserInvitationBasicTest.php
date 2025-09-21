<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\UserInvitation;
use Carbon\Carbon;

class UserInvitationBasicTest extends TestCase
{
    public function test_generate_token_returns_60_character_string()
    {
        $token = UserInvitation::generateToken();

        $this->assertIsString($token);
        $this->assertEquals(60, strlen($token));
    }

    public function test_generate_token_returns_unique_values()
    {
        $token1 = UserInvitation::generateToken();
        $token2 = UserInvitation::generateToken();

        $this->assertNotEquals($token1, $token2);
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

    public function test_invitation_casts_configuration()
    {
        $invitation = new UserInvitation();

        $casts = $invitation->getCasts();

        $this->assertEquals('datetime', $casts['expires_at']);
        $this->assertEquals('datetime', $casts['accepted_at']);
    }
}
