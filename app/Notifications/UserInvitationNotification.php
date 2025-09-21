<?php

namespace App\Notifications;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private UserInvitation $invitation
    ) {}

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
        $inviterName = $this->invitation->invitedBy->name;
        $organizationName = $this->invitation->organization?->name ?? 'the platform';
        $setPasswordUrl = route('invitation.set-password', ['token' => $this->invitation->token]);

        return (new MailMessage)
            ->subject('You\'ve been invited to join ' . config('app.name'))
            ->greeting('Hello!')
            ->line("You have been invited by {$inviterName} to join {$organizationName} on " . config('app.name') . ".")
            ->line('To get started, please set your password by clicking the button below.')
            ->action('Set Password', $setPasswordUrl)
            ->line('This invitation will expire on ' . $this->invitation->expires_at->format('M j, Y \a\t g:i A') . '.')
            ->line('If you did not expect to receive this invitation, you can safely ignore this email.')
            ->salutation('Best regards,
' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'organization' => $this->invitation->organization?->name,
            'invited_by' => $this->invitation->invitedBy->name,
        ];
    }
}
