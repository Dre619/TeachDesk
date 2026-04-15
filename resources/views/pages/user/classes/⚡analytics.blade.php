<?php

use App\Models\Assessment;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $classId;
    public int $selectedTerm = 1;
    public int $selectedYear;

    public function mount(int $classId): void
    {
        $classroom = ClassRoom::forTeacher(Auth::id())->findOrFail($classId);
        $this->classId      = $classId;
        $this->selectedYear = (int) date('Y');
    }

    #[Computed]
    public function classroom(): ClassRoom
    {
        return ClassRoom::forTeacher(Auth::id())->findOrFail($this->classId);
    }

    #[Computed]
    public function assessments()
    {
        return Assessment::forClass($this->classId)
            ->forTerm($this->selectedTerm, $this->selectedYear)
            ->with('student')
            ->get();
    }

    #[Computed]
    public function subjectStats(): array
    {
        return $this->assessments
            ->groupBy('subject')
            ->map(function ($rows, $subject) {
                $avg   = round($rows->avg('percentage'), 1);
                $grade = (new Assessment(['score' => $avg, 'max_score' => 100]))->calculateGrade();

                $gradeDist = collect(array_keys(Assessment::GRADING_SCALE))
                    ->mapWithKeys(fn ($threshold, $i) => [
                        Assessment::GRADING_SCALE[$threshold] => $rows->filter(fn ($r) =>
                            $r->grade === Assessment::GRADING_SCALE[$threshold]
                        )->count(),
                    ]);

                return [
                    'subject'   => $subject,
                    'avg'       => $avg,
                    'grade'     => $grade,
                    'count'     => $rows->count(),
                    'students'  => $rows->pluck('student_id')->unique()->count(),
                    'highest'   => round($rows->max('percentage'), 1),
                    'lowest'    => round($rows->min('percentage'), 1),
                    'gradeDist' => $gradeDist->toArray(),
                ];
            })
            ->sortByDesc('avg')
            ->values()
            ->toArray();
    }

    #[Computed]
    public function studentRankings(): array
    {
        return $this->assessments
            ->groupBy('student_id')
            ->map(function ($rows) {
                $student = $rows->first()->student;
                $avg     = round($rows->avg('percentage'), 1);
                $grade   = (new Assessment(['score' => $avg, 'max_score' => 100]))->calculateGrade();

                return [
                    'student' => $student,
                    'avg'     => $avg,
                    'grade'   => $grade,
                    'count'   => $rows->count(),
                ];
            })
            ->sortByDesc('avg')
            ->values()
            ->toArray();
    }

    #[Computed]
    public function gradeDistribution(): array
    {
        $total = $this->assessments->count();

        return collect(Assessment::GRADING_SCALE)
            ->mapWithKeys(fn ($grade) => [
                $grade => [
                    'count' => $this->assessments->where('grade', $grade)->count(),
                    'pct'   => $total > 0
                        ? round($this->assessments->where('grade', $grade)->count() / $total * 100, 1)
                        : 0,
                ],
            ])
            ->toArray();
    }

    #[Computed]
    public function classSummary(): array
    {
        $assessments = $this->assessments;

        if ($assessments->isEmpty()) {
            return [];
        }

        return [
            'avg'       => round($assessments->avg('percentage'), 1),
            'highest'   => round($assessments->max('percentage'), 1),
            'lowest'    => round($assessments->min('percentage'), 1),
            'total'     => $assessments->count(),
            'students'  => $assessments->pluck('student_id')->unique()->count(),
            'subjects'  => $assessments->pluck('subject')->unique()->count(),
        ];
    }
};
?>

