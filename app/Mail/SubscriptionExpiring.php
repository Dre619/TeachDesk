<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiring extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User         $user,
        public readonly Subscription $subscription,
        public readonly int          $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {config('app.name')} subscription expires in {$this->daysRemaining} day(s)",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-expiring',
            with: [
                'name'          => $this->user->name,
                'planName'      => $this->subscription->plan->name,
                'daysRemaining' => $this->daysRemaining,
                'expiresAt'     => $this->subscription->ends_at->format('d M Y'),
                'renewUrl'      => route('subscription.plans'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
