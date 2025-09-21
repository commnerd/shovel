<?php

namespace Tests\Unit;

use App\Services\AI\Providers\CerebrusProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AITaskDueDateCalculationTest extends TestCase
{
    use RefreshDatabase;

    private CerebrusProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new CerebrusProvider([
            'api_key' => 'test-key',
            'base_url' => 'http://test.com',
            'model' => 'test-model',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);
    }

    public function test_all_tasks_get_reasonable_due_dates()
    {
        // Use a fixed future date to avoid timing issues
        $projectDueDate = Carbon::create(2025, 12, 31)->format('Y-m-d');

        $task1 = ['title' => 'Task 1'];
        $task2 = ['title' => 'Task 2'];

        $dueDate1 = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task1);
        $dueDate2 = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task2);

        $this->assertNotNull($dueDate1);
        $this->assertNotNull($dueDate2);

        // Both tasks should get the same due date (60% into timeline)
        $this->assertEquals($dueDate1, $dueDate2);

        // Due date should be in the future
        $this->assertGreaterThan(now()->format('Y-m-d'), $dueDate1);
        $this->assertLessThanOrEqual($projectDueDate, $dueDate1);
    }

    public function test_due_date_calculation_is_consistent()
    {
        $projectDueDate = Carbon::create(2025, 12, 31)->format('Y-m-d');

        $task1 = ['title' => 'Task 1'];
        $task2 = ['title' => 'Task 2'];
        $task3 = ['title' => 'Task 3'];

        $dueDate1 = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task1);
        $dueDate2 = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task2);
        $dueDate3 = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task3);

        $this->assertNotNull($dueDate1);
        $this->assertNotNull($dueDate2);
        $this->assertNotNull($dueDate3);

        // All tasks should get the same due date (60% into timeline)
        $this->assertEquals($dueDate1, $dueDate2);
        $this->assertEquals($dueDate2, $dueDate3);
    }

    public function test_due_date_never_exceeds_project_due_date()
    {
        $projectDueDate = Carbon::create(2025, 10, 15)->format('Y-m-d');

        $task = ['title' => 'Test Task'];
        $taskDueDate = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task);

        $this->assertNotNull($taskDueDate);
        $this->assertLessThanOrEqual($projectDueDate, $taskDueDate);
    }

    public function test_due_date_is_at_least_one_day_from_now()
    {
        $projectDueDate = Carbon::create(2025, 10, 10)->format('Y-m-d');

        $task = ['title' => 'Test Task'];
        $taskDueDate = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task);

        $this->assertNotNull($taskDueDate);

        $taskDate = Carbon::parse($taskDueDate);
        $this->assertGreaterThanOrEqual(Carbon::now()->addDay()->startOfDay(), $taskDate);
    }

    public function test_past_project_due_date_returns_null()
    {
        $pastProjectDueDate = Carbon::create(2020, 1, 1)->format('Y-m-d');

        $task = ['title' => 'Test Task'];
        $taskDueDate = $this->invokeCalculateTaskDueDateFromProject($pastProjectDueDate, $task);

        $this->assertNull($taskDueDate);
    }

    public function test_invalid_date_format_returns_null()
    {
        $invalidDate = 'invalid-date';

        $task = ['title' => 'Test Task'];
        $taskDueDate = $this->invokeCalculateTaskDueDateFromProject($invalidDate, $task);

        $this->assertNull($taskDueDate);
    }

    public function test_task_without_specific_data_gets_default_due_date()
    {
        $projectDueDate = Carbon::create(2025, 12, 31)->format('Y-m-d');

        $task1 = ['title' => 'Task 1'];
        $task2 = ['title' => 'Task 2'];

        $dueDate1 = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task1);
        $dueDate2 = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task2);

        $this->assertNotNull($dueDate1);
        $this->assertNotNull($dueDate2);

        // Both tasks should get the same due date (60% of timeline)
        $this->assertEquals($dueDate1, $dueDate2);
    }

    public function test_very_long_project_timeline_handles_correctly()
    {
        $projectDueDate = Carbon::create(2026, 12, 31)->format('Y-m-d');

        $task1 = ['title' => 'Task 1'];
        $task2 = ['title' => 'Task 2'];

        $dueDate1 = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task1);
        $dueDate2 = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task2);

        $this->assertNotNull($dueDate1);
        $this->assertNotNull($dueDate2);

        // Both tasks should get the same due date even with long timeline
        $this->assertEquals($dueDate1, $dueDate2);
    }

    public function test_very_short_project_timeline_handles_correctly()
    {
        $projectDueDate = Carbon::create(2025, 10, 5)->format('Y-m-d');

        $task = ['title' => 'Test Task'];
        $taskDueDate = $this->invokeCalculateTaskDueDateFromProject($projectDueDate, $task);

        $this->assertNotNull($taskDueDate);
        $this->assertLessThanOrEqual($projectDueDate, $taskDueDate);

        // Should be at least 1 day from now
        $taskDate = Carbon::parse($taskDueDate);
        $this->assertGreaterThanOrEqual(Carbon::now()->addDay()->startOfDay(), $taskDate);
    }

    /**
     * Helper method to invoke the protected calculateTaskDueDateFromProject method
     */
    private function invokeCalculateTaskDueDateFromProject(string $projectDueDate, array $task): ?string
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('calculateTaskDueDateFromProject');
        $method->setAccessible(true);

        return $method->invoke($this->provider, $projectDueDate, $task);
    }
}
