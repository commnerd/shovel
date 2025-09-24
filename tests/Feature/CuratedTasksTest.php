<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Project;
use App\Models\CuratedTasks;
use App\Jobs\DailyCurationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuratedTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_curated_tasks_can_be_created(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $curatedTask = CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'work_date' => now()->toDateString(),
            'assigned_to' => $user->id,
            'initial_index' => 1,
            'current_index' => 1,
            'moved_count' => 0,
        ]);

        $this->assertDatabaseHas('curated_tasks', [
            'id' => $curatedTask->id,
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'assigned_to' => $user->id,
        ]);
    }

    public function test_curated_tasks_polymorphic_relationship(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $curatedTask = CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'work_date' => now()->toDateString(),
            'assigned_to' => $user->id,
            'initial_index' => 1,
            'current_index' => 1,
            'moved_count' => 0,
        ]);

        $this->assertInstanceOf(Task::class, $curatedTask->curatable);
        $this->assertEquals($task->id, $curatedTask->curatable->id);
    }

    public function test_curated_tasks_assigned_user_relationship(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $curatedTask = CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'work_date' => now()->toDateString(),
            'assigned_to' => $user->id,
            'initial_index' => 1,
            'current_index' => 1,
            'moved_count' => 0,
        ]);

        $this->assertInstanceOf(User::class, $curatedTask->assignedUser);
        $this->assertEquals($user->id, $curatedTask->assignedUser->id);
    }

    public function test_curated_tasks_scopes(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Create curated tasks for today and yesterday
        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'work_date' => $today,
            'assigned_to' => $user->id,
            'initial_index' => 1,
            'current_index' => 1,
            'moved_count' => 0,
        ]);

        CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'work_date' => $yesterday,
            'assigned_to' => $user->id,
            'initial_index' => 1,
            'current_index' => 1,
            'moved_count' => 0,
        ]);

        // Test today scope
        $todayTasks = CuratedTasks::today()->get();
        $this->assertCount(1, $todayTasks);
        $this->assertEquals($today, $todayTasks->first()->work_date->format('Y-m-d'));

        // Test forWorkDate scope
        $yesterdayTasks = CuratedTasks::forWorkDate($yesterday)->get();
        $this->assertCount(1, $yesterdayTasks);
        $this->assertEquals($yesterday, $yesterdayTasks->first()->work_date->format('Y-m-d'));

        // Test forUser scope
        $userTasks = CuratedTasks::forUser($user->id)->get();
        $this->assertCount(2, $userTasks);
    }

    public function test_curated_tasks_index_tracking(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $curatedTask = CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'work_date' => now()->toDateString(),
            'assigned_to' => $user->id,
            'initial_index' => 1,
            'current_index' => 1,
            'moved_count' => 0,
        ]);

        // Update index
        $curatedTask->updateIndex(3);
        $curatedTask->refresh();

        $this->assertEquals(3, $curatedTask->current_index);
        $this->assertEquals(1, $curatedTask->moved_count);

        // Update index again
        $curatedTask->updateIndex(5);
        $curatedTask->refresh();

        $this->assertEquals(5, $curatedTask->current_index);
        $this->assertEquals(2, $curatedTask->moved_count);

        // Reset index
        $curatedTask->resetIndex();
        $curatedTask->refresh();

        $this->assertEquals(1, $curatedTask->current_index);
        $this->assertEquals(2, $curatedTask->moved_count); // moved_count should not reset
    }

    public function test_task_curated_tasks_relationship(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $curatedTask = CuratedTasks::create([
            'curatable_type' => Task::class,
            'curatable_id' => $task->id,
            'work_date' => now()->toDateString(),
            'assigned_to' => $user->id,
            'initial_index' => 1,
            'current_index' => 1,
            'moved_count' => 0,
        ]);

        $this->assertTrue($task->curatedTasks()->exists());
        $this->assertEquals($curatedTask->id, $task->curatedTasks()->first()->id);
    }

    public function test_daily_curation_job_populates_curated_tasks(): void
    {
        // Create user and project with tasks
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $task1 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'due_date' => now()->addDay(),
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'due_date' => now()->subDay(), // Overdue task
        ]);

        // Mock AI to return suggestions
        $this->mockAIResponse([
            'suggestions' => [
                [
                    'type' => 'priority',
                    'task_id' => $task1->id,
                    'message' => 'Focus on this task today'
                ],
                [
                    'type' => 'risk',
                    'task_id' => $task2->id,
                    'message' => 'This task is overdue'
                ]
            ],
            'summary' => 'Test curation summary',
            'focus_areas' => ['priority_tasks', 'overdue_tasks']
        ]);

        // Run the daily curation job
        $job = new DailyCurationJob($user);
        $job->handle();

        // Verify CuratedTasks were created
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

        // Verify index tracking
        $curatedTask1 = CuratedTasks::where('curatable_id', $task1->id)->first();
        $curatedTask2 = CuratedTasks::where('curatable_id', $task2->id)->first();

        $this->assertEquals(1, $curatedTask1->initial_index);
        $this->assertEquals(1, $curatedTask1->current_index);
        $this->assertEquals(0, $curatedTask1->moved_count);

        $this->assertEquals(2, $curatedTask2->initial_index);
        $this->assertEquals(2, $curatedTask2->current_index);
        $this->assertEquals(0, $curatedTask2->moved_count);
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
