<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportShared extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Report $report,
        public readonly string $studentName,
        public readonly string $teacherName,
        public readonly string $schoolName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Report Card: {$this->studentName} — Term {$this->report->term}, {$this->report->academic_year}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.report-shared',
            with: [
                'shareUrl'    => $this->report->share_url,
                'studentName' => $this->studentName,
                'teacherName' => $this->teacherName,
                'schoolName'  => $this->schoolName,
                'term'        => $this->report->term,
                'year'        => $this->report->academic_year,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
