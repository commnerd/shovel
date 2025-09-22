<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Iteration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AutoCreateIterationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Project $project;

    /**
     * Create a new job instance.
     */
    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Checking auto iteration creation for project', [
                'project_id' => $this->project->id,
                'project_type' => $this->project->project_type
            ]);

            // Only process iterative projects with auto-create enabled
            if (!$this->shouldCreateIteration()) {
                Log::info('Skipping iteration creation', [
                    'project_id' => $this->project->id,
                    'reason' => 'Auto-create not enabled or not iterative project'
                ]);
                return;
            }

            $currentIteration = $this->project->getCurrentIteration();
            
            // Check if we need to create a new iteration
            if ($this->needsNewIteration($currentIteration)) {
                $newIteration = $this->createNextIteration($currentIteration);
                
                if ($newIteration) {
                    Log::info('Auto-created new iteration', [
                        'project_id' => $this->project->id,
                        'iteration_id' => $newIteration->id,
                        'iteration_name' => $newIteration->name
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Auto iteration creation failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check if this project should have iterations auto-created.
     */
    protected function shouldCreateIteration(): bool
    {
        return $this->project->project_type === 'iterative' 
            && $this->project->auto_create_iterations 
            && $this->project->default_iteration_length_weeks > 0;
    }

    /**
     * Check if a new iteration needs to be created.
     */
    protected function needsNewIteration(?Iteration $currentIteration): bool
    {
        // If no current iteration exists, create the first one
        if (!$currentIteration) {
            Log::info('No current iteration found, creating first iteration', [
                'project_id' => $this->project->id
            ]);
            return true;
        }

        // Check if current iteration is ending soon (within 1 day)
        $today = Carbon::now();
        $iterationEndDate = Carbon::parse($currentIteration->end_date);
        
        if ($iterationEndDate->diffInDays($today, false) <= 1) {
            Log::info('Current iteration ending soon, creating next iteration', [
                'project_id' => $this->project->id,
                'current_iteration_id' => $currentIteration->id,
                'end_date' => $currentIteration->end_date,
                'days_remaining' => $iterationEndDate->diffInDays($today, false)
            ]);
            return true;
        }

        return false;
    }

    /**
     * Create the next iteration for the project.
     */
    protected function createNextIteration(?Iteration $currentIteration): ?Iteration
    {
        try {
            // If there's a current iteration, complete it first
            if ($currentIteration && $currentIteration->status === 'active') {
                $this->completeCurrentIteration($currentIteration);
            }

            // Calculate dates for the new iteration
            $startDate = $currentIteration 
                ? Carbon::parse($currentIteration->end_date)->addDay()
                : Carbon::now();

            $endDate = $startDate->copy()->addWeeks($this->project->default_iteration_length_weeks);

            // Generate iteration name
            $iterationCount = $this->project->iterations()->count() + 1;
            $iterationName = $this->generateIterationName($iterationCount);

            // Create the new iteration
            $newIteration = Iteration::create([
                'project_id' => $this->project->id,
                'name' => $iterationName,
                'description' => "Auto-generated iteration {$iterationCount}",
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'status' => 'active',
                'capacity_points' => $this->calculateIterationCapacity($currentIteration),
                'committed_points' => 0,
                'completed_points' => 0,
                'sort_order' => $iterationCount,
                'goals' => $this->generateIterationGoals($currentIteration),
            ]);

            // Move appropriate backlog tasks to the new iteration
            $this->populateIterationWithTasks($newIteration);

            return $newIteration;

        } catch (\Exception $e) {
            Log::error('Failed to create next iteration', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Complete the current iteration.
     */
    protected function completeCurrentIteration(Iteration $currentIteration): void
    {
        $currentIteration->update([
            'status' => 'completed',
            'completed_points' => $currentIteration->getCompletedPoints(),
        ]);

        // Update points from tasks to ensure accuracy
        $currentIteration->updatePointsFromTasks();

        Log::info('Completed current iteration', [
            'iteration_id' => $currentIteration->id,
            'completed_points' => $currentIteration->completed_points,
            'committed_points' => $currentIteration->committed_points
        ]);
    }

    /**
     * Generate a name for the new iteration.
     */
    protected function generateIterationName(int $iterationCount): string
    {
        // You can customize this logic based on your naming preferences
        $startDate = Carbon::now();
        
        // Format: "Sprint 1 (Dec 2024)" or "Iteration 1 (Dec 2024)"
        $prefix = $this->project->project_type === 'iterative' ? 'Sprint' : 'Iteration';
        $monthYear = $startDate->format('M Y');
        
        return "{$prefix} {$iterationCount} ({$monthYear})";
    }

    /**
     * Calculate capacity for the new iteration based on historical data.
     */
    protected function calculateIterationCapacity(?Iteration $previousIteration): ?int
    {
        if (!$previousIteration) {
            // Default capacity for first iteration (could be configurable)
            return 20; // Default story points capacity
        }

        // Use the previous iteration's capacity as a starting point
        $previousCapacity = $previousIteration->capacity_points ?? 20;
        $previousVelocity = $previousIteration->completed_points ?? 0;

        // Adjust capacity based on previous velocity (simple heuristic)
        if ($previousVelocity > $previousCapacity * 0.9) {
            // High completion rate, slightly increase capacity
            return min($previousCapacity + 5, $previousCapacity * 1.2);
        } elseif ($previousVelocity < $previousCapacity * 0.6) {
            // Low completion rate, slightly decrease capacity
            return max($previousCapacity - 5, $previousCapacity * 0.8);
        }

        // Maintain same capacity
        return $previousCapacity;
    }

    /**
     * Generate goals for the new iteration.
     */
    protected function generateIterationGoals(?Iteration $previousIteration): array
    {
        $goals = [];

        // Add basic goals based on project type and backlog
        $backlogTasksCount = $this->project->getBacklogTasks()->count();
        
        if ($backlogTasksCount > 0) {
            $goals[] = "Complete high-priority backlog items";
        }

        // Check for overdue tasks
        $overdueTasks = $this->project->tasks()
            ->where('due_date', '<', Carbon::now())
            ->where('status', '!=', 'completed')
            ->count();

        if ($overdueTasks > 0) {
            $goals[] = "Address {$overdueTasks} overdue tasks";
        }

        // Add project milestone goals if near completion
        $totalTasks = $this->project->tasks()->count();
        $completedTasks = $this->project->tasks()->where('status', 'completed')->count();
        $completionPercentage = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

        if ($completionPercentage > 75) {
            $goals[] = "Focus on project completion and final deliverables";
        } elseif ($completionPercentage > 50) {
            $goals[] = "Maintain momentum and address remaining core features";
        } else {
            $goals[] = "Build foundation and core functionality";
        }

        return $goals;
    }

    /**
     * Populate the new iteration with appropriate tasks from the backlog.
     */
    protected function populateIterationWithTasks(Iteration $iteration): void
    {
        // Get backlog tasks ordered by priority (you may want to enhance this logic)
        $backlogTasks = $this->project->getBacklogTasks()
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc') // Could be enhanced with priority ordering
            ->get();

        $capacityRemaining = $iteration->capacity_points ?? 20;
        $committedPoints = 0;

        foreach ($backlogTasks as $task) {
            // Only move tasks that fit within capacity
            $taskPoints = $task->current_story_points ?? 1;
            
            if ($taskPoints <= $capacityRemaining) {
                $task->moveToIteration($iteration);
                $committedPoints += $taskPoints;
                $capacityRemaining -= $taskPoints;

                Log::info('Moved task to new iteration', [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'points' => $taskPoints,
                    'iteration_id' => $iteration->id
                ]);

                // Stop if we've filled the iteration
                if ($capacityRemaining <= 0) {
                    break;
                }
            }
        }

        // Update the iteration's committed points
        $iteration->update(['committed_points' => $committedPoints]);

        Log::info('Populated iteration with tasks', [
            'iteration_id' => $iteration->id,
            'committed_points' => $committedPoints,
            'capacity_points' => $iteration->capacity_points
        ]);
    }
}
