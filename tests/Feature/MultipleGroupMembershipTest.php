<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultipleGroupMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $organization;

    protected $defaultGroup;

    protected $secondGroup;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        $this->organization = Organization::getDefault();
        $this->defaultGroup = $this->organization->defaultGroup();

        // Create a second group in the same organization
        $this->secondGroup = Group::factory()->create([
            'name' => 'Development Team',
            'description' => 'Software development team',
            'organization_id' => $this->organization->id,
            'is_default' => false,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Add user to both groups
        $this->user->groups()->attach([
            $this->defaultGroup->id => ['joined_at' => now()],
            $this->secondGroup->id => ['joined_at' => now()->addMinutes(5)],
        ]);
    }

    public function test_user_can_belong_to_multiple_groups()
    {
        $this->assertEquals(2, $this->user->groups()->count());
        $this->assertTrue($this->user->belongsToGroup($this->defaultGroup->id));
        $this->assertTrue($this->user->belongsToGroup($this->secondGroup->id));
    }

    public function test_user_can_create_projects_in_different_groups()
    {
        // Create project in default group
        $project1 = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'Project in Default Group',
                'description' => 'This project is in the default group',
                'due_date' => '2025-12-31',
                'group_id' => $this->defaultGroup->id,
                'tasks' => [],
            ]);

        $project1->assertRedirect('/dashboard/projects');

        // Create project in second group
        $project2 = $this->actingAs($this->user)
            ->post('/dashboard/projects', [
                'title' => 'Project in Dev Team',
                'description' => 'This project is for the dev team',
                'due_date' => '2025-12-31',
                'group_id' => $this->secondGroup->id,
                'tasks' => [],
            ]);

        $project2->assertRedirect('/dashboard/projects');

        // Verify both projects were created with correct group assignments
        $userProjects = Project::where('user_id', $this->user->id)->get();
        $this->assertEquals(2, $userProjects->count());

        $defaultGroupProject = $userProjects->where('group_id', $this->defaultGroup->id)->first();
        $secondGroupProject = $userProjects->where('group_id', $this->secondGroup->id)->first();

        $this->assertNotNull($defaultGroupProject);
        $this->assertNotNull($secondGroupProject);
        $this->assertEquals('Project in Default Group', $defaultGroupProject->title);
        $this->assertEquals('Project in Dev Team', $secondGroupProject->title);
    }

    public function test_project_create_form_shows_all_user_groups()
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects/create');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/Create')
                ->has('userGroups', 2)
                ->where('userGroups.0.name', 'Everyone')
                ->where('userGroups.0.is_default', true)
                ->where('userGroups.1.name', 'Development Team')
                ->where('userGroups.1.is_default', false)
            );
    }

    public function test_user_can_see_projects_from_all_their_groups()
    {
        // Create projects in both groups by different users
        $otherUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'approved_at' => now(),
        ]);

        // Add other user to second group only
        $otherUser->groups()->attach($this->secondGroup->id, ['joined_at' => now()]);

        // Create project by current user in default group
        $myProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $this->defaultGroup->id,
            'title' => 'My Default Project',
            'created_at' => now()->subMinutes(10),
        ]);

        // Create project by other user in second group (visible to current user)
        $sharedProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->secondGroup->id,
            'title' => 'Shared Dev Project',
            'created_at' => now(),
        ]);

        // Create project in a different organization (should not be visible)
        $otherOrg = Organization::factory()->create();
        $otherGroup = Group::factory()->create(['organization_id' => $otherOrg->id]);
        $hiddenProject = Project::factory()->create([
            'group_id' => $otherGroup->id,
            'title' => 'Hidden Project',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard/projects');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/Index')
                ->has('projects', 2) // Should see 2 projects (myProject and sharedProject)
                ->where('projects.0.title', 'Shared Dev Project') // Latest first
                ->where('projects.1.title', 'My Default Project')
            );
    }

    public function test_user_can_join_additional_groups()
    {
        // Create a third group
        $thirdGroup = Group::factory()->create([
            'name' => 'Marketing Team',
            'organization_id' => $this->organization->id,
        ]);

        // Initially user should have 2 groups
        $this->assertEquals(2, $this->user->groups()->count());

        // Add user to third group
        $this->user->joinGroup($thirdGroup);

        // Now user should have 3 groups
        $this->assertEquals(3, $this->user->fresh()->groups()->count());
        $this->assertTrue($this->user->belongsToGroup($thirdGroup->id));
    }

    public function test_user_cannot_leave_default_group()
    {
        $this->assertEquals(2, $this->user->groups()->count());

        // Try to leave default group
        $result = $this->user->leaveGroup($this->defaultGroup);

        $this->assertFalse($result);
        $this->assertEquals(2, $this->user->fresh()->groups()->count());
        $this->assertTrue($this->user->belongsToGroup($this->defaultGroup->id));
    }

    public function test_user_can_leave_non_default_groups()
    {
        $this->assertEquals(2, $this->user->groups()->count());

        // Leave second group
        $result = $this->user->leaveGroup($this->secondGroup);

        $this->assertTrue($result);
        $this->assertEquals(1, $this->user->fresh()->groups()->count());
        $this->assertFalse($this->user->belongsToGroup($this->secondGroup->id));
        $this->assertTrue($this->user->belongsToGroup($this->defaultGroup->id));
    }

    public function test_joining_same_group_twice_doesnt_create_duplicates()
    {
        $initialCount = $this->user->groups()->count();

        // Try to join a group the user is already in
        $this->user->joinGroup($this->defaultGroup);

        $this->assertEquals($initialCount, $this->user->fresh()->groups()->count());
    }
}
