<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Project;
use App\Models\Organization;
use App\Models\CuratedTasks;
use App\Jobs\DailyCurationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationCurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the default organization
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
    }

    public function test_default_organization_user_sees_all_tasks(): void
    {
        // Create user in default organization
        $defaultOrg = Organization::where('is_default', true)->first();
        $user = User::factory()->create(['organization_id' => $defaultOrg->id]);

        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create some tasks
        $task1 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        // Mock AI to return suggestions for both tasks
        $this->mockAIResponse([
            'suggestions' => [
                [
                    'type' => 'priority',
                    'task_id' => $task1->id,
                    'message' => 'Focus on this task today'
                ],
                [
                    'type' => 'priority',
                    'task_id' => $task2->id,
                    'message' => 'Focus on this task today'
                ]
            ],
            'summary' => 'Test curation summary',
            'focus_areas' => ['priority_tasks']
        ]);

        // Run the daily curation job
        $job = new DailyCurationJob($user);
        $job->handle();

        // Verify both tasks were curated
        $this->assertDatabaseHas('curated_tasks', [
            'curatable_type' => Task::class,
            'curatable_id' => $task1->id,
            'assigned_to' => $user->id,
            'work_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('curated_tasks', [
            'curatable_type' => Task::class,
            'curatable_id' => $task2->id,
            'assigned_to' => $user->id,
            'work_date' => now()->toDateString(),
        ]);
    }

    public function test_organization_user_only_sees_unassigned_tasks(): void
    {
        // Create a custom organization
        $customOrg = Organization::factory()->create([
            'name' => 'Custom Organization',
            'is_default' => false
        ]);

        $user = User::factory()->create(['organization_id' => $customOrg->id]);

        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create some tasks
        $task1 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        // Manually assign task1 to today's curated tasks (simulate it was already curated)
        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task1->id,
            'work_date' => now()->toDateString(),
            'assigned_to' => $user->id,
            'initial_index' => 1,
            'current_index' => 1,
            'moved_count' => 0,
        ]);

        // Mock AI to return suggestions for only task2 (task1 is already assigned)
        $this->mockAIResponse([
            'suggestions' => [
                [
                    'type' => 'priority',
                    'task_id' => $task2->id,
                    'message' => 'Focus on this unassigned task today'
                ]
            ],
            'summary' => 'Test curation summary',
            'focus_areas' => ['priority_tasks']
        ]);

        // Run the daily curation job
        $job = new DailyCurationJob($user);
        $job->handle();

        // Verify only task2 was curated (task1 was already assigned)
        $curatedTasks = CuratedTasks::where('assigned_to', $user->id)
            ->where('work_date', now()->toDateString())
            ->get();

        $this->assertCount(1, $curatedTasks);
        $this->assertEquals($task2->id, $curatedTasks->first()->curatable_id);

        // Verify task1 still exists but wasn't re-curated
        $this->assertDatabaseHas('curated_tasks', [
            'curatable_type' => Task::class,
            'curatable_id' => $task1->id,
            'assigned_to' => $user->id,
        ]);

        // But verify only one instance exists (the original one)
        $task1CuratedCount = CuratedTasks::where('curatable_type', Task::class)
            ->where('curatable_id', $task1->id)
            ->where('assigned_to', $user->id)
            ->whereDate('work_date', now())
            ->count();

        $this->assertEquals(1, $task1CuratedCount);
    }

    public function test_organization_user_with_no_unassigned_tasks_gets_no_curation(): void
    {
        // Create a custom organization
        $customOrg = Organization::factory()->create([
            'name' => 'Custom Organization',
            'is_default' => false
        ]);

        $user = User::factory()->create(['organization_id' => $customOrg->id]);

        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create some tasks
        $task1 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        // Manually assign both tasks to today's curated tasks (simulate they were already curated)
        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task1->id,
            'work_date' => now()->toDateString(),
            'assigned_to' => $user->id,
            'initial_index' => 1,
            'current_index' => 1,
            'moved_count' => 0,
        ]);

        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task2->id,
            'work_date' => now()->toDateString(),
            'assigned_to' => $user->id,
            'initial_index' => 2,
            'current_index' => 2,
            'moved_count' => 0,
        ]);

        // Mock AI to return no suggestions (all tasks already assigned)
        $this->mockAIResponse([
            'suggestions' => [],
            'summary' => 'All tasks already assigned',
            'focus_areas' => []
        ]);

        // Run the daily curation job
        $job = new DailyCurationJob($user);
        $job->handle();

        // Verify no new curated tasks were created
        $curatedTasksCount = CuratedTasks::where('assigned_to', $user->id)
            ->whereDate('work_date', now())
            ->count();

        $this->assertEquals(2, $curatedTasksCount); // Only the original 2, no new ones
    }

    public function test_refresh_endpoint_works_for_organization_users(): void
    {
        // Create a custom organization
        $customOrg = Organization::factory()->create([
            'name' => 'Custom Organization',
            'is_default' => false
        ]);

        $user = User::factory()->create(['organization_id' => $customOrg->id]);

        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create a task
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        // Mock AI to return suggestions
        $this->mockAIResponse([
            'suggestions' => [
                [
                    'type' => 'priority',
                    'task_id' => $task->id,
                    'message' => 'Focus on this task today'
                ]
            ],
            'summary' => 'Test curation summary',
            'focus_areas' => ['priority_tasks']
        ]);

        // Call the refresh endpoint
        $response = $this->actingAs($user)
            ->post('/dashboard/todays-tasks/refresh');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Today\'s tasks refreshed successfully'
        ]);

        // Verify the task was curated
        $this->assertDatabaseHas('curated_tasks', [
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'assigned_to' => $user->id,
            'work_date' => now()->toDateString(),
        ]);
    }

    /**
     * Mock AI response for testing.
     */
    private function mockAIResponse(array $response): void
    {
        $mockAI = $this->createMock(\App\Services\AI\Contracts\AIProviderInterface::class);
        $mockResponse = $this->createMock(\App\Services\AI\Contracts\AIResponse::class);

        $mockResponse->method('getContent')
            ->willReturn(json_encode($response));

        $mockAI->method('chat')
            ->willReturn($mockResponse);

        $mockAI->method('isConfigured')
            ->willReturn(true);

        $this->app->instance('ai.provider', $mockAI);
    }
}
