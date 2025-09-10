<?php

namespace Tests\Unit;

use App\Models\WaitlistSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistSubscriberTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_waitlist_subscriber(): void
    {
        $subscriber = WaitlistSubscriber::create([
            'email' => 'test@example.com',
        ]);

        $this->assertInstanceOf(WaitlistSubscriber::class, $subscriber);
        $this->assertEquals('test@example.com', $subscriber->email);
        $this->assertNotNull($subscriber->id);
    }

    public function test_waitlist_subscriber_has_correct_fillable_attributes(): void
    {
        $subscriber = new WaitlistSubscriber();
        $fillable = $subscriber->getFillable();

        $this->assertIsArray($fillable);
        $this->assertContains('email', $fillable);
        $this->assertCount(1, $fillable);
    }

    public function test_waitlist_subscriber_uses_timestamps(): void
    {
        $subscriber = new WaitlistSubscriber();
        $this->assertTrue($subscriber->usesTimestamps());
    }

    public function test_waitlist_subscriber_table_name(): void
    {
        $subscriber = new WaitlistSubscriber();
        $this->assertEquals('waitlist_subscribers', $subscriber->getTable());
    }

    public function test_waitlist_subscriber_primary_key(): void
    {
        $subscriber = new WaitlistSubscriber();
        $this->assertEquals('id', $subscriber->getKeyName());
    }

    public function test_can_mass_assign_email(): void
    {
        $email = 'mass-assign@example.com';

        $subscriber = WaitlistSubscriber::create([
            'email' => $email,
        ]);

        $this->assertEquals($email, $subscriber->email);
    }

    public function test_waitlist_subscriber_attributes_are_correctly_set(): void
    {
        $email = 'attributes@example.com';

        $subscriber = WaitlistSubscriber::create([
            'email' => $email,
        ]);

        $this->assertDatabaseHas('waitlist_subscribers', [
            'email' => $email,
        ]);
    }
}
