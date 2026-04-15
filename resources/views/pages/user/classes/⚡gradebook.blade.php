<?php

use App\Models\Assessment;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
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
    // Filters
    // ──────────────────────────────────────────

    public int    $selectedTerm = 1;
    public int    $selectedYear;
    public string $filterSubject = '';
    public string $search        = '';

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
    public function subjects(): array
    {
        return Assessment::where('user_id', Auth::id())
            ->where('class_id', $this->classId)
            ->where('term', $this->selectedTerm)
            ->where('academic_year', $this->selectedYear)
            ->distinct()
            ->orderBy('subject')
            ->pluck('subject')
            ->toArray();
    }

    /**
     * All assessments for the class/term/year, indexed by [student_id][subject].
     */
    #[Computed]
    public function assessmentsByStudent(): array
    {
        $assessments = Assessment::where('user_id', Auth::id())
            ->where('class_id', $this->classId)
            ->where('term', $this->selectedTerm)
            ->where('academic_year', $this->selectedYear)
            ->when($this->filterSubject, fn ($q) => $q->where('subject', $this->filterSubject))
            ->with('student')
            ->get();

        $result = [];

        foreach ($assessments as $a) {
            $result[$a->student_id][$a->subject][] = $a->percentage;
        }

        return $result;
    }

    /**
     * Rows for the grade book table.
     * Each row: student + per-subject average + overall average + atRisk flag.
     */
    #[Computed]
    public function rows(): array
    {
        $rows    = [];
        $byStud  = $this->assessmentsByStudent;
        $subjects = $this->filterSubject ? [$this->filterSubject] : $this->subjects;

        foreach ($this->students as $student) {
            if ($this->search) {
                $name = strtolower($student->first_name . ' ' . $student->last_name);
                if (! str_contains($name, strtolower($this->search))) continue;
            }

            $studentData = $byStud[$student->id] ?? [];
            $subjectCells = [];
            $allPercentages = [];

            foreach ($subjects as $subject) {
                $scores = $studentData[$subject] ?? [];
                if (count($scores) > 0) {
                    $avg  = round(array_sum($scores) / count($scores), 1);
                    $grade = $this->calcGrade($avg);
                    $allPercentages[] = $avg;
                } else {
                    $avg   = null;
                    $grade = null;
                }
                $subjectCells[$subject] = ['avg' => $avg, 'grade' => $grade, 'count' => count($scores)];
            }

            $overallAvg = count($allPercentages) > 0
                ? round(array_sum($allPercentages) / count($allPercentages), 1)
                : null;

            $rows[] = [
                'student'     => $student,
                'cells'       => $subjectCells,
                'overall'     => $overallAvg,
                'overallGrade'=> $overallAvg !== null ? $this->calcGrade($overallAvg) : null,
                'atRisk'      => $overallAvg !== null && $overallAvg < 50,
                'hasData'     => count($allPercentages) > 0,
            ];
        }

        return $rows;
    }

    /**
     * Class averages per subject (bottom summary row).
     */
    #[Computed]
    public function classAverages(): array
    {
        $result   = [];
        $subjects = $this->filterSubject ? [$this->filterSubject] : $this->subjects;

        foreach ($subjects as $subject) {
            $avgs = collect($this->rows)
                ->filter(fn ($r) => $r['cells'][$subject]['avg'] !== null)
                ->map(fn ($r) => $r['cells'][$subject]['avg']);

            $result[$subject] = $avgs->isNotEmpty()
                ? ['avg' => round($avgs->avg(), 1), 'grade' => $this->calcGrade(round($avgs->avg(), 1))]
                : ['avg' => null, 'grade' => null];
        }

        return $result;
    }

    #[Computed]
    public function atRiskCount(): int
    {
        return collect($this->rows)->where('atRisk', true)->count();
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    private function calcGrade(float $pct): string
    {
        return match(true) {
            $pct >= 90 => 'A',
            $pct >= 80 => 'B',
            $pct >= 70 => 'C',
            $pct >= 60 => 'D',
            $pct >= 50 => 'E',
            default    => 'F',
        };
    }
};
?>

