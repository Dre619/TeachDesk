<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Report;
use App\Models\Student;
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
    public int $tries   = 2;

    // ──────────────────────────────────────────

    public function __construct(
        public readonly array $studentIds,
        public readonly int   $classId,
        public readonly int   $userId,
        public readonly int   $term,
        public readonly int   $year,
    ) {}

    // ──────────────────────────────────────────

    public function handle(): void
    {
        $classroom = ClassRoom::findOrFail($this->classId);

        foreach ($this->studentIds as $studentId) {
            try {
                $this->generateOne($studentId, $classroom);
            } catch (\Throwable $e) {
                // Log but don't abort the whole batch
                logger()->error("ReportGen failed for student {$studentId}: " . $e->getMessage());
            }
        }
    }

    // ──────────────────────────────────────────

    private function generateOne(int $studentId, ClassRoom $classroom): void
    {
        $student = Student::findOrFail($studentId);

        // Build assessment data
        $assessments = Assessment::with('student')
            ->where('user_id', $this->userId)
            ->forClass($this->classId)
            ->forStudent($studentId)
            ->forTerm($this->term, $this->year)
            ->get();

        $byType = $assessments->groupBy('type')->map(function ($rows) {
            $avg   = round($rows->avg('percentage'), 1);
            $grade = (new Assessment(['score' => $avg, 'max_score' => 100]))->calculateGrade();
            return [
                'scores'  => $rows->map(fn ($a) => [
                    'score'      => $a->score,
                    'max_score'  => $a->max_score,
                    'percentage' => $a->percentage,
                    'grade'      => $a->grade,
                    'remarks'    => $a->remarks,
                ])->values()->toArray(),
                'average' => $avg,
                'grade'   => $grade,
                'count'   => $rows->count(),
            ];
        })->toArray();

        $overallAvg   = $assessments->isNotEmpty() ? round($assessments->avg('percentage'), 1) : null;
        $overallGrade = $overallAvg !== null
            ? (new Assessment(['score' => $overallAvg, 'max_score' => 100]))->calculateGrade()
            : null;

        // Attendance
        $attendanceYear = Attendance::forClass($this->classId)
            ->where('student_id', $studentId)
            ->whereYear('date', $this->year)
            ->get();

        $totalDays      = $attendanceYear->count();
        $presentDays    = $attendanceYear->filter(fn ($a) => $a->wasPresent())->count();
        $absentDays     = $attendanceYear->where('status', 'absent')->count();
        $lateDays       = $attendanceYear->where('status', 'late')->count();
        $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : null;

        $report = Report::findOrInitialise($studentId, $this->userId, $this->term, $this->year);

        $html = view('reports.report-card', [
            'student'         => $student,
            'classroom'       => $classroom,
            'byType'          => $byType,
            'overallAvg'      => $overallAvg,
            'overallGrade'    => $overallGrade,
            'totalDays'       => $totalDays,
            'presentDays'     => $presentDays,
            'absentDays'      => $absentDays,
            'lateDays'        => $lateDays,
            'attendanceRate'  => $attendanceRate,
            'gradingScale'    => Assessment::GRADING_SCALE,
            'term'            => $this->term,
            'academic_year'   => $this->year,
            'teacher_comment' => $report->teacher_comment,
            'generated_at'    => now(),
        ])->render();

        $path     = Report::buildPdfPath($studentId, $this->term, $this->year);
        $fullPath = Storage::path($path);
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
