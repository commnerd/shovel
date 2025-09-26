<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Legacy DailyCurationJob - now delegates to the enhanced UserCurationJob
 *
 * This job is kept for backward compatibility with existing code and tests.
 * It now simply delegates to the new UserCurationJob which provides
 * enhanced functionality including user task history analysis.
 */
class DailyCurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('DailyCurationJob delegating to UserCurationJob', ['user_id' => $this->user->id]);

        // Delegate to the enhanced UserCurationJob
        UserCurationJob::dispatch($this->user);
    }
}
