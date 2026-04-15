<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeTeacher extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name') . '!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome-teacher',
            with: [
                'name'         => $this->user->name,
                'dashboardUrl' => route('dashboard'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
