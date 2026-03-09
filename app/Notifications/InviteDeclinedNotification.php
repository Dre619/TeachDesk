<?php

namespace App\Notifications;

use App\Models\ClassRoomMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteDeclinedNotification extends Notification implements ShouldQueue
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
            ->subject("{$teacherName} declined your invitation")
            ->greeting("Heads up.")
            ->line("{$teacherName} has declined your invitation to teach **{$subject}** in **{$className}**.")
            ->line('You can invite a different teacher for this subject from the Members tab.')
            ->action('Manage Members', route('user.members', ['classId' => $this->member->class_id]));
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'invite_declined',
            'member_id'    => $this->member->id,
            'class_name'   => $this->member->classroom->name,
            'subject'      => $this->member->subject,
            'teacher_name' => $this->member->teacher->name ?? $this->member->teacher->email,
        ];
    }
}
