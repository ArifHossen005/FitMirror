<?php

namespace App\Notifications;

use App\Models\StaffInvitation;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mailed to the invitee's email address (not to a User — none exists yet,
 * that's the whole point of the invite/accept split). The acceptance link
 * points at the dashboard SPA (FRONTEND_URL), which reads `?token=` and
 * posts it to POST /api/v1/staff/invitations/accept — the raw token never
 * touches the backend as a URL path/query the server itself parses, unlike
 * the signed email-verification link, since this flow has to survive a
 * round trip through a frontend route.
 */
class StaffInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly StaffInvitation $invitation,
        private readonly Tenant $tenant,
        private readonly string $rawToken,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = rtrim(config('app.frontend_url'), '/') . '/invite/accept?token=' . $this->rawToken;

        return (new MailMessage)
            ->subject("You're invited to join {$this->tenant->name} on FitMirror")
            ->greeting('Hello' . ($this->invitation->name ? " {$this->invitation->name}" : '') . '!')
            ->line("{$this->tenant->name} has invited you to join their FitMirror team as a **{$this->invitation->role}**.")
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation link expires on ' . $this->invitation->expires_at->format('Y-m-d H:i') . ' UTC.')
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
