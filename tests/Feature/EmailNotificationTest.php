<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewOrganizationMemberNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up organization structure
        $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);
    }

    public function test_organization_creator_receives_notification_when_new_user_joins_existing_organization()
    {
        Notification::fake();

        // Create an organization with a creator
        $creator = User::factory()->create([
            'email' => 'creator@example.com',
        ]);

        $organization = Organization::factory()->create([
            'domain' => 'example.com',
            'creator_id' => $creator->id,
        ]);

        // Create default roles for the organization
        $roles = $organization->createDefaultRoles();

        // Assign creator to organization and admin role
        $creator->update(['organization_id' => $organization->id, 'pending_approval' => false]);
        $creator->assignRole($roles['admin']);

        // New user tries to register with organization email
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => true,
        ]);

        // Should be redirected to login with pending status
        $response->assertRedirect('/login');

        // Verify the new user was created as pending
        $newUser = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->pending_approval);
        $this->assertEquals($organization->id, $newUser->organization_id);

        // Verify notification was sent to organization creator
        Notification::assertSentTo(
            $creator,
            NewOrganizationMemberNotification::class,
            function ($notification) use ($newUser, $organization) {
                return $notification->newUser->id === $newUser->id
                    && $notification->organization->id === $organization->id;
            }
        );
    }

    public function test_organization_creator_receives_notification_when_user_confirms_joining()
    {
        Notification::fake();

        // Create an organization with a creator
        $creator = User::factory()->create([
            'email' => 'creator@example.com',
        ]);

        $organization = Organization::factory()->create([
            'domain' => 'example.com',
            'creator_id' => $creator->id,
        ]);

        // Create default roles for the organization
        $roles = $organization->createDefaultRoles();

        // Assign creator to organization and admin role
        $creator->update(['organization_id' => $organization->id, 'pending_approval' => false]);
        $creator->assignRole($roles['admin']);

        // Simulate the registration flow where user initially doesn't check organization email
        // but then confirms they want to join
        session([
            'registration_data' => [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => Hash::make('password'),
            ],
            'existing_organization' => $organization,
        ]);

        // User confirms they want to join the organization
        $response = $this->post('/registration/confirm-organization', [
            'join_organization' => true,
        ]);

        $response->assertRedirect('/login');

        // Verify the new user was created as pending
        $newUser = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->pending_approval);
        $this->assertEquals($organization->id, $newUser->organization_id);

        // Verify notification was sent to organization creator
        Notification::assertSentTo(
            $creator,
            NewOrganizationMemberNotification::class,
            function ($notification) use ($newUser, $organization) {
                return $notification->newUser->id === $newUser->id
                    && $notification->organization->id === $organization->id;
            }
        );
    }

    public function test_no_notification_sent_when_user_registers_individually()
    {
        Notification::fake();

        // Create an organization with a creator
        $creator = User::factory()->create([
            'email' => 'creator@example.com',
        ]);

        $organization = Organization::factory()->create([
            'domain' => 'example.com',
            'creator_id' => $creator->id,
        ]);

        // User registers without organization email checkbox (using different domain)
        $response = $this->post('/register', [
            'name' => 'Individual User',
            'email' => 'individual@different.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        // Since there's no organization with this domain, user should be registered individually
        $response->assertRedirect('/dashboard');

        // Verify no notification was sent to any organization creator
        Notification::assertNothingSent();
    }

    public function test_notification_contains_correct_information()
    {
        // Create users and organization
        $creator = User::factory()->create(['name' => 'Organization Creator']);
        $newUser = User::factory()->create(['name' => 'New Member', 'email' => 'new@example.com']);
        $organization = Organization::factory()->create(['name' => 'Test Organization']);

        // Create the notification
        $notification = new NewOrganizationMemberNotification($newUser, $organization);

        // Test the mail representation
        $mailMessage = $notification->toMail($creator);

        $this->assertEquals("New member request for Test Organization", $mailMessage->subject);
        $this->assertStringContainsString("Hello Organization Creator!", $mailMessage->greeting);
        $this->assertStringContainsString("Test Organization", $mailMessage->introLines[0]);
        $this->assertStringContainsString("New Member", $mailMessage->introLines[2]);
        $this->assertStringContainsString("new@example.com", $mailMessage->introLines[3]);

        // Test the array representation
        $arrayData = $notification->toArray($creator);
        $this->assertEquals($newUser->id, $arrayData['new_user_id']);
        $this->assertEquals($newUser->name, $arrayData['new_user_name']);
        $this->assertEquals($newUser->email, $arrayData['new_user_email']);
        $this->assertEquals($organization->id, $arrayData['organization_id']);
        $this->assertEquals($organization->name, $arrayData['organization_name']);
    }

    public function test_no_notification_sent_when_organization_has_no_creator()
    {
        Notification::fake();

        // Create an organization without a creator (like the default 'None' organization)
        $organization = Organization::factory()->create([
            'domain' => 'orphan.com',
            'creator_id' => null,
        ]);

        // Create default roles for the organization
        $roles = $organization->createDefaultRoles();

        // Simulate user registration with organization email
        session([
            'registration_data' => [
                'name' => 'New User',
                'email' => 'newuser@orphan.com',
                'password' => Hash::make('password'),
            ],
            'existing_organization' => $organization,
        ]);

        $response = $this->post('/registration/confirm-organization', [
            'join_organization' => true,
        ]);

        $response->assertRedirect('/login');

        // Verify no notification was sent since there's no creator
        Notification::assertNothingSent();
    }
}
