<?php

use App\Jobs\DailyCurationJob;
use App\Jobs\UserCurationJob;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->organization = Organization::factory()->create([
        'name' => 'Test Organization',
        'domain' => 'test-org-' . uniqid() . '.com'
    ]);

    $this->user = User::factory()->create([
        'organization_id' => $this->organization->id,
        'pending_approval' => false,
        'approved_at' => now(),
    ]);
});

it('delegates to user curation job', function () {
    // Execute the legacy DailyCurationJob
    $job = new DailyCurationJob($this->user);
    $job->handle();

    // Assert that UserCurationJob was dispatched
    Queue::assertPushed(UserCurationJob::class, function ($job) {
        $reflection = new ReflectionClass($job);
        $userProperty = $reflection->getProperty('user');
        $userProperty->setAccessible(true);
        $user = $userProperty->getValue($job);
        return $user->id === $this->user->id;
    });
});

it('can be instantiated with a user', function () {
    $job = new DailyCurationJob($this->user);

    expect($job)->toBeInstanceOf(DailyCurationJob::class);
});

it('handles multiple users correctly', function () {
    // Create another user
    $user2 = User::factory()->create([
        'organization_id' => $this->organization->id,
        'pending_approval' => false,
        'approved_at' => now(),
    ]);

    // Execute DailyCurationJob for both users
    $job1 = new DailyCurationJob($this->user);
    $job1->handle();

    $job2 = new DailyCurationJob($user2);
    $job2->handle();

    // Assert that UserCurationJob was queued for both users
    Queue::assertPushed(UserCurationJob::class, 2);
});

it('preserves user object correctly', function () {
    // Execute the legacy DailyCurationJob
    $job = new DailyCurationJob($this->user);
    $job->handle();

    // Assert that the same user object was passed to UserCurationJob
    Queue::assertPushed(UserCurationJob::class, function ($job) {
        $reflection = new ReflectionClass($job);
        $userProperty = $reflection->getProperty('user');
        $userProperty->setAccessible(true);
        $user = $userProperty->getValue($job);
        return $user->id === $this->user->id &&
               $user->name === $this->user->name &&
               $user->organization_id === $this->user->organization_id;
    });
});
