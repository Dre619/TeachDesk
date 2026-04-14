<?php

namespace App\Actions;

use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Report;
use App\Models\ReportCardSetting;
use App\Models\Student;

class BuildReportData
{
    /**
     * Build all data required to render the report-card.blade.php template.
     *
     * @return array<string, mixed>
     */
    public function execute(
        int $classId,
        int $studentId,
        int $term,
        int $year,
        int $userId,
    ): array {
        $student = Student::findOrFail($studentId);
        $classroom = ClassRoom::findOrFail($classId);

        $assessments = Assessment::with('student')
            ->forClass($classId)
            ->forStudent($studentId)
            ->forTerm($term, $year)
            ->get();

        // Group by subject → then by assessment type within each subject
        $bySubject = $assessments->groupBy('subject')->map(function ($subjectRows) {
            $byType = $subjectRows->groupBy('type')->map(function ($rows) {
                $avg = round($rows->avg('percentage'), 1);
                $grade = (new Assessment(['score' => $avg, 'max_score' => 100]))->calculateGrade();

                return [
                    'scores' => $rows->map(fn ($a) => [
                        'score' => $a->score,
                        'max_score' => $a->max_score,
                        'percentage' => $a->percentage,
                        'grade' => $a->grade,
                        'remarks' => $a->remarks,
                    ])->values()->toArray(),
                    'average' => $avg,
                    'grade' => $grade,
                    'count' => $rows->count(),
                ];
            })->toArray();

            $subjectAvg = round($subjectRows->avg('percentage'), 1);
            $subjectGrade = (new Assessment(['score' => $subjectAvg, 'max_score' => 100]))->calculateGrade();

            return [
                'byType' => $byType,
                'average' => $subjectAvg,
                'overallGrade' => $subjectGrade,
                'count' => $subjectRows->count(),
            ];
        })->toArray();

        $overallAvg = $assessments->isNotEmpty() ? round($assessments->avg('percentage'), 1) : null;
        $overallGrade = $overallAvg !== null
            ? (new Assessment(['score' => $overallAvg, 'max_score' => 100]))->calculateGrade()
            : null;

        // Attendance for the full academic year
        $attendanceYear = Attendance::forClass($classId)
            ->where('student_id', $studentId)
            ->whereYear('date', $year)
            ->get();

        $totalDays = $attendanceYear->count();
        $presentDays = $attendanceYear->filter(fn ($a) => $a->wasPresent())->count();
        $absentDays = $attendanceYear->where('status', 'absent')->count();
        $lateDays = $attendanceYear->where('status', 'late')->count();
        $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : null;

        $settings = ReportCardSetting::forTeacherAndClass($userId, $classId);

        // Load any existing report record for conduct / comments
        $report = Report::forTeacher($userId)
            ->where('student_id', $studentId)
            ->forTerm($term, $year)
            ->first();

        return [
            'student' => $student,
            'classroom' => $classroom,
            'bySubject' => $bySubject,
            'overallAvg' => $overallAvg,
            'overallGrade' => $overallGrade,
            'totalDays' => $totalDays,
            'presentDays' => $presentDays,
            'absentDays' => $absentDays,
            'lateDays' => $lateDays,
            'attendanceRate' => $attendanceRate,
            'gradingScale' => Assessment::GRADING_SCALE,
            'settings' => $settings,
            'term' => $term,
            'academic_year' => $year,
            'conduct_grade' => $report?->conduct_grade,
            'form_teacher_comment' => $report?->form_teacher_comment,
            'head_teacher_comment' => $report?->head_teacher_comment,
            'generated_at' => now(),
        ];
    }
}
