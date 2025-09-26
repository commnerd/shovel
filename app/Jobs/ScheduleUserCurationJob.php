<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScheduleUserCurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting scheduled user curation job');

            // Get all users who should receive daily curation
            $users = User::where('pending_approval', false)
                ->whereNotNull('approved_at')
                ->whereNotNull('email_verified_at')
                ->get();

            Log::info('Found users for curation', ['user_count' => $users->count()]);

            // Queue individual curation jobs for each user
            foreach ($users as $user) {
                UserCurationJob::dispatch($user);

                Log::info('Queued curation job for user', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'organization_id' => $user->organization_id
                ]);
            }

            Log::info('Scheduled user curation job completed', [
                'total_users_queued' => $users->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Scheduled user curation job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
