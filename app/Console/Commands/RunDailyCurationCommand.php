<?php

namespace App\Console\Commands;

use App\Jobs\ScheduleUserCurationJob;
use App\Jobs\AutoCreateIterationJob;
use App\Models\User;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunDailyCurationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'curation:daily
                            {--user-id= : Run for specific user ID only}
                            {--project-id= : Run for specific project ID only}
                            {--dry-run : Show what would be processed without actually running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run daily task curation and auto-create iterations for users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting daily curation and iteration management...');

        $dryRun = $this->option('dry-run');
        $specificUserId = $this->option('user-id');
        $specificProjectId = $this->option('project-id');

        try {
            // Process daily curation for users
            $this->processDailyCuration($specificUserId, $dryRun);

            // Process auto-iteration creation for projects
            $this->processAutoIterations($specificProjectId, $dryRun);

            $this->info('Daily curation and iteration management completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Daily curation failed: ' . $e->getMessage());
            Log::error('Daily curation command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Process daily curation for users.
     */
    protected function processDailyCuration(?int $specificUserId, bool $dryRun): void
    {
        $this->info('Processing daily curation for users...');

        if ($specificUserId) {
            // For specific user, we can still dispatch individual UserCurationJob
            $user = User::find($specificUserId);
            if (!$user) {
                $this->error("User with ID {$specificUserId} not found");
                return;
            }

            if ($dryRun) {
                $this->line("Would process curation for specific user {$user->id} ({$user->name})");
            } else {
                \App\Jobs\UserCurationJob::dispatch($user);
                $this->line("Queued curation for user {$user->id} ({$user->name})");
            }
        } else {
            // For all users, dispatch the scheduled job
            if ($dryRun) {
                $userCount = User::whereNotNull('email_verified_at')
                    ->where('pending_approval', false)
                    ->count();
                $this->line("Would dispatch ScheduleUserCurationJob for {$userCount} users");
            } else {
                ScheduleUserCurationJob::dispatch();
                $this->line("Dispatched ScheduleUserCurationJob to process all users");
            }
        }
    }

    /**
     * Process auto-iteration creation for projects.
     */
    protected function processAutoIterations(?int $specificProjectId, bool $dryRun): void
    {
        $this->info('Processing auto-iteration creation for projects...');

        $projectsQuery = Project::query()
            ->where('project_type', 'iterative')
            ->where('auto_create_iterations', true)
            ->where('status', 'active')
            ->whereNotNull('default_iteration_length_weeks')
            ->where('default_iteration_length_weeks', '>', 0);

        if ($specificProjectId) {
            $projectsQuery->where('id', $specificProjectId);
        }

        $projects = $projectsQuery->with(['iterations' => function ($query) {
            $query->orderBy('end_date', 'desc');
        }])->get();

        $this->info("Found {$projects->count()} iterative projects with auto-create enabled");

        $processedCount = 0;

        foreach ($projects as $project) {
            if ($dryRun) {
                $currentIteration = $project->getCurrentIteration();
                $status = $currentIteration
                    ? "Current: {$currentIteration->name} (ends {$currentIteration->end_date})"
                    : "No current iteration";

                $this->line("Would check project {$project->id} ({$project->title}) - {$status}");
            } else {
                AutoCreateIterationJob::dispatch($project);
                $this->line("Queued iteration check for project {$project->id} ({$project->title})");
            }

            $processedCount++;
        }

        $this->info("Auto-iteration: {$processedCount} projects processed");
    }
}
