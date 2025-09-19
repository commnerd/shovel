<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrganizationMemberNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public User $newUser,
        public Organization $organization
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
            ->subject("New member request for {$this->organization->name}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("A new user has requested to join your organization '{$this->organization->name}'.")
            ->line("**User Details:**")
            ->line("Name: {$this->newUser->name}")
            ->line("Email: {$this->newUser->email}")
            ->line("The user is currently pending approval and waiting for an administrator to review their request.")
            ->action('Approve User', url("/admin/users/{$this->newUser->id}/approve?token=" . encrypt($this->newUser->id)))
            ->line('Or you can review all pending users from the admin panel:')
            ->action('Admin Panel', url("/admin/users"))
            ->line('Thank you for managing your organization!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'new_user_id' => $this->newUser->id,
            'new_user_name' => $this->newUser->name,
            'new_user_email' => $this->newUser->email,
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
        ];
    }
}
