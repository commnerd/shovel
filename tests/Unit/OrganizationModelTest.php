<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_created()
    {
        $organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'domain' => 'testorg.com',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Test Organization',
            'domain' => 'testorg.com',
        ]);
    }

    public function test_organization_can_create_default_group()
    {
        $organization = Organization::factory()->create([
            'name' => 'Test Org',
        ]);

        $group = $organization->createDefaultGroup();

        $this->assertEquals('Everyone', $group->name);
        $this->assertTrue($group->is_default);
        $this->assertEquals($organization->id, $group->organization_id);
    }

    public function test_organization_has_relationships()
    {
        $creator = User::factory()->create();
        $organization = Organization::factory()->create([
            'creator_id' => $creator->id,
        ]);

        $group = Group::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        // Test relationships
        $this->assertEquals($creator->id, $organization->creator->id);
        $this->assertTrue($organization->groups->contains($group));
        $this->assertTrue($organization->users->contains($user));
    }

    public function test_can_get_default_organization()
    {
        // Create default organization via seeder
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $defaultOrg = Organization::getDefault();

        $this->assertNotNull($defaultOrg);
        $this->assertEquals('None', $defaultOrg->name);
        $this->assertTrue($defaultOrg->is_default);
    }

    public function test_default_organization_has_everyone_group()
    {
        // Create default organization via seeder
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $defaultOrg = Organization::getDefault();
        $defaultGroup = $defaultOrg->defaultGroup();

        $this->assertNotNull($defaultGroup);
        $this->assertEquals('Everyone', $defaultGroup->name);
        $this->assertTrue($defaultGroup->is_default);
    }
}
