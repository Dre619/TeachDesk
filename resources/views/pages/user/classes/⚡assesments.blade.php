<?php

use App\Models\Assessment;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use WireUi\Traits\WireUiActions;
use App\Livewire\Concerns\HasClassRoomRole;

new class extends Component
{
    use WireUiActions,HasClassRoomRole;

    // ──────────────────────────────────────────
    // Props
    // ──────────────────────────────────────────

    public int $classId;

    // ──────────────────────────────────────────
    // Modal flags
    // ──────────────────────────────────────────

    public bool $showEntryModal   = false;  // bulk spreadsheet entry
    public bool $showEditModal    = false;  // single-record edit
    public bool $showDeleteModal  = false;
    public bool $showDetailModal  = false;
    public bool $showSummaryModal = false;

    // ──────────────────────────────────────────
    // Bulk entry state
    // Shared header fields apply to all rows in the sheet.
    // entryRows keyed by student_id.
    // ──────────────────────────────────────────

    public int    $entryTerm     = 1;
    public int    $entryYear;
    public string $entryType     = 'test';
    public string $entryMaxScore = '100';
    public string $entrySubject  = '';

    /**
     * [ student_id => ['score' => '', 'remarks' => '', 'existing_id' => null] ]
     */
    public array $entryRows   = [];
    public array $entryErrors = [];

    // ──────────────────────────────────────────
    // Single-record edit state
    // ──────────────────────────────────────────

    public ?int   $editingId    = null;
    public string $editScore    = '';
    public string $editMaxScore = '100';
    public string $editRemarks  = '';
    public int    $editTerm     = 1;
    public int    $editYear;
    public string $editType     = 'test';

    // ──────────────────────────────────────────
    // Delete / detail state
    // ──────────────────────────────────────────

    public ?int        $deletingId = null;
    public ?Assessment $viewing    = null;

    // ──────────────────────────────────────────
    // Summary modal state
    // ──────────────────────────────────────────

    public ?int    $summaryStudentId   = null;
    public int     $summaryTerm        = 1;
    public int     $summaryYear;
    public array   $summaryData        = [];
    public ?string $summaryStudentName = null;

    // ──────────────────────────────────────────
    // Filters
    // ──────────────────────────────────────────

    public string $search        = '';
    public string $filterStudent = '';
    public string $filterTerm    = '';
    public string $filterType    = '';
    public string $filterYear    = '';
    public string $filterGrade   = '';

    // ──────────────────────────────────────────
    // View mode
    // ──────────────────────────────────────────

    public string $viewMode = 'list'; // list | gradebook

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function mount(int $classId): void
    {
        $class = ClassRoom::forTeacher(Auth::id())->findOrFail($classId);
        $this->classId     = $classId;
        $this->entryYear   = (int) date('Y');
        $this->editYear    = (int) date('Y');
        $this->summaryYear = (int) date('Y');
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
        $user_id = $this->classroom->members->where('user_id',auth()->id())->where('status','accepted')->first()->invited_by ?? Auth::id();
        return Student::forTeacher($user_id)
            ->inClass($this->classId)
            ->whereNull('deleted_at')
            ->alphabetical()
            ->get();
    }

    #[Computed]
    public function assessments()
    {
        return Assessment::with('student')
            ->when(auth()->id()!=$this->classroom->user_id,function($q){
                $q->where('user_id', Auth::id());
            })
            ->forClass($this->classId)
            ->when($this->search, fn ($q) =>
                $q->whereHas('student', fn ($q) =>
                    $q->where('first_name', 'like', "%{$this->search}%")
                      ->orWhere('last_name',  'like', "%{$this->search}%")
                ))
            ->when($this->filterStudent !== '', fn ($q) => $q->forStudent((int) $this->filterStudent))
            ->when($this->filterTerm    !== '', fn ($q) => $q->forTerm((int) $this->filterTerm))
            ->when($this->filterYear    !== '', fn ($q) => $q->where('academic_year', (int) $this->filterYear))
            ->when($this->filterType    !== '', fn ($q) => $q->where('type', $this->filterType))
            ->when($this->filterGrade   !== '', fn ($q) => $q->where('grade', $this->filterGrade))
            ->latest()
            ->get();
    }

    #[Computed]
    public function availableYears(): array
    {
        return Assessment::where('user_id', Auth::id())
            ->forClass($this->classId)
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year')
            ->toArray();
    }

    #[Computed]
    public function gradebook(): array
    {
        $term = $this->filterTerm !== '' ? (int) $this->filterTerm : null;
        $year = $this->filterYear !== '' ? (int) $this->filterYear : null;

        $query = Assessment::with('student')
            ->where('user_id', Auth::id())
            ->forClass($this->classId);

        if ($term)     { $query->forTerm($term, $year); }
        elseif ($year) { $query->where('academic_year', $year); }

        $all    = $query->get();
        $result = [];

        foreach ($this->students as $student) {
            $sa = $all->where('student_id', $student->id);

            $byType = $sa->groupBy('type')->map(function ($rows) {
                $avg   = round($rows->avg('percentage'), 1);
                $grade = (new Assessment(['score' => $avg, 'max_score' => 100]))->calculateGrade();
                return ['avg' => $avg, 'grade' => $grade, 'count' => $rows->count()];
            })->toArray();

            $result[$student->id] = [
                'student' => $student,
                'types'   => $byType,
                'overall' => $sa->isNotEmpty() ? round($sa->avg('percentage'), 1) : null,
            ];
        }

        return $result;
    }

    #[Computed]
    public function gradebookTypes(): array
    {
        $types = [];
        foreach ($this->gradebook as $row) {
            $types = array_merge($types, array_keys($row['types']));
        }
        return array_values(array_unique($types));
    }

    /**
     * The subjects the current user is allowed to enter marks for.
     * Form teacher → their class subject only.
     * Subject teacher → all their assigned subjects (supports multi-subject & co-teaching).
     *
     * @return string[]
     */
    #[Computed]
    public function entrySubjects(): array
    {
        return $this->isFormTeacher()
            ? [$this->classroom->subject]
            : $this->mySubjects;
    }

    // ──────────────────────────────────────────
    // Bulk Entry Modal
    // ──────────────────────────────────────────

    public function openEntryModal(): void
    {
        $this->entryErrors = [];
        $this->entryRows   = [];

        // Auto-select subject: keep current selection if still valid, otherwise pick the first option.
        $subjects = $this->entrySubjects;
        if (empty($this->entrySubject) || ! in_array($this->entrySubject, $subjects, true)) {
            $this->entrySubject = $subjects[0] ?? $this->classroom->subject;
        }

        foreach ($this->students as $student) {
            $this->entryRows[$student->id] = [
                'score'       => '',
                'remarks'     => '',
                'existing_id' => null,
            ];
        }

        // Pre-fill any existing scores for default header values
        $this->syncExistingScores();
        $this->showEntryModal = true;
    }

    /**
     * Called whenever term/type/year header changes so existing DB records
     * are loaded back into the grid automatically.
     */
    public function syncExistingScores(): void
    {
        $existing = Assessment::where('user_id', Auth::id())
            ->forClass($this->classId)
            ->where('subject', $this->entrySubject)
            ->where('term', $this->entryTerm)
            ->where('academic_year', $this->entryYear)
            ->where('type', $this->entryType)
            ->get()
            ->keyBy('student_id');

        foreach ($this->entryRows as $studentId => $_) {
            $record = $existing->get($studentId);
            $this->entryRows[$studentId] = [
                'score'       => $record ? rtrim(rtrim((string) $record->score, '0'), '.') : '',
                'remarks'     => $record ? ($record->remarks ?? '') : '',
                'existing_id' => $record?->id,
            ];
        }

        $this->entryErrors = [];
    }

    public function saveEntrySheet(): void
    {
        $this->entryErrors = [];
        $maxScore          = (float) $this->entryMaxScore;

        if ($maxScore <= 0) {
            $this->entryErrors['_header'] = 'Max score must be greater than 0.';
            return;
        }

        $saved   = 0;
        $skipped = 0;

        foreach ($this->entryRows as $studentId => $row) {
            $score = trim($row['score']);

            if ($score === '') {
                $skipped++;
                continue;
            }

            if (! is_numeric($score) || (float) $score < 0) {
                $this->entryErrors[$studentId] = 'Must be a valid number ≥ 0.';
                continue;
            }

            if ((float) $score > $maxScore) {
                $this->entryErrors[$studentId] = "Cannot exceed {$maxScore}.";
                continue;
            }

            $data = [
                'student_id'    => $studentId,
                'class_id'      => $this->classId,
                'user_id'       => Auth::id(),
                'subject'       => $this->entrySubject,
                'term'          => $this->entryTerm,
                'academic_year' => $this->entryYear,
                'type'          => $this->entryType,
                'score'         => (float) $score,
                'max_score'     => $maxScore,
                'remarks'       => $row['remarks'] ?: null,
            ];

            if ($row['existing_id']) {
                Assessment::find($row['existing_id'])?->update($data);
            } else {
                Assessment::create($data);
                // Update so a second save within the same session updates rather than duplicates
                $this->entryRows[$studentId]['existing_id'] = Assessment::where('student_id', $studentId)
                    ->where('class_id', $this->classId)
                    ->where('subject', $this->entrySubject)
                    ->where('term', $this->entryTerm)
                    ->where('academic_year', $this->entryYear)
                    ->where('type', $this->entryType)
                    ->latest()
                    ->value('id');
            }

            $saved++;
        }

        if (! empty($this->entryErrors)) {
            $this->notification()->error(
                title:       'Fix errors first',
                description: count($this->entryErrors) . ' row(s) have invalid scores.',
            );
            return;
        }

        if ($saved === 0) {
            $this->notification()->warning(title: 'Nothing saved', description: 'No scores were entered.');
            return;
        }

        $this->showEntryModal = false;
        $this->bustCache();

        $this->notification()->success(
            title:       'Scores saved!',
            description: "{$saved} scores recorded. {$skipped} left blank.",
        );
    }

    // ──────────────────────────────────────────
    // Single-record Edit
    // ──────────────────────────────────────────

    public function openEditModal(int $id): void
    {
        $a = $this->findOwned($id);

        $this->editingId    = $a->id;
        $this->editScore    = rtrim(rtrim((string) $a->score, '0'), '.');
        $this->editMaxScore = rtrim(rtrim((string) $a->max_score, '0'), '.');
        $this->editRemarks  = $a->remarks ?? '';
        $this->editTerm     = $a->term;
        $this->editYear     = $a->academic_year;
        $this->editType     = $a->type;

        $this->showEditModal = true;
    }

    public function updateAssessment(): void
    {
        $this->validate([
            'editScore'    => 'required|numeric|min:0',
            'editMaxScore' => 'required|numeric|min:1',
            'editTerm'     => 'required|in:1,2,3',
            'editYear'     => 'required|integer|min:2000|max:2100',
            'editType'     => 'required|in:test,exam,assignment,ca,other',
            'editRemarks'  => 'nullable|string|max:500',
        ]);

        if ((float) $this->editScore > (float) $this->editMaxScore) {
            $this->addError('editScore', 'Score cannot exceed max score.');
            return;
        }

        $this->findOwned($this->editingId)->update([
            'score'         => (float) $this->editScore,
            'max_score'     => (float) $this->editMaxScore,
            'term'          => $this->editTerm,
            'academic_year' => $this->editYear,
            'type'          => $this->editType,
            'remarks'       => $this->editRemarks ?: null,
        ]);

        $this->showEditModal = false;
        $this->editingId     = null;
        $this->bustCache();

        $this->notification()->success(title: 'Updated!', description: 'Assessment record updated.');
    }

    // ──────────────────────────────────────────
    // Delete
    // ──────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deletingId      = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            $this->findOwned($this->deletingId)->delete();
            $this->bustCache();
            $this->notification()->warning(title: 'Deleted', description: 'Assessment removed.');
        }
        $this->showDeleteModal = false;
        $this->deletingId      = null;
    }

    // ──────────────────────────────────────────
    // Detail
    // ──────────────────────────────────────────

    public function openDetailModal(int $id): void
    {
        $this->viewing         = $this->findOwned($id)->load('student');
        $this->showDetailModal = true;
    }

    // ──────────────────────────────────────────
    // Summary
    // ──────────────────────────────────────────

    public function openSummaryModal(int $studentId): void
    {
        $student = $this->students->find($studentId);
        if (! $student) return;

        $this->summaryStudentId   = $studentId;
        $this->summaryStudentName = $student->full_name;
        $this->summaryData        = Assessment::summaryForStudent($studentId, $this->summaryTerm, $this->summaryYear);
        $this->showSummaryModal   = true;
    }

    public function refreshSummary(): void
    {
        if ($this->summaryStudentId) {
            $this->summaryData = Assessment::summaryForStudent(
                $this->summaryStudentId,
                $this->summaryTerm,
                $this->summaryYear,
            );
        }
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    private function findOwned(int $id): Assessment
    {
        return Assessment::where('user_id', Auth::id())
            ->forClass($this->classId)
            ->findOrFail($id);
    }

    private function bustCache(): void
    {
        unset($this->assessments, $this->gradebook, $this->gradebookTypes, $this->entrySubjects);
    }
};
?>

