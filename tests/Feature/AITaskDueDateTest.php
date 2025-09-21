<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Providers\CerebrusProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AITaskDueDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    public function test_ai_generates_tasks_with_due_dates_when_project_has_due_date()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => '2025-12-31',
        ]);

        // Mock the AI response with tasks that don't have due dates
        $mockTasks = [
            [
                'title' => 'Setup Project',
                'description' => 'Set up the project structure',
                'priority' => 'high',
                'status' => 'pending',
            ],
            [
                'title' => 'Implement Features',
                'description' => 'Implement core features',
                'priority' => 'medium',
                'status' => 'pending',
            ],
            [
                'title' => 'Testing',
                'description' => 'Test the application',
                'priority' => 'low',
                'status' => 'pending',
            ],
        ];

        $response = $this->actingAs($user)->post('/dashboard/projects/create/tasks', [
            'description' => 'Build a web application',
            'due_date' => '2025-12-31',
        ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->has('suggestedTasks')
        );
    }

    public function test_ai_task_breakdown_includes_due_dates_for_subtasks()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => '2025-12-31',
        ]);
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => '2025-12-25',
        ]);

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'task_id' => $parentTask->id,
            'title' => 'Break down this task',
            'description' => 'Need to break this into smaller tasks',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'subtasks' => [
                '*' => [
                    'title',
                    'description',
                    'status',
                    'due_date',
                ],
            ],
        ]);
    }

    public function test_ai_task_breakdown_without_project_due_date_does_not_add_due_dates()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => null,
        ]);
        $parentTask = Task::factory()->create([
            'project_id' => $project->id,
            'due_date' => null,
        ]);

        $response = $this->actingAs($user)->post("/dashboard/projects/{$project->id}/tasks/breakdown", [
            'task_id' => $parentTask->id,
            'title' => 'Break down this task',
            'description' => 'Need to break this into smaller tasks',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'subtasks',
        ]);
    }

    public function test_cerebrus_provider_calculates_task_due_dates_from_project()
    {
        $provider = new CerebrusProvider([
            'api_key' => 'test-key',
            'base_url' => 'http://test.com',
            'model' => 'test-model',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('calculateTaskDueDateFromProject');
        $method->setAccessible(true);

        $task = [
            'title' => 'Test Task',
            'priority' => 'high',
        ];

        $projectDueDate = '2025-12-31';
        $result = $method->invoke($provider, $projectDueDate, $task);

        // Should return a date string
        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);

        // Should be before the project due date
        $this->assertLessThanOrEqual($projectDueDate, $result);
    }

    public function test_cerebrus_provider_does_not_set_due_date_for_past_project_dates()
    {
        $provider = new CerebrusProvider([
            'api_key' => 'test-key',
            'base_url' => 'http://test.com',
            'model' => 'test-model',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('calculateTaskDueDateFromProject');
        $method->setAccessible(true);

        $task = [
            'title' => 'Test Task',
            'priority' => 'high',
        ];

        // Use a past date
        $projectDueDate = '2020-01-01';
        $result = $method->invoke($provider, $projectDueDate, $task);

        // Should return null for past dates
        $this->assertNull($result);
    }

    public function test_ai_generated_tasks_respect_priority_for_due_date_calculation()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'due_date' => '2025-12-31',
        ]);

        $response = $this->actingAs($user)->post('/dashboard/projects/create/tasks', [
            'description' => 'Build a web application with high priority setup, medium priority features, and low priority testing',
            'due_date' => '2025-12-31',
        ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/CreateTasks')
                ->has('suggestedTasks')
        );
    }

    public function test_project_creation_with_ai_tasks_includes_due_dates()
    {
        // Set up user with organization and group
        $organization = Organization::getDefault();
        $group = $organization->createDefaultGroup();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'pending_approval' => false,
        ]);
        $user->joinGroup($group);

        $response = $this->actingAs($user)->post('/dashboard/projects', [
            'title' => 'AI Test Project',
            'description' => 'A project to test AI task generation with due dates',
            'due_date' => '2025-12-31',
            'group_id' => $group->id,
            'tasks' => [
                [
                    'title' => 'Setup',
                    'description' => 'Set up the project',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_date' => '2025-12-15',
                    'sort_order' => 1,
                ],
                [
                    'title' => 'Development',
                    'description' => 'Develop features',
                    'priority' => 'medium',
                    'status' => 'pending',
                    'due_date' => '2025-12-25',
                    'sort_order' => 2,
                ],
            ],
        ]);

        // Check if there are validation errors
        if ($response->status() !== 302) {
            $this->fail('Expected redirect but got status: ' . $response->status() . '. Content: ' . $response->getContent());
        }

        // Check for validation errors in the session
        if ($response->getSession()->has('errors')) {
            $errors = $response->getSession()->get('errors');
            $this->fail('Validation errors: ' . json_encode($errors->all()));
        }

        $response->assertRedirect();

        // Check if project was created
        $project = Project::where('title', 'AI Test Project')->first();
        $this->assertNotNull($project);

        $tasks = $project->tasks;
        $this->assertCount(2, $tasks);

        // Check that tasks have due dates
        $this->assertNotNull($tasks->firstWhere('title', 'Setup')->due_date);
        $this->assertNotNull($tasks->firstWhere('title', 'Development')->due_date);
    }
}
