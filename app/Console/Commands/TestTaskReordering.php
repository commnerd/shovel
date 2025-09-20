<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Task, Project, User};

class TestTaskReordering extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'task:test-reordering {--project-id= : Project ID to test with}';

    /**
     * The console command description.
     */
    protected $description = 'Test task reordering functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projectId = $this->option('project-id');

        if (!$projectId) {
            // Find a project with tasks
            $project = Project::whereHas('tasks')->first();
            if (!$project) {
                $this->error('No project with tasks found. Please create some tasks first.');
                return 1;
            }
            $projectId = $project->id;
        } else {
            $project = Project::find($projectId);
            if (!$project) {
                $this->error("Project with ID {$projectId} not found.");
                return 1;
            }
        }

        $this->info("Testing task reordering for project: {$project->name} (ID: {$project->id})");

        // Get top-level tasks ordered by sort_order
        $tasks = $project->tasks()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        if ($tasks->count() < 2) {
            $this->error('Need at least 2 top-level tasks to test reordering.');
            return 1;
        }

        $this->info("Found {$tasks->count()} top-level tasks:");
        foreach ($tasks as $task) {
            $this->line("  ID: {$task->id}, Title: {$task->title}, Sort Order: {$task->sort_order}");
        }

        // Test reordering: move the first task to position 2
        $taskToMove = $tasks->first();
        $originalPosition = $taskToMove->sort_order;
        $newPosition = 2;

        $this->info("\nTesting: Moving task '{$taskToMove->title}' from position {$originalPosition} to position {$newPosition}");

        try {
            $result = $taskToMove->reorderTo($newPosition);

            if ($result['success']) {
                $this->info("✓ Reorder successful: {$result['message']}");

                // Verify the change
                $taskToMove->refresh();
                $this->info("✓ Task position updated: {$taskToMove->sort_order}");

                // Check all tasks are in correct order
                $updatedTasks = $project->tasks()
                    ->whereNull('parent_id')
                    ->orderBy('sort_order')
                    ->get();

                $this->info("\nUpdated task order:");
                foreach ($updatedTasks as $task) {
                    $this->line("  ID: {$task->id}, Title: {$task->title}, Sort Order: {$task->sort_order}");
                }

                // Verify no duplicate sort_orders
                $sortOrders = $updatedTasks->pluck('sort_order')->toArray();
                $uniqueOrders = array_unique($sortOrders);

                if (count($sortOrders) === count($uniqueOrders)) {
                    $this->info("✓ No duplicate sort orders");
                } else {
                    $this->error("✗ Found duplicate sort orders: " . implode(', ', array_diff_assoc($sortOrders, $uniqueOrders)));
                }

                $this->info("\n✅ Task reordering test completed successfully!");
                return 0;

            } else {
                $this->error("✗ Reorder failed: {$result['message']}");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("✗ Exception during reorder: " . $e->getMessage());
            return 1;
        }
    }
}
