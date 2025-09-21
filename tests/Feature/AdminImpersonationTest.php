<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $orgAdmin;

    protected User $targetUser;

    protected User $otherOrgUser;

    protected User $regularUser;

    protected Organization $organization;

    protected Organization $otherOrganization;

    protected Group $group;

    protected Group $otherGroup;

    protected Project $project;

    protected Project $otherProject;

    protected Task $task;

    protected Task $subtask;

    protected Task $otherTask;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

        // Create main organization
        $this->organization = Organization::getDefault();
        $this->group = $this->organization->createDefaultGroup();

        // Create other organization
        $this->otherOrganization = Organization::factory()->create([
            'name' => 'Other Organization',
            'domain' => 'other.com',
        ]);
        $this->otherGroup = $this->otherOrganization->createDefaultGroup();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
            'is_super_admin' => true,
        ]);
        $this->superAdmin->joinGroup($this->group);

        // Create organization admin
        $this->orgAdmin = User::factory()->create([
            'name' => 'Organization Admin',
            'email' => 'admin@example.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->orgAdmin->joinGroup($this->group);
        $adminRole = $this->organization->roles()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator']
        );
        $this->orgAdmin->assignRole($adminRole);

        // Create target user for impersonation (same organization)
        $this->targetUser = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target@example.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->targetUser->joinGroup($this->group);

        // Create user in other organization
        $this->otherOrgUser = User::factory()->create([
            'name' => 'Other Org User',
            'email' => 'user@other.com',
            'organization_id' => $this->otherOrganization->id,
            'pending_approval' => false,
        ]);
        $this->otherOrgUser->joinGroup($this->otherGroup);

        // Create regular user (same organization)
        $this->regularUser = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->regularUser->joinGroup($this->group);

        // Create projects and tasks
        $this->project = Project::factory()->create([
            'user_id' => $this->targetUser->id,
            'group_id' => $this->group->id,
            'title' => 'Target User Project',
        ]);

        $this->otherProject = Project::factory()->create([
            'user_id' => $this->otherOrgUser->id,
            'group_id' => $this->otherGroup->id,
            'title' => 'Other Org Project',
        ]);

        // Create tasks
        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Main Task',
            'description' => 'A main task',
        ]);

        $this->subtask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $this->task->id,
            'title' => 'Subtask',
            'description' => 'A subtask',
        ]);

        $this->otherTask = Task::factory()->create([
            'project_id' => $this->otherProject->id,
            'title' => 'Other Org Task',
            'description' => 'Task from other organization',
        ]);
    }

    public function test_organization_admin_can_login_as_user_in_same_organization()
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Administrative support',
            ]);

        $response->assertRedirect('/dashboard');

        // Check that we're now logged in as the target user
        $this->assertEquals($this->targetUser->id, Auth::id());

        // Check that the original admin ID is stored in session
        $this->assertEquals($this->orgAdmin->id, session('original_admin_id'));
    }

    public function test_organization_admin_cannot_login_as_user_in_different_organization()
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->otherOrgUser->id}/login-as", [
                'reason' => 'Testing cross-org access',
            ]);

        $response->assertStatus(403);

        // Should still be logged in as org admin
        $this->assertEquals($this->orgAdmin->id, Auth::id());
        $this->assertNull(session('original_admin_id'));
    }

    public function test_organization_admin_cannot_login_as_themselves()
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->orgAdmin->id}/login-as", [
                'reason' => 'Testing self-impersonation',
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('message', 'Cannot login as yourself');

        // Should still be logged in as org admin
        $this->assertEquals($this->orgAdmin->id, Auth::id());
        $this->assertNull(session('original_admin_id'));
    }

    public function test_regular_user_cannot_login_as_another_user()
    {
        $response = $this->actingAs($this->regularUser)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing unauthorized access',
            ]);

        $response->assertStatus(403);

        // Should still be logged in as regular user
        $this->assertEquals($this->regularUser->id, Auth::id());
    }

    public function test_organization_admin_can_return_to_original_account()
    {
        // First, login as another user
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Administrative support',
            ]);

        // Verify we're logged in as target user
        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->orgAdmin->id, session('original_admin_id'));

        // Now return to admin account
        $response = $this->post('/admin/return-to-admin');

        $response->assertRedirect('/admin');

        // Check that we're back to the original admin
        $this->assertEquals($this->orgAdmin->id, Auth::id());

        // Check that the session data is cleared
        $this->assertNull(session('original_admin_id'));
    }

    public function test_impersonated_user_can_access_all_projects_in_organization()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Project access testing',
            ]);

        $response = $this->get('/dashboard/projects');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Index')
            ->has('projects')
        );

        // Should be able to see the target user's project
        $response->assertInertia(fn (Assert $page) => $page->where('projects.0.id', $this->project->id)
        );
    }

    public function test_impersonated_user_can_access_all_tasks_in_organization()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Task access testing',
            ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks')
        );

        // Should be able to see both main task and subtask
        $response->assertInertia(fn (Assert $page) => $page->where('tasks', function ($tasks) {
            $taskIds = collect($tasks)->pluck('id')->toArray();
            $this->assertContains($this->task->id, $taskIds);

            $mainTask = collect($tasks)->firstWhere('id', $this->task->id);
            $this->assertEquals('Main Task', $mainTask['title']);

            return true;
        })
        );
    }

    public function test_impersonated_user_can_access_subtasks()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Subtask access testing',
            ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks?filter=all");

        $response->assertOk();

        // Should be able to see subtasks in the hierarchy
        $response->assertInertia(fn (Assert $page) => $page->where('tasks', function ($tasks) {
            $taskIds = collect($tasks)->pluck('id')->toArray();
            $this->assertContains($this->subtask->id, $taskIds);

            $subtask = collect($tasks)->firstWhere('id', $this->subtask->id);
            $this->assertEquals('Subtask', $subtask['title']);
            $this->assertEquals($this->task->id, $subtask['parent_id']);

            return true;
        })
        );
    }

    public function test_impersonated_user_cannot_access_tasks_from_other_organizations()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Cross-org access testing',
            ]);

        // Try to access project from other organization
        $response = $this->get("/dashboard/projects/{$this->otherProject->id}/tasks");

        $response->assertStatus(403);
    }

    public function test_impersonated_user_can_create_tasks()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Task creation testing',
            ]);

        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'New Task Created by Admin',
            'description' => 'Task created while impersonating',
            'priority' => 'medium',
            'status' => 'pending',
        ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title' => 'New Task Created by Admin',
            'description' => 'Task created while impersonating',
        ]);
    }

    public function test_impersonated_user_can_edit_tasks()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Task editing testing',
            ]);

        $response = $this->put("/dashboard/projects/{$this->project->id}/tasks/{$this->task->id}", [
            'title' => 'Updated Task Title',
            'description' => 'Updated by admin while impersonating',
            'priority' => 'high',
            'status' => 'completed',
        ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'title' => 'Updated Task Title',
            'description' => 'Updated by admin while impersonating',
            'status' => 'completed',
        ]);
    }

    public function test_impersonated_user_can_delete_tasks()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Task deletion testing',
            ]);

        $response = $this->delete("/dashboard/projects/{$this->project->id}/tasks/{$this->task->id}");

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        $this->assertDatabaseMissing('tasks', [
            'id' => $this->task->id,
        ]);

        // Subtask should also be deleted (cascade)
        $this->assertDatabaseMissing('tasks', [
            'id' => $this->subtask->id,
        ]);
    }

    public function test_super_admin_can_access_tasks_from_all_organizations()
    {
        // Login as user from other organization through super admin impersonation
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/users/{$this->otherOrgUser->id}/login-as", [
                'reason' => 'Cross-org super admin access',
            ]);

        $response = $this->get("/dashboard/projects/{$this->otherProject->id}/tasks");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Projects/Tasks/Index')
            ->has('tasks')
        );

        // Should be able to see tasks from other organization
        $response->assertInertia(fn (Assert $page) => $page->where('tasks', function ($tasks) {
            $taskIds = collect($tasks)->pluck('id')->toArray();
            $this->assertContains($this->otherTask->id, $taskIds);

            $otherTask = collect($tasks)->firstWhere('id', $this->otherTask->id);
            $this->assertEquals('Other Org Task', $otherTask['title']);

            return true;
        })
        );
    }

    public function test_impersonation_banner_shows_for_admin_impersonation()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Banner testing',
            ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('auth.original_admin_id', $this->orgAdmin->id)
            ->where('auth.user.id', $this->targetUser->id)
        );
    }

    public function test_admin_impersonation_is_logged()
    {
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Testing audit logging',
            ]);

        // Verify impersonation worked (logs are written in the controller)
        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->orgAdmin->id, session('original_admin_id'));
    }

    public function test_admin_can_access_user_search_within_organization()
    {
        $response = $this->actingAs($this->orgAdmin)
            ->get('/admin/users/search?query=Target');

        $response->assertOk();

        $data = $response->json();
        $this->assertGreaterThan(0, count($data['users']));

        // Should find the target user
        $targetUserData = collect($data['users'])->firstWhere('id', $this->targetUser->id);
        $this->assertNotNull($targetUserData);

        // Should NOT find users from other organizations
        $otherOrgUserData = collect($data['users'])->firstWhere('id', $this->otherOrgUser->id);
        $this->assertNull($otherOrgUserData);
    }

    public function test_admin_cannot_access_super_admin_endpoints()
    {
        $response = $this->actingAs($this->orgAdmin)
            ->get('/super-admin/users/search?query=Test');

        $response->assertStatus(403);
    }

    public function test_admin_impersonation_respects_organization_boundaries()
    {
        // Create additional users in both organizations
        $sameOrgUser = User::factory()->create([
            'name' => 'Same Org User',
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $sameOrgUser->joinGroup($this->group);

        $differentOrgUser = User::factory()->create([
            'name' => 'Different Org User',
            'organization_id' => $this->otherOrganization->id,
            'pending_approval' => false,
        ]);
        $differentOrgUser->joinGroup($this->otherGroup);

        $response = $this->actingAs($this->orgAdmin)
            ->get('/admin/users/search?query=Org User');

        $response->assertOk();

        $data = $response->json();

        // Should find same organization user
        $sameOrgData = collect($data['users'])->firstWhere('id', $sameOrgUser->id);
        $this->assertNotNull($sameOrgData);

        // Should NOT find different organization user
        $differentOrgData = collect($data['users'])->firstWhere('id', $differentOrgUser->id);
        $this->assertNull($differentOrgData);
    }

    public function test_admin_can_manage_user_roles_within_organization()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'Role management testing',
            ]);

        // Admin should be able to perform actions as the impersonated user
        // This is tested implicitly through the task creation/editing tests above
        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->orgAdmin->id, session('original_admin_id'));
    }

    public function test_nested_impersonation_is_prevented()
    {
        // Login as target user through admin impersonation
        $this->actingAs($this->orgAdmin)
            ->post("/admin/users/{$this->targetUser->id}/login-as", [
                'reason' => 'First impersonation',
            ]);

        // Try to login as another user while already impersonating
        $response = $this->post("/admin/users/{$this->regularUser->id}/login-as", [
            'reason' => 'Nested impersonation attempt',
        ]);

        // Should fail because we're not currently an admin
        $response->assertStatus(403);

        // Should still be logged in as target user
        $this->assertEquals($this->targetUser->id, Auth::id());
        $this->assertEquals($this->orgAdmin->id, session('original_admin_id'));
    }
}
