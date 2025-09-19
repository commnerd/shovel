<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\Group;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GroupModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_can_be_created()
    {
        $organization = Organization::factory()->create();
        
        $group = Group::factory()->create([
            'name' => 'Test Group',
            'organization_id' => $organization->id,
        ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'organization_id' => $organization->id,
        ]);
    }

    public function test_group_belongs_to_organization()
    {
        $organization = Organization::factory()->create();
        $group = Group::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertEquals($organization->id, $group->organization->id);
    }

    public function test_group_can_have_users()
    {
        $organization = Organization::factory()->create();
        $group = Group::factory()->create([
            'organization_id' => $organization->id,
        ]);
        
        $user1 = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $user2 = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        // Add users to group
        $group->users()->attach([$user1->id, $user2->id], ['joined_at' => now()]);

        $this->assertEquals(2, $group->users()->count());
        $this->assertTrue($group->users->contains($user1));
        $this->assertTrue($group->users->contains($user2));
    }

    public function test_group_can_have_projects()
    {
        $organization = Organization::factory()->create();
        $group = Group::factory()->create([
            'organization_id' => $organization->id,
        ]);
        
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
        ]);

        $this->assertTrue($group->projects->contains($project));
        $this->assertEquals($group->id, $project->group_id);
    }

    public function test_everyone_group_factory_state()
    {
        $organization = Organization::factory()->create();
        
        $group = Group::factory()->everyone()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertEquals('Everyone', $group->name);
        $this->assertTrue($group->is_default);
        $this->assertStringContainsString('Default group', $group->description);
    }
}
