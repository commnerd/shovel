<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstUserSuperAdminUnitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_count_check_works_correctly()
    {
        // Initially no users
        $this->assertEquals(0, User::count());

        // Create first user
        $firstUser = User::factory()->create();
        $this->assertEquals(1, User::count());

        // Create second user
        $secondUser = User::factory()->create();
        $this->assertEquals(2, User::count());
    }

    /** @test */
    public function is_super_admin_method_works_correctly()
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $regularUser = User::factory()->create(['is_super_admin' => false]);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($regularUser->isSuperAdmin());
    }

    /** @test */
    public function super_admin_field_is_boolean_cast()
    {
        $user = User::factory()->create(['is_super_admin' => 1]);
        $this->assertTrue($user->is_super_admin);
        $this->assertIsBool($user->is_super_admin);

        $user2 = User::factory()->create(['is_super_admin' => 0]);
        $this->assertFalse($user2->is_super_admin);
        $this->assertIsBool($user2->is_super_admin);
    }

    /** @test */
    public function make_super_admin_method_works()
    {
        $user = User::factory()->create(['is_super_admin' => false]);
        $this->assertFalse($user->isSuperAdmin());

        $user->makeSuperAdmin();
        $user->refresh();

        $this->assertTrue($user->isSuperAdmin());
    }

    /** @test */
    public function first_user_logic_simulation()
    {
        // Simulate the exact logic used in controllers
        $this->assertEquals(0, User::count());

        // First user check
        $isFirstUser1 = User::count() === 0;
        $this->assertTrue($isFirstUser1);

        // Create first user
        $user1 = User::factory()->create(['is_super_admin' => $isFirstUser1]);
        $this->assertTrue($user1->isSuperAdmin());

        // Second user check
        $isFirstUser2 = User::count() === 0;
        $this->assertFalse($isFirstUser2);

        // Create second user
        $user2 = User::factory()->create(['is_super_admin' => $isFirstUser2]);
        $this->assertFalse($user2->isSuperAdmin());
    }

    /** @test */
    public function user_creation_with_all_required_fields()
    {
        $this->assertEquals(0, User::count());

        $organization = Organization::factory()->create();

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'organization_id' => $organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
            'is_super_admin' => User::count() === 0,
        ];

        $user = User::create($userData);

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertFalse($user->pending_approval);
        $this->assertNotNull($user->approved_at);
    }

    /** @test */
    public function multiple_super_admin_queries_work_correctly()
    {
        // Create mix of users
        $superAdmin1 = User::factory()->create(['is_super_admin' => true]);
        $regularUser1 = User::factory()->create(['is_super_admin' => false]);
        $regularUser2 = User::factory()->create(['is_super_admin' => false]);
        $superAdmin2 = User::factory()->create(['is_super_admin' => true]);

        // Test various queries
        $this->assertEquals(2, User::where('is_super_admin', true)->count());
        $this->assertEquals(2, User::where('is_super_admin', false)->count());
        $this->assertEquals(4, User::count());

        $superAdmins = User::where('is_super_admin', true)->get();
        $this->assertTrue($superAdmins->contains($superAdmin1));
        $this->assertTrue($superAdmins->contains($superAdmin2));
    }

    /** @test */
    public function user_factory_respects_super_admin_field()
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $regularUser = User::factory()->create(['is_super_admin' => false]);

        $this->assertTrue($superAdmin->fresh()->isSuperAdmin());
        $this->assertFalse($regularUser->fresh()->isSuperAdmin());
    }
}
