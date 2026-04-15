<?php

namespace App\Notifications;

use App\Models\ClassTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClassTransferAcceptedNotification extends Notification implements ShouldQueue
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
            ->subject("Transfer accepted — {$className}")
            ->greeting("Your transfer was accepted!")
            ->line("{$toName} has accepted ownership of **{$className}**.")
            ->line("The class now appears in their account. Your historical assessment records for this class are preserved.")
            ->action('Go to Dashboard', route('dashboard'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type'       => 'class_transfer_accepted',
            'class_name' => $this->transfer->classroom->name,
            'to_name'    => $this->transfer->toUser->name,
        ];
    }
}
