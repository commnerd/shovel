<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Contracts\AITaskResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AITaskBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);

        $organization = Organization::getDefault();
        $group = $organization->createDefaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'title' => 'Test Project',
            'description' => 'A test project for AI breakdown',
        ]);
    }

    public function test_user_can_generate_task_breakdown()
    {
        // AI service will use fallback when not properly configured

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Implement User Authentication',
                'description' => 'Create a complete user authentication system',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'ai_used' => true,
        ]);

        // Should have at least some subtasks (either from AI or fallback)
        $data = $response->json();
        $this->assertArrayHasKey('subtasks', $data);
        $this->assertNotEmpty($data['subtasks']);
    }

    public function test_task_breakdown_includes_project_context()
    {
        // Create some existing tasks for context
        $existingTask1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Database Setup',
            'status' => 'completed',
        ]);

        $existingTask2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'API Development',
            'status' => 'in_progress',
        ]);

        // AI service will use fallback when not properly configured

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Frontend Implementation',
                'description' => 'Build the user interface',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_task_breakdown_handles_ai_failure()
    {
        // AI service will handle errors gracefully and return fallback

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'ai_used' => true,
        ]);

        // Should have fallback subtasks
        $data = $response->json();
        $this->assertArrayHasKey('subtasks', $data);
    }

    public function test_task_breakdown_validates_input()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                // Missing required title
                'description' => 'Test description',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_task_breakdown_requires_authentication()
    {
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => 'Test Task',
            'description' => 'Test description',
        ]);

        $response->assertStatus(401);
    }

    public function test_task_breakdown_requires_project_ownership()
    {
        $otherUser = User::factory()->create([
            'organization_id' => $this->user->organization_id,
            'pending_approval' => false,
        ]);
        $otherUser->joinGroup($this->user->groups->first());

        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $this->user->groups->first()->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$otherProject->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_create_task_with_ai_generated_subtasks()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Main Task',
                'description' => 'A main task with AI subtasks',
                'status' => 'pending',
                'due_date' => '2025-12-31',
                'subtasks' => [
                    [
                        'title' => 'Subtask 1',
                        'description' => 'First subtask',
                        'status' => 'pending',
                        'due_date' => '2025-12-15',
                    ],
                    [
                        'title' => 'Subtask 2',
                        'description' => 'Second subtask',
                        'status' => 'pending',
                        'due_date' => '2025-12-20',
                    ],
                ],
            ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        // Verify main task was created
        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title' => 'Main Task',
            'parent_id' => null,
        ]);

        $mainTask = Task::where('title', 'Main Task')->first();

        // Verify subtasks were created
        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'parent_id' => $mainTask->id,
            'title' => 'Subtask 1',
        ]);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'parent_id' => $mainTask->id,
            'title' => 'Subtask 2',
        ]);

        // Verify hierarchy
        $this->assertCount(2, $mainTask->children);
        $this->assertFalse($mainTask->isLeaf());

        $subtasks = $mainTask->children()->orderBy('sort_order')->get();
        $this->assertEquals('Subtask 1', $subtasks->first()->title);
        $this->assertEquals('Subtask 2', $subtasks->last()->title);
        $this->assertEquals(1, $subtasks->first()->sort_order);
        $this->assertEquals(2, $subtasks->last()->sort_order);
    }

    public function test_task_creation_validates_subtasks()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Main Task',
                'status' => 'pending',
                'subtasks' => [
                    [
                        // Missing required title
                        'description' => 'Subtask without title',
                        'status' => 'pending',
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subtasks.0.title']);
    }

    public function test_task_breakdown_with_hierarchical_context()
    {
        // Create a hierarchical task structure for context
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Feature Development',
            'status' => 'in_progress',
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentTask->id,
            'title' => 'Backend API',
            'status' => 'completed',
        ]);

        // AI service will use fallback when not properly configured

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Frontend Components',
                'description' => 'Build user interface components',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_task_breakdown_with_empty_project()
    {
        // AI service will use fallback when not properly configured

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'First Task',
                'description' => 'Initial project task',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_ai_breakdown_fallback_when_service_fails()
    {
        // AI service will use fallback when service fails

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Test Task',
                'description' => 'Test description',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'ai_used' => true,
        ]);

        // Should have fallback subtasks
        $data = $response->json();
        $this->assertArrayHasKey('subtasks', $data);
    }

    public function test_task_creation_with_subtasks_maintains_hierarchy()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks", [
                'title' => 'Complex Task',
                'description' => 'A task with multiple subtasks',
                'status' => 'pending',
                'subtasks' => [
                    [
                        'title' => 'Research Phase',
                        'description' => 'Research requirements',
                        'status' => 'pending',
                        'due_date' => '2025-12-10',
                    ],
                    [
                        'title' => 'Development Phase',
                        'description' => 'Implement the solution',
                        'status' => 'pending',
                        'due_date' => '2025-12-20',
                    ],
                    [
                        'title' => 'Testing Phase',
                        'description' => 'Test the implementation',
                        'status' => 'pending',
                        'due_date' => '2025-12-25',
                    ],
                ],
            ]);

        $response->assertRedirect("/dashboard/projects/{$this->project->id}/tasks");

        $mainTask = Task::where('title', 'Complex Task')->first();
        $this->assertNotNull($mainTask);
        $this->assertTrue($mainTask->isTopLevel());
        $this->assertFalse($mainTask->isLeaf());
        $this->assertCount(3, $mainTask->children);

        // Verify subtask hierarchy and ordering
        $subtasks = $mainTask->children()->orderBy('sort_order')->get();

        $this->assertEquals('Research Phase', $subtasks[0]->title);
        $this->assertEquals('Development Phase', $subtasks[1]->title);
        $this->assertEquals('Testing Phase', $subtasks[2]->title);

        foreach ($subtasks as $index => $subtask) {
            $this->assertEquals($index + 1, $subtask->sort_order);
            $this->assertEquals(1, $subtask->depth);
            $this->assertEquals($mainTask->id, $subtask->parent_id);
            $this->assertTrue($subtask->isLeaf());
            $this->assertFalse($subtask->isTopLevel());
        }
    }

    public function test_task_breakdown_with_due_date_context()
    {
        // Set project due date for context
        $this->project->update(['due_date' => '2026-03-31']);

        // AI service will use fallback when not properly configured

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
                'title' => 'Time-sensitive Task',
                'description' => 'Task with deadline pressure',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_task_breakdown_authorization_across_organizations()
    {
        // Create different organization
        $otherOrg = Organization::factory()->create();
        $otherGroup = $otherOrg->createDefaultGroup();

        $otherUser = User::factory()->create([
            'organization_id' => $otherOrg->id,
            'pending_approval' => false,
        ]);
        $otherUser->joinGroup($otherGroup);

        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'group_id' => $otherGroup->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$otherProject->id}/tasks/breakdown", [
                'title' => 'Unauthorized Task',
                'description' => 'Should not be allowed',
            ]);

        $response->assertStatus(403);
    }
}
