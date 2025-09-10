<?php

namespace Tests\Feature;

use App\Models\WaitlistSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Landing'));
    }

    public function test_can_subscribe_to_waitlist_with_valid_email(): void
    {
        $email = 'test@example.com';

        $response = $this->post('/waitlist', [
            'email' => $email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Thanks! We\'ll be in touch soon.');

        $this->assertDatabaseHas('waitlist_subscribers', [
            'email' => $email,
        ]);
    }

    public function test_cannot_subscribe_with_invalid_email(): void
    {
        $response = $this->post('/waitlist', [
            'email' => 'invalid-email',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('waitlist_subscribers', 0);
    }

    public function test_cannot_subscribe_without_email(): void
    {
        $response = $this->post('/waitlist', []);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('waitlist_subscribers', 0);
    }

    public function test_cannot_subscribe_with_duplicate_email(): void
    {
        $email = 'test@example.com';

        // Create existing subscriber
        WaitlistSubscriber::create(['email' => $email]);

        $response = $this->post('/waitlist', [
            'email' => $email,
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('waitlist_subscribers', 1);
    }

    public function test_can_subscribe_with_different_valid_emails(): void
    {
        $emails = [
            'user1@example.com',
            'user2@example.com',
            'user3@example.com',
        ];

        foreach ($emails as $email) {
            $response = $this->post('/waitlist', [
                'email' => $email,
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('success', 'Thanks! We\'ll be in touch soon.');
        }

        $this->assertDatabaseCount('waitlist_subscribers', 3);
    }

    public function test_email_validation_rules(): void
    {
        $invalidEmails = [
            'not-an-email',
            '@example.com',
            'test@',
            'test.example.com',
            'test@.com',
            'test@example.',
        ];

        foreach ($invalidEmails as $email) {
            $response = $this->post('/waitlist', [
                'email' => $email,
            ]);

            $response->assertSessionHasErrors(['email']);
        }

        $this->assertDatabaseCount('waitlist_subscribers', 0);
    }

    public function test_waitlist_subscriber_model_fillable_attributes(): void
    {
        $subscriber = new WaitlistSubscriber();
        $fillable = $subscriber->getFillable();

        $this->assertContains('email', $fillable);
    }

    public function test_waitlist_subscriber_has_timestamps(): void
    {
        $subscriber = WaitlistSubscriber::create([
            'email' => 'test@example.com',
        ]);

        $this->assertNotNull($subscriber->created_at);
        $this->assertNotNull($subscriber->updated_at);
    }

    public function test_waitlist_subscriber_email_is_unique(): void
    {
        $email = 'unique@example.com';

        // First subscription should work
        $response1 = $this->post('/waitlist', ['email' => $email]);
        $response1->assertRedirect();

        // Second subscription with same email should fail
        $response2 = $this->post('/waitlist', ['email' => $email]);
        $response2->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('waitlist_subscribers', 1);
    }

    public function test_waitlist_controller_uses_correct_validation_rules(): void
    {
        $response = $this->post('/waitlist', [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Test that the validation rules are working
        $response = $this->post('/waitlist', [
            'email' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