<div class="min-h-screen bg-slate-50 font-sans">

    {{-- ══ HEADER ══════════════════════════════════════════════════ --}}
    <div class="bg-white border-b border-slate-200 px-6 py-5">
        <p class="text-xs text-slate-400 mb-1 uppercase tracking-wide font-medium">
            {{ $this->classroom->name }} &middot; {{ $this->classroom->subject }} &middot; {{ $this->classroom->academic_year }}
        </p>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Analytics</h1>
                <p class="text-sm text-slate-500 mt-0.5">Performance overview for your class</p>
            </div>
            <div class="flex items-center gap-3">
                <x-native-select wire:model.live="selectedTerm" label="">
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </x-native-select>
                <x-input wire:model.live.debounce.400ms="selectedYear" type="number" class="w-24" />
            </div>
        </div>
    </div>

    <div class="px-6 py-8 space-y-8">

        @if (empty($this->classSummary))
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                <x-icon name="chart-bar" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-500 font-medium">No assessments found for Term {{ $selectedTerm }}, {{ $selectedYear }}.</p>
                <p class="text-slate-400 text-sm mt-1">Add some assessments first to see analytics.</p>
            </div>
        @else
            @php $summary = $this->classSummary; @endphp

            {{-- ══ SUMMARY STRIP ══════════════════════════════════════ --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                @php
                    $tiles = [
                        ['label' => 'Class Average', 'value' => $summary['avg'] . '%', 'color' => 'text-indigo-600'],
                        ['label' => 'Highest Score', 'value' => $summary['highest'] . '%', 'color' => 'text-emerald-600'],
                        ['label' => 'Lowest Score',  'value' => $summary['lowest'] . '%',  'color' => 'text-red-500'],
                        ['label' => 'Assessments',   'value' => $summary['total'],          'color' => 'text-slate-700'],
                        ['label' => 'Students',       'value' => $summary['students'],       'color' => 'text-slate-700'],
                        ['label' => 'Subjects',       'value' => $summary['subjects'],       'color' => 'text-slate-700'],
                    ];
                @endphp
                @foreach ($tiles as $tile)
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                        <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">{{ $tile['label'] }}</p>
                        <p class="text-2xl font-bold mt-1 {{ $tile['color'] }}">{{ $tile['value'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- ══ GRADE DISTRIBUTION ══════════════════════════════════ --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-base font-semibold text-slate-800 mb-5">Overall Grade Distribution</h2>
                @php
                    $gradeColors = [
                        'A' => ['bar' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-700'],
                        'B' => ['bar' => 'bg-teal-500',    'badge' => 'bg-teal-100 text-teal-700'],
                        'C' => ['bar' => 'bg-blue-500',    'badge' => 'bg-blue-100 text-blue-700'],
                        'D' => ['bar' => 'bg-amber-500',   'badge' => 'bg-amber-100 text-amber-700'],
                        'E' => ['bar' => 'bg-orange-500',  'badge' => 'bg-orange-100 text-orange-700'],
                        'F' => ['bar' => 'bg-red-500',     'badge' => 'bg-red-100 text-red-700'],
                    ];
                @endphp
                <div class="space-y-3">
                    @foreach ($this->gradeDistribution as $grade => $data)
                        <div class="flex items-center gap-3">
                            <span class="w-6 text-center text-xs font-bold {{ $gradeColors[$grade]['badge'] }} rounded-full px-1.5 py-0.5">{{ $grade }}</span>
                            <div class="flex-1 bg-slate-100 rounded-full h-5 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all {{ $gradeColors[$grade]['bar'] }}"
                                    style="width: {{ $data['pct'] }}%"
                                ></div>
                            </div>
                            <span class="w-10 text-right text-xs font-semibold text-slate-600">{{ $data['count'] }}</span>
                            <span class="w-10 text-right text-xs text-slate-400">{{ $data['pct'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ══ SUBJECT BREAKDOWN ═══════════════════════════════════ --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-base font-semibold text-slate-800">Subject Performance</h2>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-left">
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs text-center">Grade</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Average</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden md:table-cell">Range</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden lg:table-cell text-center">Entries</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden lg:table-cell">Grade breakdown</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->subjectStats as $stat)
                            @php
                                $gc = $gradeColors[$stat['grade']] ?? ['bar' => 'bg-slate-400', 'badge' => 'bg-slate-100 text-slate-600'];
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-3 font-semibold text-slate-800">{{ $stat['subject'] }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold {{ $gc['badge'] }}">
                                        {{ $stat['grade'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-28 bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div class="h-full rounded-full {{ $gc['bar'] }}" style="width: {{ $stat['avg'] }}%"></div>
                                        </div>
                                        <span class="text-sm font-semibold text-slate-700">{{ $stat['avg'] }}%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-500 hidden md:table-cell">
                                    {{ $stat['highest'] }}% – {{ $stat['lowest'] }}%
                                </td>
                                <td class="px-5 py-3 text-center text-xs text-slate-500 hidden lg:table-cell">
                                    {{ $stat['count'] }}
                                </td>
                                <td class="px-5 py-3 hidden lg:table-cell">
                                    <div class="flex items-center gap-1">
                                        @foreach ($stat['gradeDist'] as $g => $cnt)
                                            @if ($cnt > 0)
                                                <span class="inline-flex items-center gap-0.5 text-xs px-1.5 py-0.5 rounded {{ $gradeColors[$g]['badge'] }}">
                                                    {{ $g }}<span class="font-bold">×{{ $cnt }}</span>
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- ══ STUDENT RANKINGS ════════════════════════════════════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Top performers --}}
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2">
                        <x-icon name="trophy" class="w-4 h-4 text-amber-500" />
                        <h2 class="text-base font-semibold text-slate-800">Top Performers</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach (array_slice($this->studentRankings, 0, 5) as $i => $row)
                            @php $gc = $gradeColors[$row['grade']] ?? ['badge' => 'bg-slate-100 text-slate-600']; @endphp
                            <div class="flex items-center gap-4 px-5 py-3">
                                <span class="text-sm font-bold text-slate-400 w-5 text-center">{{ $i + 1 }}</span>
                                <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($row['student']->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($row['student']->last_name ?? '', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-slate-800 text-sm truncate">{{ $row['student']->full_name ?? 'Unknown' }}</p>
                                    <p class="text-xs text-slate-400">{{ $row['count'] }} assessments</p>
                                </div>
                                <span class="text-sm font-bold text-slate-700">{{ $row['avg'] }}%</span>
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold {{ $gc['badge'] }}">
                                    {{ $row['grade'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Needs attention --}}
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2">
                        <x-icon name="exclamation-triangle" class="w-4 h-4 text-orange-500" />
                        <h2 class="text-base font-semibold text-slate-800">Needs Attention</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach (array_slice(array_reverse($this->studentRankings), 0, 5) as $i => $row)
                            @php $gc = $gradeColors[$row['grade']] ?? ['badge' => 'bg-slate-100 text-slate-600']; @endphp
                            <div class="flex items-center gap-4 px-5 py-3">
                                <div class="w-8 h-8 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($row['student']->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($row['student']->last_name ?? '', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-slate-800 text-sm truncate">{{ $row['student']->full_name ?? 'Unknown' }}</p>
                                    <p class="text-xs text-slate-400">{{ $row['count'] }} assessments</p>
                                </div>
                                <span class="text-sm font-bold text-slate-700">{{ $row['avg'] }}%</span>
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold {{ $gc['badge'] }}">
                                    {{ $row['grade'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>

        @endif
    </div>

</div>
