<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Organization $organization,
        public User $approvedBy
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Welcome to {$this->organization->name}! Your membership has been approved")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Great news! Your request to join '{$this->organization->name}' has been approved.")
            ->line("You now have full access to your organization's projects and resources.")
            ->line("**Approval Details:**")
            ->line("Organization: {$this->organization->name}")
            ->line("Approved by: {$this->approvedBy->name}")
            ->line("Approved on: " . now()->format('F j, Y \a\t g:i A'))
            ->action('Access Dashboard', url('/dashboard'))
            ->line('You can now log in and start collaborating with your team!')
            ->line('Welcome to the team!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'approved_by_id' => $this->approvedBy->id,
            'approved_by_name' => $this->approvedBy->name,
            'approved_at' => now()->toISOString(),
        ];
    }
}
