<?php

use App\Jobs\GenerateBulkReports;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Report;
use App\Models\ReportCardSetting;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Browsershot\Browsershot;
use WireUi\Traits\WireUiActions;
use App\Livewire\Concerns\HasClassRoomRole;

new class extends Component
{
    use WireUiActions, HasClassRoomRole, WithFileUploads;

    // ──────────────────────────────────────────
    // Props
    // ──────────────────────────────────────────

    public int $classId;

    // ──────────────────────────────────────────
    // Modal flags
    // ──────────────────────────────────────────

    public bool $showPreviewModal  = false;
    public bool $showCommentModal  = false;
    public bool $showBulkModal     = false;
    public bool $showDeleteModal   = false;
    public bool $showSettingsModal = false;

    // ──────────────────────────────────────────
    // Term / year selection (shared header)
    // ──────────────────────────────────────────

    public int $selectedTerm = 1;
    public int $selectedYear;

    // ──────────────────────────────────────────
    // Preview state
    // ──────────────────────────────────────────

    public ?int  $previewStudentId = null;
    public array $previewData      = [];

    // ──────────────────────────────────────────
    // Comment / conduct editing (per student)
    // ──────────────────────────────────────────

    public ?int   $commentReportId      = null;
    public string $conductGrade         = '';
    public string $formTeacherComment   = '';
    public string $headTeacherComment   = '';

    // ──────────────────────────────────────────
    // Report card settings (per class)
    // ──────────────────────────────────────────

    public string $settingSchoolName      = 'Student Report Card';
    public string $settingSchoolMotto     = '';
    public string $settingAccentColor     = '#4f46e5';
    public ?string $settingLogoPath       = null;   // stored path
    public $settingLogo                   = null;   // temp upload
    public bool   $settingShowAttendance  = true;
    public bool   $settingShowConduct     = true;
    public bool   $settingShowGradingScale= true;
    public bool   $settingShowSignatures  = true;
    public string $settingFooterNote      = '';

    // ──────────────────────────────────────────
    // Delete state
    // ──────────────────────────────────────────

    public ?int $deletingReportId = null;

    // ──────────────────────────────────────────
    // Generation tracking
    // ──────────────────────────────────────────

    public array  $generatingIds = [];
    public string $bulkStatus    = '';

    // ──────────────────────────────────────────
    // Filters
    // ──────────────────────────────────────────

    public string $filterStatus = '';
    public string $search       = '';

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function mount(int $classId): void
    {
        $class = ClassRoom::forTeacher(Auth::id())->findOrFail($classId);
        $this->classId      = $classId;
        $this->selectedYear = (int) date('Y');
        $this->resolveRole($class);
        $this->loadSettings();
    }

    private function loadSettings(): void
    {
        $s = ReportCardSetting::forTeacherAndClass(Auth::id(), $this->classId);
        $this->settingSchoolName       = $s->school_name       ?? 'Student Report Card';
        $this->settingSchoolMotto      = $s->school_motto      ?? '';
        $this->settingAccentColor      = $s->accent_color      ?? '#4f46e5';
        $this->settingLogoPath         = $s->school_logo       ?? null;
        $this->settingShowAttendance   = (bool) ($s->show_attendance   ?? true);
        $this->settingShowConduct      = (bool) ($s->show_conduct      ?? true);
        $this->settingShowGradingScale = (bool) ($s->show_grading_scale ?? true);
        $this->settingShowSignatures   = (bool) ($s->show_signatures   ?? true);
        $this->settingFooterNote       = $s->footer_note       ?? '';
    }

    // ──────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────

    #[Computed]
    public function classroom(): ClassRoom
    {
        return ClassRoom::forTeacher(Auth::id())->findOrFail($this->classId);
    }

    #[Computed]
    public function students()
    {
        return Student::forTeacher(Auth::id())
            ->inClass($this->classId)
            ->whereNull('deleted_at')
            ->alphabetical()
            ->get();
    }

    #[Computed]
    public function reports(): array
    {
        return Report::forTeacher(Auth::id())
            ->whereIn('student_id', $this->students->pluck('id'))
            ->forTerm($this->selectedTerm, $this->selectedYear)
            ->get()
            ->keyBy('student_id')
            ->toArray();
    }

    #[Computed]
    public function rows(): array
    {
        $rows = [];

        foreach ($this->students as $student) {
            $report = $this->reports[$student->id] ?? null;
            $status = $report['status'] ?? 'not_created';

            if ($this->search) {
                $name = strtolower($student->first_name . ' ' . $student->last_name);
                if (! str_contains($name, strtolower($this->search))) continue;
            }

            if ($this->filterStatus) {
                if ($this->filterStatus === 'not_created' && $report !== null) continue;
                if ($this->filterStatus !== 'not_created' && $status !== $this->filterStatus) continue;
            }

            $rows[] = [
                'student' => $student,
                'report'  => $report ? Report::hydrate([$report])->first() : null,
                'status'  => $status,
            ];
        }

        return $rows;
    }

    #[Computed]
    public function stats(): array
    {
        $total     = $this->students->count();
        $generated = collect($this->reports)->where('status', 'generated')->count();
        $draft     = collect($this->reports)->where('status', 'draft')->count();
        $pending   = $total - count($this->reports);

        return compact('total', 'generated', 'draft', 'pending');
    }

    // ──────────────────────────────────────────
    // Preview
    // ──────────────────────────────────────────

    public function openPreview(int $studentId): void
    {
        $this->previewStudentId = $studentId;
        $this->previewData      = $this->buildReportData($studentId);
        $this->showPreviewModal = true;
    }

    // ──────────────────────────────────────────
    // Comment / Conduct modal
    // ──────────────────────────────────────────

    public function openCommentModal(int $studentId): void
    {
        $report = Report::findOrInitialise(
            $studentId,
            Auth::id(),
            $this->selectedTerm,
            $this->selectedYear,
        );

        $this->commentReportId    = $report->id;
        $this->conductGrade       = $report->conduct_grade       ?? '';
        $this->formTeacherComment = $report->form_teacher_comment ?? '';
        $this->headTeacherComment = $report->head_teacher_comment ?? '';
        $this->showCommentModal   = true;
    }

    public function saveComment(): void
    {
        $this->validate([
            'conductGrade'       => 'nullable|in:A,B,C,D,E,F',
            'formTeacherComment' => 'nullable|string|max:1000',
            'headTeacherComment' => 'nullable|string|max:1000',
        ]);

        Report::find($this->commentReportId)?->update([
            'conduct_grade'        => $this->conductGrade ?: null,
            'form_teacher_comment' => $this->formTeacherComment ?: null,
            'head_teacher_comment' => $this->headTeacherComment ?: null,
        ]);

        $this->showCommentModal = false;
        $this->commentReportId  = null;
        unset($this->reports, $this->rows);

        $this->notification()->success(title: 'Saved!', description: 'Comment & conduct updated.');
    }

    // ──────────────────────────────────────────
    // Report card settings modal
    // ──────────────────────────────────────────

    public function openSettingsModal(): void
    {
        $this->showSettingsModal = true;
    }

    public function saveSettings(): void
    {
        $this->validate([
            'settingSchoolName'       => 'required|string|max:120',
            'settingSchoolMotto'      => 'nullable|string|max:200',
            'settingAccentColor'      => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settingShowAttendance'   => 'boolean',
            'settingShowConduct'      => 'boolean',
            'settingShowGradingScale' => 'boolean',
            'settingShowSignatures'   => 'boolean',
            'settingFooterNote'       => 'nullable|string|max:200',
            'settingLogo'             => 'nullable|image|max:2048',
        ]);

        // Handle logo upload
        $logoPath = $this->settingLogoPath;
        if ($this->settingLogo) {
            // Delete old logo if it exists
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $this->settingLogo->store('logos', 'public');
            $this->settingLogoPath = $logoPath;
            $this->settingLogo     = null;
        }

        ReportCardSetting::updateOrCreate(
            ['user_id' => Auth::id(), 'class_id' => $this->classId],
            [
                'school_name'        => $this->settingSchoolName,
                'school_motto'       => $this->settingSchoolMotto ?: null,
                'school_logo'        => $logoPath,
                'accent_color'       => $this->settingAccentColor,
                'show_attendance'    => $this->settingShowAttendance,
                'show_conduct'       => $this->settingShowConduct,
                'show_grading_scale' => $this->settingShowGradingScale,
                'show_signatures'    => $this->settingShowSignatures,
                'footer_note'        => $this->settingFooterNote ?: null,
            ]
        );

        $this->showSettingsModal = false;
        $this->notification()->success(
            title:       'Settings saved!',
            description: 'Report card template updated. Regenerate PDFs to apply.',
        );
    }

    public function removeLogo(): void
    {
        $s = ReportCardSetting::where('user_id', Auth::id())->where('class_id', $this->classId)->first();
        if ($s?->school_logo && Storage::disk('public')->exists($s->school_logo)) {
            Storage::disk('public')->delete($s->school_logo);
        }
        $s?->update(['school_logo' => null]);
        $this->settingLogoPath = null;
        $this->notification()->success(title: 'Logo removed', description: 'Report card logo has been cleared.');
    }

    // ──────────────────────────────────────────
    // Generate single report
    // ──────────────────────────────────────────

    public function generateReport(int $studentId): void
    {
        $this->generatingIds[] = $studentId;

        try {
            $this->doGenerate($studentId);
            $this->notification()->success(
                title:       'Report generated!',
                description: 'The PDF has been saved.',
            );
        } catch (\Throwable $e) {
            $this->notification()->error(
                title:       'Generation failed',
                description: $e->getMessage(),
            );
        }

        $this->generatingIds = array_values(array_diff($this->generatingIds, [$studentId]));
        unset($this->reports, $this->rows, $this->stats);
    }

    // ──────────────────────────────────────────
    // Bulk generation
    // ──────────────────────────────────────────

    public function openBulkModal(): void
    {
        $this->bulkStatus    = '';
        $this->showBulkModal = true;
    }

    public function generateAll(): void
    {
        $studentIds = $this->students->pluck('id')->toArray();

        if (count($studentIds) > 30) {
            GenerateBulkReports::dispatch(
                $studentIds,
                $this->classId,
                Auth::id(),
                $this->selectedTerm,
                $this->selectedYear,
            );

            $this->bulkStatus    = 'queued';
            $this->showBulkModal = false;
            $this->notification()->success(
                title:       'Reports queued!',
                description: 'Generation is running in the background.',
            );
            return;
        }

        $success = 0;
        $failed  = 0;

        foreach ($studentIds as $id) {
            try {
                $this->doGenerate($id);
                $success++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->showBulkModal = false;
        unset($this->reports, $this->rows, $this->stats);

        $this->notification()->success(
            title:       'Bulk generation complete',
            description: "{$success} generated · {$failed} failed.",
        );
    }

    // ──────────────────────────────────────────
    // Download
    // ──────────────────────────────────────────

    public function download(int $reportId): mixed
    {
        $report = Report::forTeacher(Auth::id())->findOrFail($reportId);

        if (! $report->isGenerated()) {
            $this->notification()->error(title: 'Not ready', description: 'The PDF has not been generated yet.');
            return null;
        }

        return response()->download(Storage::path($report->pdf_path));
    }

    // ──────────────────────────────────────────
    // Delete PDF
    // ──────────────────────────────────────────

    public function confirmDelete(int $reportId): void
    {
        $this->deletingReportId = $reportId;
        $this->showDeleteModal  = true;
    }

    public function deleteReport(): void
    {
        if ($this->deletingReportId) {
            $report = Report::forTeacher(Auth::id())->find($this->deletingReportId);
            $report?->deletePdf();
            $report?->update(['status' => 'draft', 'pdf_path' => null, 'generated_at' => null]);
            unset($this->reports, $this->rows, $this->stats);

            $this->notification()->warning(title: 'PDF deleted', description: 'Report reset to draft.');
        }

        $this->showDeleteModal  = false;
        $this->deletingReportId = null;
    }

    // ──────────────────────────────────────────
    // Core generation logic
    // ──────────────────────────────────────────

    private function doGenerate(int $studentId): void
    {
        $data   = $this->buildReportData($studentId);
        $report = Report::findOrInitialise(
            $studentId,
            Auth::id(),
            $this->selectedTerm,
            $this->selectedYear,
        );

        // Merge per-student fields from DB record
        $data['conduct_grade']        = $report->conduct_grade;
        $data['form_teacher_comment'] = $report->form_teacher_comment;
        $data['head_teacher_comment'] = $report->head_teacher_comment;

        $html     = view('reports.report-card', $data)->render();
        $path     = Report::buildPdfPath($studentId, $this->selectedTerm, $this->selectedYear);
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

    /**
     * Builds all data needed to render the report card template.
     * Returns $bySubject keyed by subject name, each containing byType breakdown.
     */
    private function buildReportData(int $studentId): array
    {
        $student   = Student::with('classRoom')->findOrFail($studentId);
        $classroom = $this->classroom;

        $assessments = Assessment::with('student')
            ->forClass($this->classId)
            ->forStudent($studentId)
            ->forTerm($this->selectedTerm, $this->selectedYear)
            ->get();

        // Group by subject → then by type within each subject
        $bySubject = $assessments->groupBy('subject')->map(function ($subjectRows, $subject) {
            $byType = $subjectRows->groupBy('type')->map(function ($rows) {
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

            $subjectAvg   = round($subjectRows->avg('percentage'), 1);
            $subjectGrade = (new Assessment(['score' => $subjectAvg, 'max_score' => 100]))->calculateGrade();

            return [
                'byType'       => $byType,
                'average'      => $subjectAvg,
                'overallGrade' => $subjectGrade,
                'count'        => $subjectRows->count(),
            ];
        })->toArray();

        // Overall across all subjects
        $overallAvg   = $assessments->isNotEmpty() ? round($assessments->avg('percentage'), 1) : null;
        $overallGrade = $overallAvg !== null
            ? (new Assessment(['score' => $overallAvg, 'max_score' => 100]))->calculateGrade()
            : null;

        // Attendance for the academic year
        $attendanceYear = Attendance::forClass($this->classId)
            ->where('student_id', $studentId)
            ->whereYear('date', $this->selectedYear)
            ->get();

        $totalDays      = $attendanceYear->count();
        $presentDays    = $attendanceYear->filter(fn ($a) => $a->wasPresent())->count();
        $absentDays     = $attendanceYear->where('status', 'absent')->count();
        $lateDays       = $attendanceYear->where('status', 'late')->count();
        $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : null;

        $gradingScale = Assessment::GRADING_SCALE;

        // Load report card settings for this class
        $settings = ReportCardSetting::forTeacherAndClass(Auth::id(), $this->classId);

        return compact(
            'student', 'classroom', 'bySubject',
            'overallAvg', 'overallGrade',
            'totalDays', 'presentDays', 'absentDays', 'lateDays', 'attendanceRate',
            'gradingScale', 'settings',
        ) + [
            'term'                => $this->selectedTerm,
            'academic_year'       => $this->selectedYear,
            'conduct_grade'       => null,
            'form_teacher_comment'=> null,
            'head_teacher_comment'=> null,
            'generated_at'        => now(),
        ];
    }
};
?>

<div class="min-h-screen bg-slate-50 font-sans">
<x-notifications/>
    {{-- ══════════════════════════════════════════════════════════
         HEADER
    ══════════════════════════════════════════════════════════ --}}
    <div class="bg-white border-b border-slate-200 px-6 py-5">
        <div class="mx-auto">
            <p class="text-xs text-slate-400 mb-1 uppercase tracking-wide font-medium">
                {{ $this->classroom->name }} &middot; {{ $this->classroom->subject }} &middot; {{ $this->classroom->academic_year }}
            </p>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Report Cards</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        {{ $this->stats['total'] }} students &middot;
                        <span class="text-emerald-600 font-medium">{{ $this->stats['generated'] }} generated</span>
                        &middot; {{ $this->stats['draft'] }} draft
                        &middot; {{ $this->stats['pending'] }} pending
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-button
                        wire:click="openSettingsModal"
                        icon="adjustments-horizontal"
                        label="Customise"
                        outline
                        class="w-full sm:w-auto"
                    />
                    <x-button
                        wire:click="openBulkModal"
                        icon="document-duplicate"
                        label="Generate All"
                        outline
                        class="w-full sm:w-auto"
                    />
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto px-6 py-8 space-y-6">

        {{-- ══════════════════════════════════════════════════════════
             TERM / YEAR SELECTOR + FILTERS
        ══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-native-select wire:model.live="selectedTerm" label="Term">
                    <option value="1">Term 1 (Jan–Apr)</option>
                    <option value="2">Term 2 (May–Aug)</option>
                    <option value="3">Term 3 (Sep–Dec)</option>
                </x-native-select>

                <x-input wire:model.live.debounce.400ms="selectedYear" label="Year" type="number" />

                <x-input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search student…"
                    icon="magnifying-glass"
                    shadowless
                />

                <x-native-select wire:model.live="filterStatus" shadowless>
                    <option value="">All statuses</option>
                    <option value="generated">Generated</option>
                    <option value="draft">Draft</option>
                    <option value="not_created">Not Created</option>
                </x-native-select>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             STATS STRIP
        ══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Total Students</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ $this->stats['total'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-emerald-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Generated</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $this->stats['generated'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-amber-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Draft</p>
                <p class="text-2xl font-bold text-amber-500 mt-1">{{ $this->stats['draft'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Pending</p>
                <p class="text-2xl font-bold text-slate-500 mt-1">{{ $this->stats['pending'] }}</p>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             STUDENT REPORT GRID
        ══════════════════════════════════════════════════════════ --}}
        @if (empty($this->rows))
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                <x-icon name="document-text" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-500 font-medium">No students found.</p>
            </div>
        @else
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-left">
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Student</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden sm:table-cell">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden md:table-cell">Generated</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden lg:table-cell">Conduct</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->rows as $row)
                        @php
                            $student   = $row['student'];
                            $report    = $row['report'];
                            $status    = $row['status'];
                            $isGenerating = in_array($student->id, $generatingIds);

                            $statusBadge = match($status) {
                                'generated'   => ['bg-emerald-100 text-emerald-700 border-emerald-200', 'Generated'],
                                'draft'       => ['bg-amber-100 text-amber-700 border-amber-200',   'Draft'],
                                default       => ['bg-slate-100 text-slate-500 border-slate-200',   'Not Created'],
                            };
                            $conductColors = [
                                'A' => 'bg-emerald-100 text-emerald-700',
                                'B' => 'bg-teal-100 text-teal-700',
                                'C' => 'bg-blue-100 text-blue-700',
                                'D' => 'bg-amber-100 text-amber-700',
                                'E' => 'bg-orange-100 text-orange-700',
                                'F' => 'bg-red-100 text-red-700',
                            ];
                        @endphp
                        <tr wire:key="report-{{ $student->id }}" class="hover:bg-slate-50 transition-colors">

                            {{-- Student --}}
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-800">{{ $student->full_name }}</p>
                                        <p class="text-xs text-slate-400">{{ $student->register_name }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Status badge --}}
                            <td class="px-5 py-3 hidden sm:table-cell">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $statusBadge[0] }}">
                                    @if ($isGenerating)
                                        <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                        </svg>
                                        Generating…
                                    @else
                                        {{ $statusBadge[1] }}
                                    @endif
                                </span>
                            </td>

                            {{-- Generated at --}}
                            <td class="px-5 py-3 text-slate-500 text-xs hidden md:table-cell">
                                @if ($report?->generated_at)
                                    {{ $report->generated_at->format('d M Y, H:i') }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            {{-- Conduct grade --}}
                            <td class="px-5 py-3 hidden lg:table-cell">
                                @if ($report?->conduct_grade)
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold {{ $conductColors[$report->conduct_grade] ?? '' }}">
                                        {{ $report->conduct_grade }}
                                    </span>
                                @else
                                    <span class="text-slate-300 text-xs italic">Not set</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Preview --}}
                                    <x-button
                                        wire:click="openPreview({{ $student->id }})"
                                        icon="eye"
                                        flat xs
                                        class="text-slate-500"
                                        title="Preview"
                                    />

                                    {{-- Comment & Conduct --}}
                                    <x-button
                                        wire:click="openCommentModal({{ $student->id }})"
                                        icon="chat-bubble-left-ellipsis"
                                        flat xs
                                        class="text-indigo-600"
                                        title="Add comment & conduct"
                                    />

                                    {{-- Generate / Regenerate --}}
                                    <x-button
                                        wire:click="generateReport({{ $student->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="generateReport({{ $student->id }})"
                                        :icon="$status === 'generated' ? 'arrow-path' : 'document-arrow-down'"
                                        flat xs
                                        :class="$status === 'generated' ? 'text-sky-600' : 'text-emerald-600'"
                                        :title="$status === 'generated' ? 'Regenerate' : 'Generate PDF'"
                                        :disabled="$isGenerating"
                                    />

                                    {{-- Download --}}
                                    @if ($report?->isGenerated())
                                        <a
                                            href="{{ $report->pdf_url }}"
                                            target="_blank"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded text-slate-500 hover:bg-slate-100 transition-colors"
                                            title="Download PDF"
                                        >
                                            <x-icon name="arrow-down-tray" class="w-4 h-4" />
                                        </a>

                                        {{-- Delete PDF --}}
                                        <x-button
                                            wire:click="confirmDelete({{ $report->id }})"
                                            icon="trash"
                                            flat xs
                                            class="text-red-400"
                                            title="Delete PDF"
                                        />
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>


    {{-- ══════════════════════════════════════════════════════════
         PREVIEW MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showPreviewModal" title="Report Card Preview" blur width="3xl">
        <x-card class="relative">
            @if (! empty($previewData))
        @php
            $pd = $previewData;
            $gradeColors = [
                'A'=>'bg-emerald-100 text-emerald-800','B'=>'bg-teal-100 text-teal-800',
                'C'=>'bg-blue-100 text-blue-800','D'=>'bg-amber-100 text-amber-800',
                'E'=>'bg-orange-100 text-orange-800','F'=>'bg-red-100 text-red-800',
            ];
        @endphp
        <div class="space-y-5 p-1 max-h-[75vh] overflow-y-auto">

            {{-- Student header --}}
            <div class="flex items-center justify-between bg-indigo-50 border border-indigo-200 rounded-xl px-5 py-4">
                <div>
                    <p class="font-bold text-indigo-900 text-lg">{{ $pd['student']->full_name }}</p>
                    <p class="text-indigo-600 text-sm">{{ $pd['classroom']->name }} &middot; Term {{ $pd['term'] }} &middot; {{ $pd['academic_year'] }}</p>
                </div>
                @if ($pd['overallGrade'])
                    <div class="text-center">
                        <span class="inline-flex items-center justify-center w-14 h-14 rounded-full text-2xl font-extrabold border-4 border-indigo-200 {{ $gradeColors[$pd['overallGrade']] ?? '' }}">
                            {{ $pd['overallGrade'] }}
                        </span>
                        <p class="text-xs text-indigo-500 mt-1">{{ $pd['overallAvg'] }}% overall</p>
                    </div>
                @endif
            </div>

            {{-- Assessment breakdown — single unified table --}}
            @if (! empty($pd['bySubject']))
            @php
                $typeLabels = [
                    'test'=>'Class Test','exam'=>'Examination',
                    'assignment'=>'Assignment','ca'=>'Continuous Assessment',
                    'other'=>'Other',
                ];
            @endphp
            <div class="border border-slate-200 rounded-lg overflow-hidden text-xs">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-800 text-white">
                            <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-xs">Subject / Assessment</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase tracking-wide text-xs w-20">Score</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase tracking-wide text-xs w-14">%</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase tracking-wide text-xs w-14">Grade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($pd['bySubject'] as $subject => $subjectData)

                            {{-- Subject header row --}}
                            <tr class="bg-slate-700">
                                <td colspan="4" class="px-3 py-1.5 font-bold text-white text-xs">
                                    {{ $subject }}
                                    <span class="ml-2 font-normal text-slate-400 text-xs">{{ $subjectData['count'] }} {{ Str::plural('record', $subjectData['count']) }}</span>
                                </td>
                            </tr>

                            @foreach ($subjectData['byType'] as $type => $data)

                                {{-- Type header row --}}
                                <tr class="bg-indigo-50">
                                    <td colspan="4" class="px-4 py-1 font-semibold text-indigo-700 text-xs">
                                        {{ $typeLabels[$type] ?? ucfirst($type) }}
                                        <span class="font-normal text-indigo-400 ml-1">({{ $data['count'] }})</span>
                                    </td>
                                </tr>

                                {{-- Score rows --}}
                                @foreach ($data['scores'] as $i => $score)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-1.5 text-slate-400">Entry {{ $i + 1 }}</td>
                                    <td class="px-3 py-1.5 text-center font-medium text-slate-700">{{ $score['score'] }}/{{ $score['max_score'] }}</td>
                                    <td class="px-3 py-1.5 text-center text-slate-600">{{ $score['percentage'] }}%</td>
                                    <td class="px-3 py-1.5 text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full font-bold {{ $gradeColors[$score['grade']] ?? '' }}">{{ $score['grade'] }}</span>
                                    </td>
                                </tr>
                                @endforeach

                                {{-- Type average row --}}
                                <tr class="bg-green-50">
                                    <td class="px-5 py-1.5 font-semibold text-green-700">{{ $typeLabels[$type] ?? ucfirst($type) }} Average</td>
                                    <td class="px-3 py-1.5 text-center text-slate-400">—</td>
                                    <td class="px-3 py-1.5 text-center font-semibold text-green-700">{{ $data['average'] }}%</td>
                                    <td class="px-3 py-1.5 text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full font-bold {{ $gradeColors[$data['grade']] ?? '' }}">{{ $data['grade'] }}</span>
                                    </td>
                                </tr>

                            @endforeach

                            {{-- Subject overall average row --}}
                            <tr class="bg-indigo-100 border-t-2 border-indigo-200">
                                <td class="px-4 py-1.5 font-bold text-indigo-800">{{ $subject }} — Overall</td>
                                <td class="px-3 py-1.5 text-center text-slate-400">—</td>
                                <td class="px-3 py-1.5 text-center font-bold text-indigo-800">{{ $subjectData['average'] }}%</td>
                                <td class="px-3 py-1.5 text-center">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full font-bold {{ $gradeColors[$subjectData['overallGrade']] ?? '' }}">{{ $subjectData['overallGrade'] }}</span>
                                </td>
                            </tr>

                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <div class="text-center py-6 text-slate-400 text-sm">No assessments recorded for this term.</div>
            @endif

            {{-- Attendance --}}
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Attendance — {{ $pd['academic_year'] }}</p>
                <div class="grid grid-cols-5 gap-3">
                    @foreach ([
                        ['Present', $pd['presentDays'], 'text-emerald-600'],
                        ['Absent',  $pd['absentDays'],  'text-red-500'],
                        ['Late',    $pd['lateDays'],    'text-amber-500'],
                        ['Rate',    ($pd['attendanceRate'] !== null ? $pd['attendanceRate'].'%' : '—'), 'text-indigo-600'],
                        ['Total',   $pd['totalDays'],   'text-slate-600'],
                    ] as [$label, $value, $color])
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-center">
                        <p class="text-lg font-bold {{ $color }}">{{ $value }}</p>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">{{ $label }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>
        @endif

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                @if (! empty($previewData))
                    <x-button
                        wire:click="generateReport({{ $previewStudentId }})"
                        wire:loading.attr="disabled"
                        wire:target="generateReport({{ $previewStudentId }})"
                        icon="document-arrow-down"
                        label="Generate PDF"
                        primary
                        spinner="generateReport({{ $previewStudentId }})"
                    />
                @endif
                <x-button wire:click="$set('showPreviewModal', false)" label="Close" flat />
            </div>
        </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         COMMENT & CONDUCT MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showCommentModal" title="Comments & Conduct" blur persistent width="xl">
        <x-card class="relative">
            <div class="p-1 space-y-4">

                {{-- Conduct grade --}}
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Conduct Grade</p>
                    <div class="grid grid-cols-6 gap-2">
                        @foreach (['A' => 'Excellent', 'B' => 'Very Good', 'C' => 'Good', 'D' => 'Satisfactory', 'E' => 'Needs Improvement', 'F' => 'Unsatisfactory'] as $grade => $label)
                        @php
                            $active = $conductGrade === $grade;
                            $colors = [
                                'A' => ['ring-emerald-500 bg-emerald-100 text-emerald-800', 'bg-slate-100 text-slate-500 hover:bg-emerald-50'],
                                'B' => ['ring-teal-500 bg-teal-100 text-teal-800', 'bg-slate-100 text-slate-500 hover:bg-teal-50'],
                                'C' => ['ring-blue-500 bg-blue-100 text-blue-800', 'bg-slate-100 text-slate-500 hover:bg-blue-50'],
                                'D' => ['ring-amber-500 bg-amber-100 text-amber-800', 'bg-slate-100 text-slate-500 hover:bg-amber-50'],
                                'E' => ['ring-orange-500 bg-orange-100 text-orange-800', 'bg-slate-100 text-slate-500 hover:bg-orange-50'],
                                'F' => ['ring-red-500 bg-red-100 text-red-800', 'bg-slate-100 text-slate-500 hover:bg-red-50'],
                            ][$grade];
                        @endphp
                        <button
                            wire:click="$set('conductGrade', '{{ $active ? '' : $grade }}')"
                            type="button"
                            class="flex flex-col items-center justify-center rounded-lg py-2 text-xs font-bold transition-all {{ $active ? 'ring-2 '.$colors[0] : $colors[1] }}"
                            title="{{ $label }}"
                        >
                            {{ $grade }}
                        </button>
                        @endforeach
                    </div>
                    @if ($conductGrade)
                    <p class="text-xs text-slate-400 mt-1">
                        Selected: <span class="font-semibold">{{ $conductGrade }}</span> —
                        {{ ['A'=>'Excellent','B'=>'Very Good','C'=>'Good','D'=>'Satisfactory','E'=>'Needs Improvement','F'=>'Unsatisfactory'][$conductGrade] }}
                        <button wire:click="$set('conductGrade', '')" class="ml-2 text-red-400 hover:underline text-xs">Clear</button>
                    </p>
                    @endif
                </div>

                {{-- Form teacher comment --}}
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Form Teacher's Comment</p>
                    <x-textarea
                        wire:model="formTeacherComment"
                        rows="4"
                        placeholder="General comment about the student's overall performance and conduct…"
                        :error="$errors->first('formTeacherComment')"
                    />
                    <p class="text-xs text-slate-400 mt-1">{{ strlen($formTeacherComment) }} / 1000</p>
                </div>

                {{-- Head teacher comment --}}
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Head Teacher's Comment</p>
                    <x-textarea
                        wire:model="headTeacherComment"
                        rows="3"
                        placeholder="Head teacher's remarks (optional)…"
                        :error="$errors->first('headTeacherComment')"
                    />
                    <p class="text-xs text-slate-400 mt-1">{{ strlen($headTeacherComment) }} / 1000</p>
                </div>

            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('showCommentModal', false)" label="Cancel" flat />
                    <x-button
                        wire:click="saveComment"
                        wire:loading.attr="disabled"
                        wire:target="saveComment"
                        label="Save"
                        primary
                        spinner="saveComment"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         REPORT CARD SETTINGS MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showSettingsModal" title="Customise Report Card" blur persistent width="xl">
        <x-card class="relative">
            <div class="p-1 space-y-5">

                <p class="text-sm text-slate-500">
                    These settings apply to all report cards generated for
                    <strong>{{ $this->classroom->name }}</strong>. Regenerate PDFs after saving to apply changes.
                </p>

                {{-- School name --}}
                <x-input
                    wire:model="settingSchoolName"
                    label="School / Report Title"
                    placeholder="e.g. Sunrise Academy"
                    :error="$errors->first('settingSchoolName')"
                />

                {{-- School motto --}}
                <x-input
                    wire:model="settingSchoolMotto"
                    label="School Motto (optional)"
                    placeholder="e.g. Excellence Through Knowledge"
                    :error="$errors->first('settingSchoolMotto')"
                />

                {{-- School logo --}}
                <div>
                    <p class="text-xs font-semibold text-slate-600 mb-1">School Logo (optional)</p>
                    @if ($settingLogoPath)
                        <div class="flex items-center gap-3 mb-2">
                            <img src="{{ Storage::disk('public')->url($settingLogoPath) }}" alt="School logo" class="h-14 w-auto rounded border border-slate-200 bg-slate-50 object-contain p-1" />
                            <div class="text-xs text-slate-500">
                                <p>Logo uploaded.</p>
                                <button wire:click="removeLogo" type="button" class="text-red-400 hover:underline mt-0.5">Remove logo</button>
                            </div>
                        </div>
                    @endif
                    <label class="flex items-center gap-2 cursor-pointer">
                        <div class="flex-1 border border-dashed border-slate-300 rounded-lg px-4 py-3 text-center hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                            @if ($settingLogo)
                                <p class="text-xs text-indigo-600 font-medium">{{ $settingLogo->getClientOriginalName() }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">Click to change</p>
                            @else
                                <x-icon name="photo" class="w-5 h-5 text-slate-400 mx-auto mb-1" />
                                <p class="text-xs text-slate-500">Click to upload PNG / JPG / SVG (max 2 MB)</p>
                            @endif
                        </div>
                        <input type="file" wire:model="settingLogo" accept="image/*" class="sr-only" />
                    </label>
                    @error('settingLogo') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Accent colour --}}
                <div>
                    <p class="text-xs font-semibold text-slate-600 mb-1">Accent Colour</p>
                    <div class="flex items-center gap-3">
                        <input
                            type="color"
                            wire:model.live="settingAccentColor"
                            class="w-10 h-10 rounded cursor-pointer border border-slate-200"
                        />
                        <x-input
                            wire:model.live.debounce.300ms="settingAccentColor"
                            placeholder="#4f46e5"
                            class="font-mono w-32"
                            :error="$errors->first('settingAccentColor')"
                        />
                        <div class="flex gap-1.5">
                            @foreach (['#4f46e5','#059669','#0284c7','#dc2626','#d97706','#7c3aed','#0f172a'] as $preset)
                            <button
                                wire:click="$set('settingAccentColor', '{{ $preset }}')"
                                type="button"
                                title="{{ $preset }}"
                                class="w-6 h-6 rounded-full border-2 {{ $settingAccentColor === $preset ? 'border-slate-800 scale-110' : 'border-transparent' }} transition-all"
                                style="background:{{ $preset }}"
                            ></button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Sections to show --}}
                <div>
                    <p class="text-xs font-semibold text-slate-600 mb-2">Sections to Include</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" wire:model="settingShowAttendance" class="rounded text-indigo-600" />
                            <span class="text-sm text-slate-700">Attendance Summary</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" wire:model="settingShowConduct" class="rounded text-indigo-600" />
                            <span class="text-sm text-slate-700">Conduct Grade</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" wire:model="settingShowGradingScale" class="rounded text-indigo-600" />
                            <span class="text-sm text-slate-700">ECZ Grading Scale</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" wire:model="settingShowSignatures" class="rounded text-indigo-600" />
                            <span class="text-sm text-slate-700">Signature Lines</span>
                        </label>
                    </div>
                </div>

                {{-- Footer note --}}
                <x-input
                    wire:model="settingFooterNote"
                    label="Footer Note (optional)"
                    placeholder="e.g. This report is computer generated and valid without a stamp."
                    :error="$errors->first('settingFooterNote')"
                />

            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('showSettingsModal', false)" label="Cancel" flat />
                    <x-button
                        wire:click="saveSettings"
                        wire:loading.attr="disabled"
                        wire:target="saveSettings"
                        icon="check"
                        label="Save Settings"
                        primary
                        spinner="saveSettings"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         BULK GENERATE MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showBulkModal" title="Generate All Report Cards" blur width="lg">
        <x-card class="relative">
            <div class="flex items-start gap-4 p-1">
            <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-indigo-50">
                <x-icon name="document-duplicate" class="w-6 h-6 text-indigo-600" />
            </div>
            <div class="space-y-2">
                <p class="text-slate-700 font-medium">
                    Generate PDF report cards for all {{ $this->stats['total'] }} students?
                </p>
                <p class="text-slate-500 text-sm">
                    Term <strong>{{ $selectedTerm }}</strong> &middot; Academic Year <strong>{{ $selectedYear }}</strong>
                </p>
                <ul class="text-slate-500 text-sm list-disc list-inside space-y-1 mt-2">
                    <li>Existing PDFs will be overwritten.</li>
                    <li>Classes with more than 30 students are queued in the background.</li>
                    <li>Make sure all assessments and comments are finalised first.</li>
                </ul>
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button wire:click="$set('showBulkModal', false)" label="Cancel" flat />
                <x-button
                    wire:click="generateAll"
                    wire:loading.attr="disabled"
                    wire:target="generateAll"
                    icon="document-arrow-down"
                    label="Generate All PDFs"
                    primary
                    spinner="generateAll"
                />
            </div>
        </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         DELETE / RESET MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showDeleteModal" title="Delete Report PDF" blur width="lg">
        <x-card class="relative">
            <div class="flex items-start gap-4 p-1">
            <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-red-50">
                <x-icon name="exclamation-triangle" class="w-6 h-6 text-red-500" />
            </div>
            <div>
                <p class="text-slate-700 font-medium">Delete the generated PDF?</p>
                <p class="text-slate-500 text-sm mt-1">
                    The record will be reset to draft. Comments and conduct are preserved.
                    You can regenerate at any time.
                </p>
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button wire:click="$set('showDeleteModal', false)" label="Cancel" flat />
                <x-button
                    wire:click="deleteReport"
                    wire:loading.attr="disabled"
                    wire:target="deleteReport"
                    label="Yes, Delete PDF"
                    red
                    spinner="deleteReport"
                />
            </div>
        </x-slot>
        </x-card>
    </x-modal>

</div>
