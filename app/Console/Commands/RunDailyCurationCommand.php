<?php

namespace App\Console\Commands;

use App\Jobs\DailyCurationJob;
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

        $usersQuery = User::query()
            ->whereNotNull('email_verified_at')
            ->where('pending_approval', false);

        if ($specificUserId) {
            $usersQuery->where('id', $specificUserId);
        }

        $users = $usersQuery->with(['projects' => function ($query) {
            $query->where('status', 'active');
        }])->get();

        $this->info("Found {$users->count()} users to process");

        $processedCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            // Skip users without active projects
            if ($user->projects->isEmpty()) {
                $skippedCount++;
                if ($this->output->isVerbose()) {
                    $this->line("Skipping user {$user->id} ({$user->name}) - no active projects");
                }
                continue;
            }

            if ($dryRun) {
                $this->line("Would process curation for user {$user->id} ({$user->name}) with {$user->projects->count()} active projects");
            } else {
                DailyCurationJob::dispatch($user);
                $this->line("Queued curation for user {$user->id} ({$user->name})");
            }

            $processedCount++;
        }

        $this->info("Daily curation: {$processedCount} users processed, {$skippedCount} skipped");
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
