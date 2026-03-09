<?php

namespace App\Notifications;

use App\Models\ClassRoomMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClassInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ClassRoomMember $member) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $acceptUrl  = route('invites.accept',  ['token' => $this->member->invite_token]);
        $declineUrl = route('invites.decline', ['token' => $this->member->invite_token]);

        $inviterName  = $this->member->invitedBy->name;
        $subject      = $this->member->subject;
        $className    = $this->member->classroom->name;
        $academicYear = $this->member->classroom->academic_year;

        return (new MailMessage)
            ->subject("Invitation to teach {$subject} — {$className}")
            ->greeting("Hello!")
            ->line("{$inviterName} has invited you to teach **{$subject}** in **{$className}** ({$academicYear}).")
            ->line("You'll be able to enter assessment marks for your subject. Only your subject's data will be visible to you.")
            ->action('Accept Invitation', $acceptUrl)
            ->line("Not interested? You can [decline this invitation]({$declineUrl}).")
            ->line('This invitation link expires after it is used.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'        => 'class_invite',
            'member_id'   => $this->member->id,
            'class_name'  => $this->member->classroom->name,
            'subject'     => $this->member->subject,
            'invited_by'  => $this->member->invitedBy->name,
            'token'       => $this->member->invite_token,   // used by bell to accept inline
        ];
    }
}
