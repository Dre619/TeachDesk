<?php

namespace App\Notifications;

use App\Models\ClassRoomMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ClassRoomMember $member) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $teacherName = $this->member->teacher->name ?? $this->member->teacher->email;
        $subject     = $this->member->subject;
        $className   = $this->member->classroom->name;

        return (new MailMessage)
            ->subject("{$teacherName} accepted your invitation")
            ->greeting("Great news!")
            ->line("{$teacherName} has accepted your invitation to teach **{$subject}** in **{$className}**.")
            ->line('They can now log in and enter assessment marks for their subject.')
            ->action('View Class Members', route('user.members', ['classId' => $this->member->class_id]));
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'invite_accepted',
            'member_id'    => $this->member->id,
            'class_name'   => $this->member->classroom->name,
            'subject'      => $this->member->subject,
            'teacher_name' => $this->member->teacher->name ?? $this->member->teacher->email,
        ];
    }
}
