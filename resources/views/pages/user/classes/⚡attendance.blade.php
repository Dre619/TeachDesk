<?php

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use WireUi\Traits\WireUiActions;
use Carbon\Carbon;
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

    public bool $showEntryModal  = false;
    public bool $showDetailModal = false;

    // ──────────────────────────────────────────
    // Entry sheet state
    // ──────────────────────────────────────────

    public string $entryDate = '';

    /**
     * [ student_id => ['status' => 'present', 'notes' => ''] ]
     */
    public array $entryRows = [];

    // ──────────────────────────────────────────
    // Detail / register view
    // ──────────────────────────────────────────

    public int    $registerYear;
    public int    $registerMonth;

    // ──────────────────────────────────────────
    // Filters (for the daily log list)
    // ──────────────────────────────────────────

    public string $filterDate   = '';
    public string $filterStatus = '';
    public string $filterSearch = '';

    // ──────────────────────────────────────────
    // View mode
    // ──────────────────────────────────────────

    public string $viewMode = 'register'; // register | log

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function mount(int $classId): void
    {
        // Attendance is a form-teacher responsibility — subject teachers are not permitted.
        $class = ClassRoom::where('user_id', Auth::id())->findOrFail($classId);

        $this->classId        = $classId;
        $this->resolveRole($class);
        $this->entryDate      = today()->toDateString();
        $this->registerYear   = (int) today()->year;
        $this->registerMonth  = (int) today()->month;
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

    /**
     * All attendance rows for the selected month, keyed by student_id then day.
     * [ student_id => [ day => Attendance ] ]
     */
    #[Computed]
    public function registerData(): array
    {
        $records = Attendance::forClass($this->classId)
            ->inMonth($this->registerYear, $this->registerMonth)
            ->get();

        $map = [];
        foreach ($records as $record) {
            $map[$record->student_id][$record->date->day] = $record;
        }

        return $map;
    }

    /**
     * School days (Mon–Fri) in the selected month.
     */
    #[Computed]
    public function schoolDays(): array
    {
        $start  = Carbon::create($this->registerYear, $this->registerMonth, 1);
        $end    = $start->copy()->endOfMonth();
        $days   = [];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->isWeekday()) {
                $days[] = (int) $d->day;
            }
        }

        return $days;
    }

    /**
     * Per-student summary for the register month.
     * [ student_id => ['present'=>n, 'absent'=>n, 'late'=>n, 'total'=>n, 'rate'=>float] ]
     */
    #[Computed]
    public function registerSummary(): array
    {
        $summary = [];

        foreach ($this->students as $student) {
            $rows    = collect($this->registerData[$student->id] ?? []);
            $total   = $rows->count();
            $present = $rows->where('status', 'present')->count();
            $late    = $rows->where('status', 'late')->count();
            $absent  = $rows->where('status', 'absent')->count();
            $rate    = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0.0;

            $summary[$student->id] = compact('present', 'absent', 'late', 'total', 'rate');
        }

        return $summary;
    }

    /**
     * Filtered log of individual attendance records.
     */
    #[Computed]
    public function attendanceLog()
    {
        return Attendance::with('student')
            ->forClass($this->classId)
            ->when($this->filterDate,   fn ($q) => $q->onDate($this->filterDate))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterSearch, fn ($q) =>
                $q->whereHas('student', fn ($q) =>
                    $q->where('first_name', 'like', "%{$this->filterSearch}%")
                      ->orWhere('last_name',  'like', "%{$this->filterSearch}%")
                ))
            ->latest('date')
            ->get();
    }

    /**
     * Unique dates that have attendance records (for the date filter dropdown).
     */
    #[Computed]
    public function logDates(): array
    {
        return Attendance::forClass($this->classId)
            ->selectRaw('DATE(date) as d')
            ->distinct()
            ->orderByDesc('d')
            ->pluck('d')
            ->toArray();
    }

    // ──────────────────────────────────────────
    // Entry modal
    // ──────────────────────────────────────────

    public function openEntryModal(): void
    {
        $this->entryRows = [];

        foreach ($this->students as $student) {
            $this->entryRows[$student->id] = [
                'status' => 'present',
                'notes'  => '',
            ];
        }

        $this->syncExistingAttendance();
        $this->showEntryModal = true;
    }

    /**
     * When the date changes, reload whatever was already saved for that day.
     */
    public function syncExistingAttendance(): void
    {
        if (! $this->entryDate) {
            return;
        }

        $existing = Attendance::forClass($this->classId)
            ->onDate($this->entryDate)
            ->get()
            ->keyBy('student_id');

        foreach ($this->entryRows as $studentId => $_) {
            $record = $existing->get($studentId);

            $this->entryRows[$studentId] = [
                'status' => $record ? $record->status : 'present',
                'notes'  => $record ? ($record->notes ?? '') : '',
            ];
        }
    }

    /**
     * Mark all visible rows with a given status at once.
     */
    public function markAll(string $status): void
    {
        foreach ($this->entryRows as $id => $_) {
            $this->entryRows[$id]['status'] = $status;
        }
    }

    public function saveEntrySheet(): void
    {
        if (! $this->entryDate) {
            $this->notification()->error(title: 'No date', description: 'Please select a date.');
            return;
        }

        $records = [];

        foreach ($this->entryRows as $studentId => $row) {
            $records[] = [
                'student_id' => $studentId,
                'status'     => $row['status'],
                'notes'      => $row['notes'] ?: null,
            ];
        }

        Attendance::bulkUpsert($this->classId, $this->entryDate, $records);

        $this->showEntryModal = false;
        $this->bustCache();

        $absent = collect($records)->where('status', 'absent')->count();
        $late   = collect($records)->where('status', 'late')->count();

        $this->notification()->success(
            title:       'Attendance saved!',
            description: Carbon::parse($this->entryDate)->format('D, d M Y')
                . " · {$absent} absent · {$late} late.",
        );
    }

    // ──────────────────────────────────────────
    // Register month navigation
    // ──────────────────────────────────────────

    public function previousMonth(): void
    {
        $date = Carbon::create($this->registerYear, $this->registerMonth, 1)->subMonth();
        $this->registerYear  = $date->year;
        $this->registerMonth = $date->month;
        $this->bustCache();
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->registerYear, $this->registerMonth, 1)->addMonth();
        $this->registerYear  = $date->year;
        $this->registerMonth = $date->month;
        $this->bustCache();
    }

    public function goToToday(): void
    {
        $this->registerYear  = (int) today()->year;
        $this->registerMonth = (int) today()->month;
        $this->bustCache();
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    private function bustCache(): void
    {
        unset(
            $this->registerData,
            $this->registerSummary,
            $this->schoolDays,
            $this->attendanceLog,
            $this->logDates,
        );
    }
};
?>

