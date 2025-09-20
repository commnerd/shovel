<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Task, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubtaskDefaultPriorityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_subtask_creation_form_provides_parent_priority_as_default()
    {
        $this->actingAs($this->user);

        // Test with high priority parent
        $highPriorityParent = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/{$highPriorityParent->id}/subtasks/create");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('parentTask')
                 ->where('parentTask.priority', 'high')
                 ->where('parentTask.priority_level', 3)
        );

        // Test with medium priority parent
        $mediumPriorityParent = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/{$mediumPriorityParent->id}/subtasks/create");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->where('parentTask.priority', 'medium')
                 ->where('parentTask.priority_level', 2)
        );

        // Test with low priority parent
        $lowPriorityParent = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'parent_id' => null,
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/{$lowPriorityParent->id}/subtasks/create");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->where('parentTask.priority', 'low')
                 ->where('parentTask.priority_level', 1)
        );
    }

    public function test_regular_task_creation_form_has_medium_default()
    {
        $this->actingAs($this->user);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/create");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('parentTasks')
                 ->missing('parentTask') // No pre-selected parent
        );
    }

    public function test_subtask_creation_with_high_priority_parent_defaults_to_high()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        // Create subtask without specifying priority (should default to parent's priority)
        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'Test Subtask',
            'description' => 'Test description',
            'parent_id' => $parentTask->id,
            'priority' => 'high', // This should be the default for high priority parent
            'status' => 'pending',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify the subtask was created with the correct priority
        $subtask = Task::where('parent_id', $parentTask->id)->first();
        $this->assertNotNull($subtask);
        $this->assertEquals('high', $subtask->priority);
    }

    public function test_subtask_creation_with_medium_priority_parent_defaults_to_medium()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'Test Subtask',
            'description' => 'Test description',
            'parent_id' => $parentTask->id,
            'priority' => 'medium', // This should be the default for medium priority parent
            'status' => 'pending',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $subtask = Task::where('parent_id', $parentTask->id)->first();
        $this->assertNotNull($subtask);
        $this->assertEquals('medium', $subtask->priority);
    }

    public function test_subtask_creation_with_low_priority_parent_defaults_to_low()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'low',
            'parent_id' => null,
        ]);

        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'Test Subtask',
            'description' => 'Test description',
            'parent_id' => $parentTask->id,
            'priority' => 'low', // This should be the default for low priority parent
            'status' => 'pending',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $subtask = Task::where('parent_id', $parentTask->id)->first();
        $this->assertNotNull($subtask);
        $this->assertEquals('low', $subtask->priority);
    }

    public function test_ai_generated_subtasks_respect_parent_priority_minimum()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        // Generate AI breakdown for high priority task
        $response = $this->postJson("/dashboard/projects/{$this->project->id}/tasks/breakdown", [
            'title' => $parentTask->title,
            'description' => $parentTask->description,
            'parent_task_id' => $parentTask->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $data = $response->json();

        // All generated subtasks should have priority >= parent priority (high)
        foreach ($data['subtasks'] as $subtask) {
            $this->assertContains($subtask['priority'], ['high'],
                "AI-generated subtask should have priority >= parent priority (high), got: {$subtask['priority']}"
            );
        }
    }

    public function test_subtask_form_shows_correct_minimum_priority_guidance()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'high',
            'parent_id' => null,
        ]);

        $response = $this->get("/dashboard/projects/{$this->project->id}/tasks/{$parentTask->id}/subtasks/create");

        $response->assertStatus(200);

        // Verify the form receives the parent task with priority information
        $response->assertInertia(fn ($page) =>
            $page->where('parentTask.priority', 'high')
                 ->where('parentTask.priority_level', 3)
        );
    }

    public function test_bulk_subtask_creation_respects_parent_priority()
    {
        $this->actingAs($this->user);

        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'parent_id' => null,
        ]);

        // Create task with multiple subtasks
        $response = $this->post("/dashboard/projects/{$this->project->id}/tasks", [
            'title' => 'Parent Task',
            'description' => 'Parent description',
            'priority' => 'medium',
            'status' => 'pending',
            'subtasks' => [
                [
                    'title' => 'Subtask 1',
                    'priority' => 'medium', // Minimum allowed
                    'status' => 'pending',
                ],
                [
                    'title' => 'Subtask 2',
                    'priority' => 'high', // Higher than minimum (allowed)
                    'status' => 'pending',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify subtasks were created with correct priorities
        $createdTask = Task::where('title', 'Parent Task')->first();
        $subtasks = $createdTask->children;

        $this->assertEquals(2, $subtasks->count());
        $this->assertEquals('medium', $subtasks->get(0)->priority);
        $this->assertEquals('high', $subtasks->get(1)->priority);
    }
}