{{-- resources/views/livewire/assessment-manager.blade.php --}}
<div class="min-h-screen bg-slate-50 font-sans">

    {{-- ══════════════════════════════════════════════════════════
         HEADER
    ══════════════════════════════════════════════════════════ --}}
    <div class="bg-white border-b border-slate-200 px-6 py-5">
        <div class="mx-auto">
            <p class="text-xs text-slate-400 mb-1 uppercase tracking-wide font-medium">
                {{ $this->classroom->name }} &middot; {{ $this->classroom->academic_year }}
            </p>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Assessments</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        <span class="font-medium text-indigo-600">{{ implode(', ', $this->entrySubjects) }}</span>
                        &middot; {{ $this->assessments->count() }} <!--{{ Str::plural('record', $this->assessments->count()) }}--->
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    {{-- View toggle --}}
                    <div class="flex items-center bg-slate-100 rounded-lg p-1 gap-1">
                        <button wire:click="$set('viewMode','list')" @class([
                            'px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                            'bg-white shadow text-slate-800'      => $viewMode === 'list',
                            'text-slate-500 hover:text-slate-700' => $viewMode !== 'list',
                        ])><x-icon name="list-bullet" class="w-4 h-4 inline -mt-0.5 mr-1" />List</button>
                        <button wire:click="$set('viewMode','gradebook')" @class([
                            'px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                            'bg-white shadow text-slate-800'      => $viewMode === 'gradebook',
                            'text-slate-500 hover:text-slate-700' => $viewMode !== 'gradebook',
                        ])><x-icon name="table-cells" class="w-4 h-4 inline -mt-0.5 mr-1" />Gradebook</button>
                    </div>
                    <x-button wire:click="openEntryModal" icon="clipboard-document-check" label="Enter Scores" primary class="w-full sm:w-auto" />
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto px-6 py-8 space-y-6">

        {{-- ══════════════════════════════════════════════════════════
             FILTER BAR
        ══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <div class="col-span-2 sm:col-span-1">
                    <x-input wire:model.live.debounce.300ms="search" placeholder="Search student…" icon="magnifying-glass" shadowless />
                </div>
                <x-native-select wire:model.live="filterStudent" shadowless>
                    <option value="">All students</option>
                    @foreach ($this->students as $s)
                        <option value="{{ $s->id }}">{{ $s->full_name }}</option>
                    @endforeach
                </x-native-select>
                <x-native-select wire:model.live="filterTerm" shadowless>
                    <option value="">All terms</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </x-native-select>
                <x-native-select wire:model.live="filterType" shadowless>
                    <option value="">All types</option>
                    <option value="test">Class Test</option>
                    <option value="exam">Examination</option>
                    <option value="assignment">Assignment</option>
                    <option value="ca">Cont. Assessment</option>
                    <option value="other">Other</option>
                </x-native-select>
                <x-native-select wire:model.live="filterGrade" shadowless>
                    <option value="">All grades</option>
                    @foreach (['A','B','C','D','E','F'] as $g)
                        <option value="{{ $g }}">Grade {{ $g }}</option>
                    @endforeach
                </x-native-select>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             STATS STRIP
        ══════════════════════════════════════════════════════════ --}}
        @if ($this->assessments->isNotEmpty())
        @php
            $avgPercent = round($this->assessments->avg('percentage'), 1);
            $passCount  = $this->assessments->filter(fn ($a) => $a->percentage >= 50)->count();
            $failCount  = $this->assessments->count() - $passCount;
            $topScore   = $this->assessments->max('percentage');
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Class Average</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ $avgPercent }}%</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Top Score</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $topScore }}%</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Passed ≥50%</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $passCount }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Failed</p>
                <p class="text-2xl font-bold text-red-500 mt-1">{{ $failCount }}</p>
            </div>
        </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════
             EMPTY STATE
        ══════════════════════════════════════════════════════════ --}}
        @if ($this->assessments->isEmpty())
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                <x-icon name="clipboard-document-list" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-500 font-medium">No assessments recorded yet.</p>
                <p class="text-slate-400 text-sm mt-1">Use "Enter Scores" to record the whole class at once.</p>
                <x-button wire:click="openEntryModal" label="Enter Scores" primary class="mt-5" />
            </div>

        {{-- ══════════════════════════════════════════════════════════
             LIST VIEW
        ══════════════════════════════════════════════════════════ --}}
        @elseif ($viewMode === 'list')
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-left">
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Student</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden sm:table-cell">Type</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden md:table-cell">Term / Year</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Score</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Grade</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->assessments as $a)
                        @php
                            $pct      = $a->percentage;
                            $barColor = match(true) {
                                $pct >= 80 => 'bg-emerald-500',
                                $pct >= 60 => 'bg-blue-500',
                                $pct >= 50 => 'bg-amber-500',
                                default    => 'bg-red-500',
                            };
                            $gc = match($a->grade) {
                                'A' => 'bg-emerald-100 text-emerald-800',
                                'B' => 'bg-teal-100 text-teal-800',
                                'C' => 'bg-blue-100 text-blue-800',
                                'D' => 'bg-amber-100 text-amber-800',
                                'E' => 'bg-orange-100 text-orange-800',
                                default => 'bg-red-100 text-red-800',
                            };
                        @endphp
                        <tr wire:key="a-{{ $a->id }}" class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($a->student->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($a->student->last_name ?? '', 0, 1)) }}
                                    </div>
                                    <span class="font-medium text-slate-800">{{ $a->student->full_name ?? '—' }}</span>
                                </div>
                            </td>
                             <td class="px-5 py-3 hidden sm:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                                    {{ $a->subject }}
                                </span>
                            </td>
                            <td class="px-5 py-3 hidden sm:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                                    {{ $a->type_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-slate-500 text-xs hidden md:table-cell">
                                Term {{ $a->term }} &middot; {{ $a->academic_year }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-slate-100 rounded-full h-1.5 hidden sm:block">
                                        <div class="{{ $barColor }} h-1.5 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                    </div>
                                    <span class="text-slate-700 font-semibold whitespace-nowrap">
                                        {{ $a->score }}/{{ $a->max_score }}
                                        <span class="text-slate-400 font-normal">({{ $pct }}%)</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold {{ $gc }}">
                                    {{ $a->grade }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <x-button wire:click="openDetailModal({{ $a->id }})" icon="eye"    flat xs class="text-slate-500" />
                                    <x-button wire:click="openEditModal({{ $a->id }})"   icon="pencil" flat xs class="text-indigo-600" />
                                    <x-button wire:click="confirmDelete({{ $a->id }})"  icon="trash"  flat xs class="text-red-500" />
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        {{-- ══════════════════════════════════════════════════════════
             GRADEBOOK VIEW
        ══════════════════════════════════════════════════════════ --}}
        @else
            @php $types = $this->gradebookTypes; $rows = $this->gradebook; @endphp
            @if (empty($types))
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-16 text-center">
                    <p class="text-slate-400 text-sm">No gradebook data for the selected filters.</p>
                </div>
            @else
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-800 text-white text-left">
                                <th class="px-5 py-3 font-semibold text-xs uppercase tracking-wide sticky left-0 bg-slate-800 z-10 min-w-[180px]">Student</th>
                                @foreach ($types as $type)
                                    @php
                                        $typeLabel = match($type) {
                                            'test'       => 'Class Test',
                                            'exam'       => 'Exam',
                                            'assignment' => 'Assignment',
                                            'ca'         => 'CA',
                                            default      => ucfirst($type),
                                        };
                                    @endphp
                                    <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-center whitespace-nowrap">{{ $typeLabel }}</th>
                                @endforeach
                                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-center">Overall</th>
                                <th class="px-4 py-3 text-xs uppercase tracking-wide text-center">Summary</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rows as $studentId => $row)
                            @php
                                $overall      = $row['overall'];
                                $og           = null;
                                if ($overall !== null) {
                                    $og = (new App\Models\Assessment(['score' => $overall, 'max_score' => 100]))->calculateGrade();
                                }
                                $ocColor = match($og) {
                                    'A' => 'bg-emerald-100 text-emerald-800',
                                    'B' => 'bg-teal-100 text-teal-800',
                                    'C' => 'bg-blue-100 text-blue-800',
                                    'D' => 'bg-amber-100 text-amber-800',
                                    'E' => 'bg-orange-100 text-orange-800',
                                    'F' => 'bg-red-100 text-red-800',
                                    default => '',
                                };
                            @endphp
                            <tr wire:key="gb-{{ $studentId }}" class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-3 sticky left-0 bg-white z-10">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                                            {{ strtoupper(substr($row['student']->first_name, 0, 1)) }}{{ strtoupper(substr($row['student']->last_name, 0, 1)) }}
                                        </div>
                                        <span class="font-medium text-slate-800">{{ $row['student']->full_name }}</span>
                                    </div>
                                </td>
                                @foreach ($types as $type)
                                @php
                                    $cell      = $row['types'][$type] ?? null;
                                    $cellColor = match($cell['grade'] ?? null) {
                                        'A' => 'text-emerald-700 font-bold',
                                        'B' => 'text-teal-700 font-bold',
                                        'C' => 'text-blue-700 font-semibold',
                                        'D' => 'text-amber-700 font-semibold',
                                        'E' => 'text-orange-700',
                                        'F' => 'text-red-600',
                                        default => 'text-slate-300',
                                    };
                                @endphp
                                <td class="px-4 py-3 text-center">
                                    @if ($cell)
                                        <div class="{{ $cellColor }} text-sm">{{ $cell['grade'] }}</div>
                                        <div class="text-xs text-slate-400">{{ $cell['avg'] }}%</div>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                @endforeach
                                <td class="px-4 py-3 text-center">
                                    @if ($overall !== null)
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold {{ $ocColor }}">{{ $og }}</span>
                                        <div class="text-xs text-slate-400 mt-0.5">{{ $overall }}%</div>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-button wire:click="openSummaryModal({{ $studentId }})" icon="document-chart-bar" flat xs class="text-indigo-600" />
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        @endif

    </div>{{-- /--}}


    {{-- ══════════════════════════════════════════════════════════
         BULK SCORE ENTRY MODAL  (spreadsheet style)
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showEntryModal" title="Enter Scores" blur persistent width="3xl">

        <x-card class="relative">
            {{-- ── Shared header ──────────────────────────────── --}}
        <div class="border-b border-slate-200 pb-4 mb-1">
            <p class="text-xs text-slate-500 mb-3">
                Set the assessment details below, then fill in each student's score.
                Rows left blank will be skipped. Changing Term / Type / Year auto-loads
                any existing scores.
            </p>

            @if (isset($entryErrors['_header']))
                <p class="text-red-500 text-xs mb-2">{{ $entryErrors['_header'] }}</p>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-native-select
                    wire:model.live="entryTerm"
                    wire:change="syncExistingScores"
                    label="Term"
                >
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </x-native-select>

                <x-input
                    wire:model.live.debounce.600ms="entryYear"
                    wire:change="syncExistingScores"
                    label="Year"
                    type="number"
                />

                <x-native-select
                    wire:model.live="entryType"
                    wire:change="syncExistingScores"
                    label="Type"
                >
                    <option value="test">Class Test</option>
                    <option value="exam">Examination</option>
                    <option value="assignment">Assignment</option>
                    <option value="ca">Cont. Assessment</option>
                    <option value="other">Other</option>
                </x-native-select>

                <x-input
                    wire:model.live.debounce.400ms="entryMaxScore"
                    label="Max Score"
                    type="number"
                    min="1"
                />
            </div>

            {{-- Subject selector / badge --}}
            <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                <x-icon name="academic-cap" class="w-4 h-4 text-indigo-400" />
                Subject:
                @if (count($this->entrySubjects) > 1)
                    {{-- Multi-subject teacher: let them pick which subject this sheet is for --}}
                    <select
                        wire:model.live="entrySubject"
                        wire:change="syncExistingScores"
                        class="text-xs font-semibold text-indigo-600 border-0 border-b border-indigo-300 bg-transparent focus:outline-none focus:ring-0 cursor-pointer"
                    >
                        @foreach ($this->entrySubjects as $subj)
                            <option value="{{ $subj }}">{{ $subj }}</option>
                        @endforeach
                    </select>
                @else
                    <span class="font-semibold text-indigo-600">{{ $this->entrySubjects[0] ?? $this->classroom->subject }}</span>
                @endif
                &middot; Class: <span class="font-semibold text-slate-700">{{ $this->classroom->name }}</span>
            </div>
        </div>

        {{-- ── Student score grid ──────────────────────────── --}}
        <div class="overflow-y-auto" style="max-height: 55vh;">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-slate-100 border-b border-slate-200">
                        <th class="px-4 py-2.5 text-left font-semibold text-slate-600 text-xs uppercase tracking-wide w-8">#</th>
                        <th class="px-4 py-2.5 text-left font-semibold text-slate-600 text-xs uppercase tracking-wide">Student</th>
                        <th class="px-4 py-2.5 text-center font-semibold text-slate-600 text-xs uppercase tracking-wide w-32">
                            Score <span class="font-normal text-slate-400">/ {{ $entryMaxScore ?: '—' }}</span>
                        </th>
                        <th class="px-4 py-2.5 text-center font-semibold text-slate-600 text-xs uppercase tracking-wide w-20">Grade</th>
                        <th class="px-4 py-2.5 text-left font-semibold text-slate-600 text-xs uppercase tracking-wide">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->students as $i => $student)
                    @php
                        $row      = $entryRows[$student->id] ?? ['score' => '', 'remarks' => '', 'existing_id' => null];
                        $hasScore = $row['score'] !== '' && is_numeric($row['score']);
                        $hasError = isset($entryErrors[$student->id]);
                        $isEdit   = ! empty($row['existing_id']);

                        // Live grade preview
                        $previewGrade = null;
                        if ($hasScore && (float)($entryMaxScore ?: 0) > 0) {
                            $pct          = ((float)$row['score'] / (float)$entryMaxScore) * 100;
                            $previewGrade = 'F';
                            foreach (App\Models\Assessment::GRADING_SCALE as $threshold => $g) {
                                if ($pct >= $threshold) { $previewGrade = $g; break; }
                            }
                        }
                        $pgColor = match($previewGrade) {
                            'A' => 'bg-emerald-100 text-emerald-700',
                            'B' => 'bg-teal-100 text-teal-700',
                            'C' => 'bg-blue-100 text-blue-700',
                            'D' => 'bg-amber-100 text-amber-700',
                            'E' => 'bg-orange-100 text-orange-700',
                            'F' => 'bg-red-100 text-red-700',
                            default => 'bg-slate-100 text-slate-400',
                        };
                    @endphp
                    <tr wire:key="er-{{ $student->id }}" @class([
                        'transition-colors',
                        'bg-white hover:bg-slate-50'    => ! $hasError,
                        'bg-red-50 hover:bg-red-50/80'  => $hasError,
                        'bg-indigo-50/40'               => $isEdit && ! $hasError,
                    ])>
                        {{-- Row number --}}
                        <td class="px-4 py-2 text-slate-400 text-xs">{{ $i + 1 }}</td>

                        {{-- Student name --}}
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($student->first_name, 0, 1)) }}
                                </div>
                                <div>
                                    <span class="font-medium text-slate-800">{{ $student->full_name }}</span>
                                    @if ($isEdit)
                                        <span class="ml-1.5 text-xs text-indigo-400">(editing)</span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Score input --}}
                        <td class="px-4 py-2">
                            <input
                                wire:model.live="entryRows.{{ $student->id }}.score"
                                type="number"
                                step="0.01"
                                min="0"
                                max="{{ $entryMaxScore }}"
                                placeholder="—"
                                @class([
                                    'w-full text-center rounded-md border text-sm py-1.5 px-2 focus:outline-none focus:ring-2',
                                    'border-slate-300 focus:ring-indigo-500 focus:border-indigo-500' => ! $hasError,
                                    'border-red-400 bg-red-50 focus:ring-red-400'                   => $hasError,
                                ])
                            />
                            @if ($hasError)
                                <p class="text-red-500 text-xs mt-0.5 text-center">{{ $entryErrors[$student->id] }}</p>
                            @endif
                        </td>

                        {{-- Live grade preview --}}
                        <td class="px-4 py-2 text-center">
                            <span @class([
                                'inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold',
                                $pgColor,
                            ])>
                                {{ $previewGrade ?? '—' }}
                            </span>
                        </td>

                        {{-- Remarks --}}
                        <td class="px-4 py-2">
                            <input
                                wire:model.lazy="entryRows.{{ $student->id }}.remarks"
                                type="text"
                                placeholder="Optional…"
                                class="w-full rounded-md border border-slate-300 text-sm py-1.5 px-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-slot name="footer">
            <div class="flex items-center justify-between w-full">
                <p class="text-xs text-slate-400">
                    {{ $this->students->count() }} students &middot; Leave score blank to skip
                </p>
                <div class="flex gap-3">
                    <x-button wire:click="$set('showEntryModal', false)" label="Cancel" flat />
                    <x-button
                        wire:click="saveEntrySheet"
                        wire:loading.attr="disabled"
                        wire:target="saveEntrySheet"
                        icon="check"
                        label="Save Scores"
                        primary
                        spinner="saveEntrySheet"
                    />
                </div>
            </div>
        </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         SINGLE EDIT MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showEditModal" title="Edit Assessment" blur persistent width="xl">
        <x-card class="relative">
            <div class="space-y-4 p-1">
            <div class="grid grid-cols-3 gap-4">
                <x-native-select wire:model="editTerm" label="Term" :error="$errors->first('editTerm')">
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </x-native-select>
                <x-input wire:model="editYear" label="Year" type="number" :error="$errors->first('editYear')" />
                <x-native-select wire:model="editType" label="Type" :error="$errors->first('editType')">
                    <option value="test">Class Test</option>
                    <option value="exam">Examination</option>
                    <option value="assignment">Assignment</option>
                    <option value="ca">Cont. Assessment</option>
                    <option value="other">Other</option>
                </x-native-select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-input wire:model.live="editScore"    label="Score"     type="number" step="0.01" :error="$errors->first('editScore')" />
                <x-input wire:model.live="editMaxScore" label="Max Score" type="number" step="0.01" :error="$errors->first('editMaxScore')" />
            </div>

            {{-- Live grade preview --}}
            @if ($editScore !== '' && $editMaxScore !== '' && (float)$editMaxScore > 0)
            @php
                $ep = round(((float)$editScore / (float)$editMaxScore) * 100, 1);
                $eg = 'F';
                foreach (App\Models\Assessment::GRADING_SCALE as $t => $g) { if ($ep >= $t) { $eg = $g; break; } }
                $egc = match($eg) {
                    'A'=>'bg-emerald-50 border-emerald-300 text-emerald-700',
                    'B'=>'bg-teal-50 border-teal-300 text-teal-700',
                    'C'=>'bg-blue-50 border-blue-300 text-blue-700',
                    'D'=>'bg-amber-50 border-amber-300 text-amber-700',
                    'E'=>'bg-orange-50 border-orange-300 text-orange-700',
                    default=>'bg-red-50 border-red-300 text-red-700',
                };
            @endphp
            <div class="flex items-center gap-3 {{ $egc }} border rounded-lg px-4 py-2.5">
                <span class="text-2xl font-extrabold">{{ $eg }}</span>
                <div>
                    <p class="text-sm font-semibold">{{ $ep }}%</p>
                    <p class="text-xs opacity-75">ECZ Grade Preview</p>
                </div>
            </div>
            @endif

            <x-textarea wire:model="editRemarks" label="Remarks (optional)" rows="2" :error="$errors->first('editRemarks')" />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button wire:click="$set('showEditModal', false)" label="Cancel" flat />
                <x-button wire:click="updateAssessment" wire:loading.attr="disabled" wire:target="updateAssessment" label="Update" primary spinner="updateAssessment" />
            </div>
        </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         DELETE MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showDeleteModal" title="Delete Assessment" blur width="lg">
        <x-card class="relative">
            <div class="flex items-start gap-4 p-1">
            <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-red-50">
                <x-icon name="exclamation-triangle" class="w-6 h-6 text-red-500" />
            </div>
            <div>
                <p class="text-slate-700 font-medium">Delete this assessment record?</p>
                <p class="text-slate-500 text-sm mt-1">This action cannot be undone.</p>
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button wire:click="$set('showDeleteModal', false)" label="Cancel" flat />
                <x-button wire:click="delete" wire:loading.attr="disabled" wire:target="delete" label="Yes, Delete" red spinner="delete" />
            </div>
        </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         DETAIL MODAL
    ══════════════════════════════════════════════════════════ --}}
    @if ($viewing)
    @php
        $dpct = $viewing->percentage;
        $dgc  = match($viewing->grade) {
            'A'=>'bg-emerald-100 text-emerald-800 border-emerald-300',
            'B'=>'bg-teal-100 text-teal-800 border-teal-300',
            'C'=>'bg-blue-100 text-blue-800 border-blue-300',
            'D'=>'bg-amber-100 text-amber-800 border-amber-300',
            'E'=>'bg-orange-100 text-orange-800 border-orange-300',
            default=>'bg-red-100 text-red-800 border-red-300',
        };
        $dbar = match(true) {
            $dpct >= 80 => 'bg-emerald-500',
            $dpct >= 60 => 'bg-blue-500',
            $dpct >= 50 => 'bg-amber-500',
            default     => 'bg-red-500',
        };
    @endphp
    <x-modal wire:model.live="showDetailModal" title="Assessment Detail" blur width="xl">
        <x-card class="relative">
            <div class="space-y-5 p-1">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold">
                        {{ strtoupper(substr($viewing->student->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($viewing->student->last_name ?? '', 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-bold text-slate-800">{{ $viewing->student->full_name ?? '—' }}</p>
                        <p class="text-xs text-slate-400">{{ $viewing->subject }} &middot; {{ $viewing->type_label }}</p>
                    </div>
                </div>
                <div class="flex flex-col items-center">
                    <span class="text-4xl font-extrabold border-2 rounded-2xl w-16 h-16 flex items-center justify-center {{ $dgc }}">
                        {{ $viewing->grade }}
                    </span>
                    <span class="text-xs text-slate-400 mt-1">ECZ Grade</span>
                </div>
            </div>
            <div>
                <div class="flex justify-between text-xs text-slate-500 mb-1">
                    <span>{{ $viewing->score }} / {{ $viewing->max_score }}</span>
                    <span>{{ $dpct }}%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2.5">
                    <div class="{{ $dbar }} h-2.5 rounded-full" style="width: {{ min($dpct,100) }}%"></div>
                </div>
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm border-t border-slate-100 pt-4">
                <div>
                    <dt class="text-slate-400 text-xs uppercase tracking-wide font-medium">Term / Year</dt>
                    <dd class="mt-1 text-slate-700 font-semibold">Term {{ $viewing->term }} &middot; {{ $viewing->academic_year }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400 text-xs uppercase tracking-wide font-medium">Type</dt>
                    <dd class="mt-1 text-slate-700 font-semibold">{{ $viewing->type_label }}</dd>
                </div>
                @if ($viewing->remarks)
                <div class="col-span-2">
                    <dt class="text-slate-400 text-xs uppercase tracking-wide font-medium">Remarks</dt>
                    <dd class="mt-1 text-slate-700">{{ $viewing->remarks }}</dd>
                </div>
                @endif
            </dl>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button wire:click="openEditModal({{ $viewing->id }})" icon="pencil" label="Edit" outline />
                <x-button wire:click="$set('showDetailModal', false)" label="Close" flat />
            </div>
        </x-slot>
        </x-card>
    </x-modal>
    @endif


    {{-- ══════════════════════════════════════════════════════════
         STUDENT SUMMARY MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showSummaryModal" :title="'Term Summary — ' . ($summaryStudentName ?? '')" blur width="2xl">
        <x-card class="relative">
            <div class="space-y-4 p-1">
            <div class="flex items-center gap-3">
                <x-native-select wire:model.live="summaryTerm" shadowless class="flex-1">
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </x-native-select>
                <x-input wire:model.live.debounce.500ms="summaryYear" type="number" placeholder="Year" shadowless class="flex-1" />
                <x-button wire:click="refreshSummary" icon="arrow-path" flat xs class="text-indigo-600" label="Refresh" />
            </div>

            @if (empty($summaryData))
                <p class="text-slate-400 text-sm text-center py-8">No assessments for this term.</p>
            @else
                <div class="space-y-3">
                    @foreach ($summaryData as $subject => $data)
                    @php
                        $sg = $data['grade'];
                        $sc = match($sg) {
                            'A'=>['bar'=>'bg-emerald-500','badge'=>'bg-emerald-100 text-emerald-800'],
                            'B'=>['bar'=>'bg-teal-500','badge'=>'bg-teal-100 text-teal-800'],
                            'C'=>['bar'=>'bg-blue-500','badge'=>'bg-blue-100 text-blue-800'],
                            'D'=>['bar'=>'bg-amber-500','badge'=>'bg-amber-100 text-amber-800'],
                            'E'=>['bar'=>'bg-orange-500','badge'=>'bg-orange-100 text-orange-800'],
                            default=>['bar'=>'bg-red-500','badge'=>'bg-red-100 text-red-800'],
                        };
                    @endphp
                    <div class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <p class="font-semibold text-slate-800 text-sm">{{ $subject }}</p>
                                <p class="text-xs text-slate-400">{{ $data['count'] }} {{ Str::plural('assessment', $data['count']) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-slate-700">{{ $data['average'] }}%</span>
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold {{ $sc['badge'] }}">{{ $sg }}</span>
                            </div>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-1.5">
                            <div class="{{ $sc['bar'] }} h-1.5 rounded-full" style="width: {{ min($data['average'],100) }}%"></div>
                        </div>
                    </div>
                    @endforeach

                    @php
                        $overallAvg = round(collect($summaryData)->avg('average'), 1);
                        $og = 'F';
                        foreach (App\Models\Assessment::GRADING_SCALE as $t => $g) { if ($overallAvg >= $t) { $og = $g; break; } }
                    @endphp
                    <div class="bg-slate-800 rounded-lg px-4 py-3 flex items-center justify-between">
                        <p class="text-white font-semibold text-sm">Overall Average</p>
                        <div class="flex items-center gap-2">
                            <span class="text-white font-bold">{{ $overallAvg }}%</span>
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-white text-slate-800 text-sm font-extrabold">{{ $og }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        <x-slot name="footer">
            <div class="flex justify-end">
                <x-button wire:click="$set('showSummaryModal', false)" label="Close" flat />
            </div>
        </x-slot>
        </x-card>
    </x-modal>

</div>