<div class="min-h-screen bg-slate-50 font-sans">
<x-notifications />

    {{-- ══════════════════════════════════════════════════════════
         PAGE HEADER
    ══════════════════════════════════════════════════════════ --}}
    <div class="bg-white border-b border-slate-200 px-6 py-5">
        <div class="mx-auto">
            <p class="text-xs text-slate-400 mb-1 uppercase tracking-wide font-medium">
                {{ $this->classroom->name }} &middot; {{ $this->classroom->subject }} &middot; {{ $this->classroom->academic_year }}
            </p>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Grade Book</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        {{ count($this->rows) }} students
                        @if($this->atRiskCount > 0)
                            &middot; <span class="text-red-500 font-medium">{{ $this->atRiskCount }} at risk</span>
                        @endif
                    </p>
                </div>

                {{-- Term / Year filters --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <select wire:model.live="selectedTerm"
                        class="px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="1">Term 1 (Jan–Apr)</option>
                        <option value="2">Term 2 (May–Aug)</option>
                        <option value="3">Term 3 (Sep–Dec)</option>
                    </select>
                    <input
                        wire:model.live="selectedYear"
                        type="number" min="2020" max="2100"
                        class="w-24 px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Year"
                    />
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto px-6 py-8 space-y-6">

        {{-- ══════════════════════════════════════════════════════════
             SUMMARY TILES
        ══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Students</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ count($this->rows) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Subjects</p>
                <p class="text-2xl font-bold text-indigo-600 mt-1">{{ count($this->subjects) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">At Risk (&lt;50%)</p>
                <p class="text-2xl font-bold mt-1 {{ $this->atRiskCount > 0 ? 'text-red-500' : 'text-slate-400' }}">
                    {{ $this->atRiskCount }}
                </p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Class Average</p>
                @php
                    $allOveralls = collect($this->rows)->filter(fn($r) => $r['overall'] !== null)->pluck('overall');
                    $classOverall = $allOveralls->isNotEmpty() ? round($allOveralls->avg(), 1) : null;
                @endphp
                <p class="text-2xl font-bold text-slate-800 mt-1">
                    {{ $classOverall !== null ? $classOverall . '%' : '—' }}
                </p>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             FILTER BAR
        ══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="flex flex-wrap gap-3">
                <div class="flex-1 min-w-40">
                    <x-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search student…"
                        icon="magnifying-glass"
                        shadowless
                    />
                </div>
                <div class="min-w-48">
                    <x-native-select wire:model.live="filterSubject" shadowless>
                        <option value="">All subjects</option>
                        @foreach ($this->subjects as $subj)
                            <option value="{{ $subj }}">{{ $subj }}</option>
                        @endforeach
                    </x-native-select>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             EMPTY STATE
        ══════════════════════════════════════════════════════════ --}}
        @if (empty($this->subjects))
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                <x-icon name="clipboard-document-list" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-500 font-medium">No assessments for Term {{ $selectedTerm }}, {{ $selectedYear }}.</p>
                <p class="text-slate-400 text-sm mt-1">Enter assessment scores to populate the grade book.</p>
            </div>

        {{-- ══════════════════════════════════════════════════════════
             GRADE BOOK TABLE
        ══════════════════════════════════════════════════════════ --}}
        @else
            @php
                $displaySubjects = $filterSubject ? [$filterSubject] : $this->subjects;

                $gradeBg = fn(string $g): string => match($g) {
                    'A'     => 'bg-emerald-100 text-emerald-800',
                    'B'     => 'bg-blue-100 text-blue-800',
                    'C'     => 'bg-amber-100 text-amber-800',
                    'D'     => 'bg-orange-100 text-orange-800',
                    'E'     => 'bg-red-100 text-red-700',
                    default => 'bg-red-200 text-red-900',
                };
            @endphp

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-max">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="px-5 py-3 text-left font-semibold text-slate-500 uppercase tracking-wide text-xs sticky left-0 bg-slate-50 z-10">
                                    Student
                                </th>
                                @foreach ($displaySubjects as $subj)
                                    <th class="px-4 py-3 text-center font-semibold text-slate-500 uppercase tracking-wide text-xs whitespace-nowrap">
                                        {{ $subj }}
                                    </th>
                                @endforeach
                                <th class="px-4 py-3 text-center font-semibold text-slate-500 uppercase tracking-wide text-xs bg-slate-100">
                                    Overall
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->rows as $row)
                                <tr class="hover:bg-slate-50 transition-colors {{ $row['atRisk'] ? 'bg-red-50/40' : '' }}">
                                    <td class="px-5 py-3 sticky left-0 bg-white z-10 {{ $row['atRisk'] ? '!bg-red-50/40' : '' }}">
                                        <div class="flex items-center gap-2">
                                            @if ($row['atRisk'])
                                                <x-icon name="exclamation-triangle" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                            @endif
                                            <div>
                                                <p class="font-semibold text-slate-800 leading-tight">
                                                    {{ $row['student']->full_name }}
                                                </p>
                                                @if ($row['atRisk'])
                                                    <p class="text-xs text-red-500 font-medium">At risk</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    @foreach ($displaySubjects as $subj)
                                        @php $cell = $row['cells'][$subj] ?? ['avg' => null, 'grade' => null, 'count' => 0]; @endphp
                                        <td class="px-4 py-3 text-center">
                                            @if ($cell['avg'] !== null)
                                                <div class="flex flex-col items-center gap-1">
                                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-bold {{ $gradeBg($cell['grade']) }}">
                                                        {{ $cell['grade'] }}
                                                    </span>
                                                    <span class="text-xs text-slate-400">{{ $cell['avg'] }}%</span>
                                                    @if ($cell['count'] > 1)
                                                        <span class="text-[10px] text-slate-300">×{{ $cell['count'] }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-slate-300 text-lg">—</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="px-4 py-3 text-center bg-slate-50/60">
                                        @if ($row['overall'] !== null)
                                            <div class="flex flex-col items-center gap-1">
                                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-sm font-bold {{ $gradeBg($row['overallGrade']) }}">
                                                    {{ $row['overallGrade'] }}
                                                </span>
                                                <span class="text-xs text-slate-500 font-semibold">{{ $row['overall'] }}%</span>
                                            </div>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Class average row --}}
                            <tr class="bg-indigo-50 border-t-2 border-indigo-200 font-semibold">
                                <td class="px-5 py-3 sticky left-0 bg-indigo-50 z-10">
                                    <p class="text-xs font-bold text-indigo-700 uppercase tracking-wide">Class Average</p>
                                </td>
                                @foreach ($displaySubjects as $subj)
                                    @php $ca = $this->classAverages[$subj] ?? ['avg' => null, 'grade' => null]; @endphp
                                    <td class="px-4 py-3 text-center">
                                        @if ($ca['avg'] !== null)
                                            <div class="flex flex-col items-center gap-1">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-bold {{ $gradeBg($ca['grade']) }}">
                                                    {{ $ca['grade'] }}
                                                </span>
                                                <span class="text-xs text-indigo-600 font-semibold">{{ $ca['avg'] }}%</span>
                                            </div>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-center bg-indigo-100/60">
                                    @if ($classOverall !== null)
                                        <div class="flex flex-col items-center gap-1">
                                            @php $cog = match(true) { $classOverall >= 90 => 'A', $classOverall >= 80 => 'B', $classOverall >= 70 => 'C', $classOverall >= 60 => 'D', $classOverall >= 50 => 'E', default => 'F' }; @endphp
                                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-sm font-bold {{ $gradeBg($cog) }}">
                                                {{ $cog }}
                                            </span>
                                            <span class="text-xs text-indigo-600 font-semibold">{{ $classOverall }}%</span>
                                        </div>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Grade legend --}}
                <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span class="font-semibold text-slate-400 uppercase tracking-wide mr-1">Grades:</span>
                    @foreach (['A' => ['≥90%','bg-emerald-100 text-emerald-800'], 'B' => ['≥80%','bg-blue-100 text-blue-800'], 'C' => ['≥70%','bg-amber-100 text-amber-800'], 'D' => ['≥60%','bg-orange-100 text-orange-800'], 'E' => ['≥50%','bg-red-100 text-red-700'], 'F' => ['<50%','bg-red-200 text-red-900']] as $g => [$label, $cls])
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded text-[10px] font-bold {{ $cls }}">{{ $g }}</span>
                            {{ $label }}
                        </span>
                    @endforeach
                    <span class="ml-auto text-slate-400">×N = number of assessments averaged</span>
                </div>
            </div>
        @endif

    </div>
</div>
