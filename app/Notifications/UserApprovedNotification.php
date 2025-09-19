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

    public User $approvedBy;

    public Organization $organization;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $approvedBy, ?Organization $organization = null)
    {
        $this->approvedBy = $approvedBy;
        $this->organization = $organization ?? $approvedBy->organization;
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
            ->subject('Your account has been approved!')
            ->greeting("Hello {$notifiable->name}!")
            ->line('Great news! Your account has been approved and you can now access the platform.')
            ->line("You were approved by {$this->approvedBy->name} from {$this->organization->name}.")
            ->action('Access Dashboard', url('/dashboard'))
            ->line('You can now create projects, manage tasks, and collaborate with your team.')
            ->line('If you have any questions, please contact your organization administrator.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Your account has been approved',
            'approved_by_id' => $this->approvedBy->id,
            'approved_by_name' => $this->approvedBy->name,
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
        ];
    }
}
