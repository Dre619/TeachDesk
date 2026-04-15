<?php

namespace App\Notifications;

use App\Models\ClassTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClassTransferDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ClassTransfer $transfer) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $toName    = $this->transfer->toUser->name;
        $className = $this->transfer->classroom->name;

        return (new MailMessage)
            ->subject("Transfer declined — {$className}")
            ->greeting("Transfer not completed.")
            ->line("{$toName} has declined the transfer of **{$className}**.")
            ->line("The class remains under your account. You can send a new transfer request to someone else.")
            ->action('Go to Dashboard', route('dashboard'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type'       => 'class_transfer_declined',
            'class_name' => $this->transfer->classroom->name,
            'to_name'    => $this->transfer->toUser->name,
        ];
    }
}