{{-- resources/views/livewire/attendance-manager.blade.php --}}
<div class="min-h-screen bg-slate-50 font-sans">

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
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Attendance</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        {{ $this->students->count() }} {{ Str::plural('student', $this->students->count()) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    {{-- View toggle --}}
                    <div class="flex items-center bg-slate-100 rounded-lg p-1 gap-1">
                        <button wire:click="$set('viewMode','register')" @class([
                            'px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                            'bg-white shadow text-slate-800'      => $viewMode === 'register',
                            'text-slate-500 hover:text-slate-700' => $viewMode !== 'register',
                        ])><x-icon name="table-cells" class="w-4 h-4 inline -mt-0.5 mr-1" />Register</button>
                        <button wire:click="$set('viewMode','log')" @class([
                            'px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                            'bg-white shadow text-slate-800'      => $viewMode === 'log',
                            'text-slate-500 hover:text-slate-700' => $viewMode !== 'log',
                        ])><x-icon name="list-bullet" class="w-4 h-4 inline -mt-0.5 mr-1" />Log</button>
                    </div>
                    <a
                        href="{{ route('user.attendance.register', $classId) }}?month={{ $registerMonth }}&year={{ $registerYear }}"
                        target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50 transition-colors"
                    >
                        <x-icon name="printer" class="w-4 h-4" />
                        Print Register
                    </a>
                    <x-button
                        wire:click="openEntryModal"
                        icon="clipboard-document-check"
                        label="Take Attendance"
                        primary
                        class="w-full sm:w-auto"
                    />
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto px-6 py-8 space-y-6">

        {{-- ══════════════════════════════════════════════════════════
             REGISTER VIEW  (monthly grid)
        ══════════════════════════════════════════════════════════ --}}
        @if ($viewMode === 'register')

            {{-- Month navigator --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4 flex items-center justify-between">
                <button wire:click="previousMonth" class="p-2 rounded-lg hover:bg-slate-100 text-slate-600 transition-colors">
                    <x-icon name="chevron-left" class="w-5 h-5" />
                </button>

                <div class="text-center">
                    <p class="text-lg font-bold text-slate-800">
                        {{ Carbon::create($registerYear, $registerMonth)->format('F Y') }}
                    </p>
                    <p class="text-xs text-slate-400 mt-0.5">{{ count($this->schoolDays) }} school days</p>
                </div>

                <div class="flex items-center gap-2">
                    <button wire:click="goToToday" class="text-xs text-indigo-600 font-medium px-3 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors">
                        Today
                    </button>
                    <button wire:click="nextMonth" class="p-2 rounded-lg hover:bg-slate-100 text-slate-600 transition-colors">
                        <x-icon name="chevron-right" class="w-5 h-5" />
                    </button>
                </div>
            </div>

            {{-- Legend --}}
            <div class="flex items-center gap-4 text-xs text-slate-500 px-1">
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded flex items-center justify-center bg-emerald-100 text-emerald-700 font-bold text-xs">P</span> Present</span>
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded flex items-center justify-center bg-red-100 text-red-700 font-bold text-xs">A</span> Absent</span>
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded flex items-center justify-center bg-amber-100 text-amber-700 font-bold text-xs">L</span> Late</span>
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded flex items-center justify-center bg-slate-100 text-slate-400 font-bold text-xs">—</span> Not recorded</span>
            </div>

            @if ($this->students->isEmpty())
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-16 text-center">
                    <x-icon name="user-group" class="w-10 h-10 text-slate-300 mx-auto mb-3" />
                    <p class="text-slate-400 text-sm">No students in this class.</p>
                </div>
            @else
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="text-xs w-full">
                            <thead>
                                <tr class="bg-slate-800 text-white">
                                    {{-- Student col --}}
                                    <th class="sticky left-0 bg-slate-800 z-10 px-4 py-3 text-left font-semibold uppercase tracking-wide min-w-[160px]">
                                        Student
                                    </th>
                                    {{-- Day cols --}}
                                    @foreach ($this->schoolDays as $day)
                                    @php
                                        $isToday = today()->year  == $registerYear
                                                && today()->month == $registerMonth
                                                && today()->day   == $day;
                                    @endphp
                                    <th @class([
                                        'px-1 py-3 text-center font-semibold w-8',
                                        'bg-indigo-700' => $isToday,
                                    ])>
                                        {{ $day }}
                                    </th>
                                    @endforeach
                                    {{-- Summary cols --}}
                                    <th class="px-3 py-3 text-center font-semibold uppercase tracking-wide whitespace-nowrap">P</th>
                                    <th class="px-3 py-3 text-center font-semibold uppercase tracking-wide whitespace-nowrap">A</th>
                                    <th class="px-3 py-3 text-center font-semibold uppercase tracking-wide whitespace-nowrap">L</th>
                                    <th class="px-3 py-3 text-center font-semibold uppercase tracking-wide whitespace-nowrap">Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($this->students as $student)
                                @php
                                    $studentDays = $this->registerData[$student->id] ?? [];
                                    $summary     = $this->registerSummary[$student->id];
                                    $rateColor   = match(true) {
                                        $summary['rate'] >= 90 => 'text-emerald-600',
                                        $summary['rate'] >= 75 => 'text-amber-600',
                                        default                => 'text-red-600',
                                    };
                                @endphp
                                <tr wire:key="reg-{{ $student->id }}" class="hover:bg-slate-50 transition-colors">

                                    {{-- Student name --}}
                                    <td class="sticky left-0 bg-white px-4 py-2.5 z-10">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold shrink-0" style="font-size:10px">
                                                {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                                            </div>
                                            <span class="font-medium text-slate-700 whitespace-nowrap">{{ $student->full_name }}</span>
                                        </div>
                                    </td>

                                    {{-- Day cells --}}
                                    @foreach ($this->schoolDays as $day)
                                    @php
                                        $record   = $studentDays[$day] ?? null;
                                        $code     = $record?->status_code ?? null;
                                        $isToday  = today()->year  == $registerYear
                                                 && today()->month == $registerMonth
                                                 && today()->day   == $day;
                                        $cellBg = match($code) {
                                            'P'     => 'bg-emerald-50',
                                            'A'     => 'bg-red-50',
                                            'L'     => 'bg-amber-50',
                                            default => '',
                                        };
                                        $codeColor = match($code) {
                                            'P' => 'bg-emerald-100 text-emerald-700',
                                            'A' => 'bg-red-100 text-red-700',
                                            'L' => 'bg-amber-100 text-amber-700',
                                            default => 'bg-slate-100 text-slate-400',
                                        };
                                    @endphp
                                    <td @class([
                                        'px-1 py-2.5 text-center',
                                        $cellBg,
                                        'ring-1 ring-inset ring-indigo-300' => $isToday,
                                    ])>
                                        <span @class([
                                            'inline-flex items-center justify-center w-5 h-5 rounded font-bold',
                                            $codeColor,
                                        ]) title="{{ $record?->status_label ?? 'Not recorded' }}{{ $record?->notes ? ' — ' . $record->notes : '' }}">
                                            {{ $code ?? '—' }}
                                        </span>
                                    </td>
                                    @endforeach

                                    {{-- Summary --}}
                                    <td class="px-3 py-2.5 text-center font-semibold text-emerald-700">{{ $summary['present'] }}</td>
                                    <td class="px-3 py-2.5 text-center font-semibold text-red-600">{{ $summary['absent'] }}</td>
                                    <td class="px-3 py-2.5 text-center font-semibold text-amber-600">{{ $summary['late'] }}</td>
                                    <td class="px-3 py-2.5 text-center font-bold {{ $rateColor }}">{{ $summary['rate'] }}%</td>

                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        {{-- ══════════════════════════════════════════════════════════
             LOG VIEW  (filterable daily list)
        ══════════════════════════════════════════════════════════ --}}
        @else

            {{-- Filter bar --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <x-input
                        wire:model.live.debounce.300ms="filterSearch"
                        placeholder="Search student…"
                        icon="magnifying-glass"
                        shadowless
                    />
                    <x-native-select wire:model.live="filterDate" shadowless>
                        <option value="">All dates</option>
                        @foreach ($this->logDates as $d)
                            <option value="{{ $d }}">{{ Carbon::parse($d)->format('D, d M Y') }}</option>
                        @endforeach
                    </x-native-select>
                    <x-native-select wire:model.live="filterStatus" shadowless>
                        <option value="">All statuses</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                    </x-native-select>
                </div>
            </div>

            {{-- Stats strip --}}
            @if ($this->attendanceLog->isNotEmpty())
            @php
                $lPresent = $this->attendanceLog->where('status','present')->count();
                $lAbsent  = $this->attendanceLog->where('status','absent')->count();
                $lLate    = $this->attendanceLog->where('status','late')->count();
                $lTotal   = $this->attendanceLog->count();
                $lRate    = $lTotal > 0 ? round((($lPresent + $lLate) / $lTotal) * 100, 1) : 0;
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Present</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $lPresent }}</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Absent</p>
                    <p class="text-2xl font-bold text-red-500 mt-1">{{ $lAbsent }}</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Late</p>
                    <p class="text-2xl font-bold text-amber-500 mt-1">{{ $lLate }}</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Attendance Rate</p>
                    <p @class(['text-2xl font-bold mt-1',
                        'text-emerald-600' => $lRate >= 90,
                        'text-amber-500'   => $lRate >= 75 && $lRate < 90,
                        'text-red-500'     => $lRate < 75,
                    ])>{{ $lRate }}%</p>
                </div>
            </div>
            @endif

            @if ($this->attendanceLog->isEmpty())
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                    <x-icon name="clipboard-document" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                    <p class="text-slate-500 font-medium">No attendance records found.</p>
                    <x-button wire:click="openEntryModal" label="Take Attendance" primary class="mt-5" />
                </div>
            @else
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-left">
                                <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Student</th>
                                <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Date</th>
                                <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Status</th>
                                <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden sm:table-cell">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->attendanceLog as $record)
                            @php
                                $sc = match($record->status) {
                                    'present' => 'bg-emerald-100 text-emerald-700',
                                    'absent'  => 'bg-red-100 text-red-700',
                                    'late'    => 'bg-amber-100 text-amber-700',
                                    default   => 'bg-slate-100 text-slate-500',
                                };
                            @endphp
                            <tr wire:key="log-{{ $record->id }}" class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                                            {{ strtoupper(substr($record->student->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($record->student->last_name ?? '', 0, 1)) }}
                                        </div>
                                        <span class="font-medium text-slate-800">{{ $record->student->full_name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $record->date->format('D, d M Y') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $sc }}">
                                        {{ $record->status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-slate-400 text-xs hidden sm:table-cell">
                                    {{ $record->notes ?? '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

    </div>{{-- /--}}


    {{-- ══════════════════════════════════════════════════════════
         ATTENDANCE ENTRY MODAL  (spreadsheet style)
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showEntryModal" title="Take Attendance" blur persistent width="3xl">

        <x-card class="relative">
            {{-- Header: date picker + mark-all shortcuts --}}
        <div class="border-b border-slate-200 pb-4 mb-1 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <x-input
                        wire:model.live="entryDate"
                        wire:change="syncExistingAttendance"
                        label="Date"
                        type="date"
                    />
                </div>

                {{-- Mark-all shortcuts --}}
                <div class="flex items-center gap-2 pb-0.5">
                    <span class="text-xs text-slate-500 font-medium whitespace-nowrap">Mark all:</span>
                    <button
                        wire:click="markAll('present')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-colors"
                    >
                        All Present
                    </button>
                    <button
                        wire:click="markAll('absent')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-100 text-red-700 hover:bg-red-200 transition-colors"
                    >
                        All Absent
                    </button>
                    <button
                        wire:click="markAll('late')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                    >
                        All Late
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs text-slate-500">
                <x-icon name="academic-cap" class="w-4 h-4 text-indigo-400" />
                {{ $this->classroom->name }} &middot; {{ $this->classroom->subject }}
                &middot; <span class="font-medium text-slate-700">{{ $this->students->count() }} students</span>
            </div>
        </div>

        {{-- Student rows --}}
        <div class="overflow-y-auto" style="max-height: 55vh;">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-slate-100 border-b border-slate-200">
                        <th class="px-4 py-2.5 text-left font-semibold text-slate-600 text-xs uppercase tracking-wide w-8">#</th>
                        <th class="px-4 py-2.5 text-left font-semibold text-slate-600 text-xs uppercase tracking-wide">Student</th>
                        <th class="px-4 py-2.5 text-center font-semibold text-slate-600 text-xs uppercase tracking-wide w-52">Status</th>
                        <th class="px-4 py-2.5 text-left font-semibold text-slate-600 text-xs uppercase tracking-wide">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->students as $i => $student)
                    @php
                        $row    = $entryRows[$student->id] ?? ['status' => 'present', 'notes' => ''];
                        $status = $row['status'];
                        $rowBg  = match($status) {
                            'present' => 'bg-emerald-50/60',
                            'absent'  => 'bg-red-50/60',
                            'late'    => 'bg-amber-50/60',
                            default   => 'bg-white',
                        };
                    @endphp
                    <tr wire:key="entry-{{ $student->id }}" class="{{ $rowBg }} transition-colors">

                        <td class="px-4 py-2.5 text-slate-400 text-xs">{{ $i + 1 }}</td>

                        {{-- Student --}}
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div @class([
                                    'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                    'bg-emerald-100 text-emerald-700' => $status === 'present',
                                    'bg-red-100 text-red-700'         => $status === 'absent',
                                    'bg-amber-100 text-amber-700'     => $status === 'late',
                                ])>
                                    {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                                </div>
                                <span class="font-medium text-slate-800">{{ $student->full_name }}</span>
                            </div>
                        </td>

                        {{-- Status toggle buttons --}}
                        <td class="px-4 py-2.5">
                            <div class="flex items-center justify-center gap-1.5">
                                <button
                                    wire:click="$set('entryRows.{{ $student->id }}.status', 'present')"
                                    @class([
                                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors border',
                                        'bg-emerald-500 text-white border-emerald-500 shadow-sm' => $status === 'present',
                                        'bg-white text-emerald-600 border-emerald-200 hover:bg-emerald-50' => $status !== 'present',
                                    ])
                                >P</button>
                                <button
                                    wire:click="$set('entryRows.{{ $student->id }}.status', 'absent')"
                                    @class([
                                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors border',
                                        'bg-red-500 text-white border-red-500 shadow-sm' => $status === 'absent',
                                        'bg-white text-red-600 border-red-200 hover:bg-red-50' => $status !== 'absent',
                                    ])
                                >A</button>
                                <button
                                    wire:click="$set('entryRows.{{ $student->id }}.status', 'late')"
                                    @class([
                                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors border',
                                        'bg-amber-500 text-white border-amber-500 shadow-sm' => $status === 'late',
                                        'bg-white text-amber-600 border-amber-200 hover:bg-amber-50' => $status !== 'late',
                                    ])
                                >L</button>
                            </div>
                        </td>

                        {{-- Notes --}}
                        <td class="px-4 py-2.5">
                            <input
                                wire:model.lazy="entryRows.{{ $student->id }}.notes"
                                type="text"
                                placeholder="{{ $status === 'absent' ? 'Reason for absence…' : 'Optional…' }}"
                                class="w-full rounded-md border border-slate-300 text-xs py-1.5 px-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Live tally --}}
        @php
            $tPresent = collect($entryRows)->where('status', 'present')->count();
            $tAbsent  = collect($entryRows)->where('status', 'absent')->count();
            $tLate    = collect($entryRows)->where('status', 'late')->count();
        @endphp
        <div class="border-t border-slate-200 pt-3 mt-1 flex items-center gap-4 text-xs">
            <span class="flex items-center gap-1.5 font-semibold text-emerald-600">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> {{ $tPresent }} Present
            </span>
            <span class="flex items-center gap-1.5 font-semibold text-red-500">
                <span class="w-2 h-2 rounded-full bg-red-500"></span> {{ $tAbsent }} Absent
            </span>
            <span class="flex items-center gap-1.5 font-semibold text-amber-500">
                <span class="w-2 h-2 rounded-full bg-amber-400"></span> {{ $tLate }} Late
            </span>
        </div>

        <x-slot name="footer">
            <div class="flex items-center justify-between w-full">
                <p class="text-xs text-slate-400">
                    Existing records for this date will be overwritten.
                </p>
                <div class="flex gap-3">
                    <x-button wire:click="$set('showEntryModal', false)" label="Cancel" flat />
                    <x-button
                        wire:click="saveEntrySheet"
                        wire:loading.attr="disabled"
                        wire:target="saveEntrySheet"
                        icon="check"
                        label="Save Attendance"
                        primary
                        spinner="saveEntrySheet"
                    />
                </div>
            </div>
        </x-slot>
        </x-card>
    </x-modal>

</div>
