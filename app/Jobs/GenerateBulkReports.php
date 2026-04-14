<?php

namespace App\Jobs;

use App\Actions\BuildReportData;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class GenerateBulkReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    // ──────────────────────────────────────────

    public function __construct(
        public readonly array $studentIds,
        public readonly int $classId,
        public readonly int $userId,
        public readonly int $term,
        public readonly int $year,
    ) {}

    // ──────────────────────────────────────────

    public function handle(): void
    {
        foreach ($this->studentIds as $studentId) {
            try {
                $this->generateOne($studentId);
            } catch (\Throwable $e) {
                logger()->error("ReportGen failed for student {$studentId}: ".$e->getMessage());
            }
        }
    }

    // ──────────────────────────────────────────

    private function generateOne(int $studentId): void
    {
        $report = Report::findOrInitialise($studentId, $this->userId, $this->term, $this->year);

        $data = (new BuildReportData)->execute(
            $this->classId, $studentId, $this->term, $this->year, $this->userId
        );

        $html = view('reports.report-card', $data)->render();
        $path = Report::buildPdfPath($studentId, $this->term, $this->year);
        $fullPath = Storage::disk('public')->path($path);

        @mkdir(dirname($fullPath), 0755, true);

        Browsershot::html($html)
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->save($fullPath);

        $report->markAsGenerated($path);
    }
}
