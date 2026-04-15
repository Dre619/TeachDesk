<?php

namespace App\Notifications;

use App\Models\ClassTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClassTransferRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ClassTransfer $transfer) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $acceptUrl  = route('class-transfer.accept',  ['token' => $this->transfer->token]);
        $declineUrl = route('class-transfer.decline', ['token' => $this->transfer->token]);

        $fromName  = $this->transfer->fromUser->name;
        $className = $this->transfer->classroom->name;
        $subject   = $this->transfer->classroom->subject;
        $year      = $this->transfer->classroom->academic_year;

        return (new MailMessage)
            ->subject("Class transfer request — {$className}")
            ->greeting("Hello!")
            ->line("{$fromName} would like to transfer **{$className}** ({$subject} · {$year}) to you.")
            ->when($this->transfer->message, fn ($m) =>
                $m->line("Their message: *\"{$this->transfer->message}\"*")
            )
            ->line("If you accept, you will become the form teacher for this class and all its records.")
            ->action('Accept Transfer', $acceptUrl)
            ->line("Not taking this class? You can [decline]({$declineUrl}).")
            ->line('The original teacher retains their assessment records regardless of your decision.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'        => 'class_transfer_requested',
            'transfer_id' => $this->transfer->id,
            'class_name'  => $this->transfer->classroom->name,
            'from_name'   => $this->transfer->fromUser->name,
            'token'       => $this->transfer->token,
            'message'     => $this->transfer->message,
        ];
    }
}
