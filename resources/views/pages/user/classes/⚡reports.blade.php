<?php

use App\Actions\BuildReportData;
use App\Jobs\GenerateBulkReports;
use App\Mail\ReportShared;
use App\Models\ClassRoom;
use App\Models\Report;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Browsershot\Browsershot;
use WireUi\Traits\WireUiActions;
use App\Livewire\Concerns\HasClassRoomRole;

new class extends Component
{
    use WireUiActions, HasClassRoomRole;

    // ──────────────────────────────────────────
    // Props
    // ──────────────────────────────────────────

    public int $classId;

    // ──────────────────────────────────────────
    // Modal flags
    // ──────────────────────────────────────────

    public bool $showPreviewModal = false;
    public bool $showCommentModal = false;
    public bool $showBulkModal    = false;
    public bool $showDeleteModal  = false;

    // ──────────────────────────────────────────
    // Term / year selection (shared header)
    // ──────────────────────────────────────────

    public int $selectedTerm = 1;
    public int $selectedYear;

    // ──────────────────────────────────────────
    // Preview state
    // ──────────────────────────────────────────

    public ?int $previewStudentId = null;

    // ──────────────────────────────────────────
    // Comment / conduct editing (per student)
    // ──────────────────────────────────────────

    public ?int   $commentReportId      = null;
    public string $conductGrade         = '';
    public string $formTeacherComment   = '';
    public string $headTeacherComment   = '';

    // ──────────────────────────────────────────
    // Delete state
    // ──────────────────────────────────────────

    public ?int $deletingReportId = null;

    // ──────────────────────────────────────────
    // Share state
    // ──────────────────────────────────────────

    public bool   $showShareModal  = false;
    public ?int   $shareReportId   = null;
    public string $shareStudentName = '';
    public string $shareUrl         = '';
    public string $shareEmail       = '';
    public bool   $shareSent        = false;

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
    // Share report
    // ──────────────────────────────────────────

    public function openShareModal(int $reportId): void
    {
        $report = Report::forTeacher(Auth::id())->findOrFail($reportId);
        $token  = $report->generateShareToken();

        $this->shareReportId   = $reportId;
        $this->shareStudentName = $report->student->full_name ?? '';
        $this->shareUrl        = route('report.shared', $token);
        $this->shareEmail      = $report->parent_email ?? '';
        $this->shareSent       = false;
        $this->showShareModal  = true;
    }

    public function sendShareEmail(): void
    {
        $this->validate(['shareEmail' => 'required|email|max:255']);

        $report = Report::forTeacher(Auth::id())->with('student')->findOrFail($this->shareReportId);
        $user   = Auth::user();

        $report->update(['parent_email' => $this->shareEmail]);

        Mail::to($this->shareEmail)->queue(new ReportShared(
            report:      $report,
            studentName: $report->student->full_name ?? '',
            teacherName: $user->name,
            schoolName:  $user->school_name ?? '',
        ));

        $this->shareSent = true;
        $this->notification()->success(title: 'Email sent!', description: "Report link sent to {$this->shareEmail}.");
    }

    // ──────────────────────────────────────────
    // Core generation logic
    // ──────────────────────────────────────────

    private function doGenerate(int $studentId): void
    {
        // Ensure the report record exists before building data (so conduct/comments are loaded)
        $report = Report::findOrInitialise($studentId, Auth::id(), $this->selectedTerm, $this->selectedYear);

        $data = (new BuildReportData)->execute(
            $this->classId, $studentId, $this->selectedTerm, $this->selectedYear, Auth::id()
        );

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
                    <a href="{{ route('user.report-settings', $classId) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50 hover:border-indigo-400 transition-colors">
                        <x-icon name="adjustments-horizontal" class="w-4 h-4" />
                        Customise
                    </a>
                    @if($this->stats['generated'] > 0)
                    <a
                        href="{{ route('user.reports.download-zip', $classId) }}?term={{ $selectedTerm }}&year={{ $selectedYear }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-emerald-300 bg-white text-emerald-700 text-sm font-semibold hover:bg-emerald-50 transition-colors"
                    >
                        <x-icon name="archive-box-arrow-down" class="w-4 h-4" />
                        Download ZIP ({{ $this->stats['generated'] }})
                    </a>
                    @endif
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

                                    {{-- Download + Share + Delete (generated only) --}}
                                    @if ($report?->isGenerated())
                                        <a
                                            href="{{ $report->pdf_url }}"
                                            target="_blank"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded text-slate-500 hover:bg-slate-100 transition-colors"
                                            title="Download PDF"
                                        >
                                            <x-icon name="arrow-down-tray" class="w-4 h-4" />
                                        </a>

                                        {{-- Share --}}
                                        <x-button
                                            wire:click="openShareModal({{ $report->id }})"
                                            icon="share"
                                            flat xs
                                            class="text-violet-500"
                                            title="Share with parent"
                                        />

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
         PREVIEW MODAL — iframe renders the actual report card
         template, so it automatically matches any layout setting.
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showPreviewModal" title="Report Card Preview" blur width="3xl">
        <x-card class="relative">
            @if ($previewStudentId)
            <div class="space-y-3 p-1">

                {{-- Student info strip --}}
                @php $ps = $this->students->firstWhere('id', $previewStudentId); @endphp
                @if ($ps)
                <div class="flex items-center justify-between bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3">
                    <div>
                        <p class="font-bold text-indigo-900">{{ $ps->full_name }}</p>
                        <p class="text-sm text-indigo-500">{{ $this->classroom->name }} &middot; Term {{ $selectedTerm }} &middot; {{ $selectedYear }}</p>
                    </div>
                    <a href="{{ route('user.report-settings', $classId) }}"
                       class="text-xs text-indigo-500 hover:text-indigo-700 underline underline-offset-2">
                        Change layout
                    </a>
                </div>
                @endif

                {{-- Actual report card rendered in iframe --}}
                <div class="rounded-lg overflow-hidden border border-slate-200 bg-slate-50">
                    <iframe
                        src="{{ route('user.report-preview', [$classId, $previewStudentId]) }}?term={{ $selectedTerm }}&year={{ $selectedYear }}"
                        style="width: 100%; height: 640px; border: none; display: block;"
                        loading="lazy"
                    ></iframe>
                </div>

            </div>
            @endif

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    @if ($previewStudentId)
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

    {{-- ══════════════════════════════════════════════════════════
         SHARE MODAL — email + WhatsApp
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showShareModal" title="Share Report Card" blur width="lg">
        <x-card class="relative">
            <div class="p-1 space-y-5">

                {{-- Student context --}}
                <div class="flex items-center gap-3 rounded-xl bg-violet-50 border border-violet-200 px-4 py-3">
                    <div class="shrink-0 w-9 h-9 rounded-full bg-violet-200 text-violet-700 flex items-center justify-center font-bold text-sm">
                        {{ strtoupper(substr($shareStudentName, 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-semibold text-violet-900 text-sm">{{ $shareStudentName }}</p>
                        <p class="text-xs text-violet-500">Term {{ $selectedTerm }} · {{ $selectedYear }}</p>
                    </div>
                </div>

                {{-- Shareable link --}}
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Shareable Link</p>
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            readonly
                            value="{{ $shareUrl }}"
                            class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 font-mono"
                            onclick="this.select()"
                        />
                        <button
                            onclick="navigator.clipboard.writeText('{{ $shareUrl }}'); this.textContent='Copied!';"
                            class="shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                        >
                            Copy
                        </button>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Anyone with this link can view the report card — no login required.</p>
                </div>

                {{-- WhatsApp --}}
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Share via WhatsApp</p>
                    <a
                        href="https://wa.me/?text={{ urlencode('Hi, here is the report card for ' . $shareStudentName . ' (Term ' . $selectedTerm . ', ' . $selectedYear . '): ' . $shareUrl) }}"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-500 hover:bg-green-600 text-white font-semibold text-sm px-4 py-2.5 transition"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Send via WhatsApp
                    </a>
                </div>

                {{-- Email --}}
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Send via Email</p>
                    <div class="flex items-start gap-2">
                        <x-input
                            wire:model="shareEmail"
                            type="email"
                            placeholder="parent@example.com"
                            :error="$errors->first('shareEmail')"
                            class="flex-1"
                        />
                        <x-button
                            wire:click="sendShareEmail"
                            wire:loading.attr="disabled"
                            wire:target="sendShareEmail"
                            icon="envelope"
                            label="Send"
                            primary
                            spinner="sendShareEmail"
                        />
                    </div>
                    @if ($shareSent)
                        <p class="text-xs text-emerald-600 font-medium mt-1">&#10003; Email sent to {{ $shareEmail }}</p>
                    @endif
                </div>

            </div>
            <x-slot name="footer">
                <div class="flex justify-end">
                    <x-button wire:click="$set('showShareModal', false)" label="Close" flat />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

</div>
