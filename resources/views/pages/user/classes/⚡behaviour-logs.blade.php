<?php

use App\Models\BehaviourLog;
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

    public bool $showFormModal    = false;
    public bool $showDeleteModal  = false;
    public bool $showDetailModal  = false;
    public bool $showStudentModal = false; // per-student timeline

    // ──────────────────────────────────────────
    // Form state
    // ──────────────────────────────────────────

    public ?int $editingId = null;

    #[Rule('required|exists:students,id')]
    public string $student_id = '';

    #[Rule('required|in:positive,negative')]
    public string $type = 'positive';

    #[Rule('required|string|max:100')]
    public string $category = '';

    #[Rule('required|string|max:1000')]
    public string $description = '';

    #[Rule('nullable|string|max:500')]
    public string $action_taken = '';

    #[Rule('required|date')]
    public string $date = '';

    // ──────────────────────────────────────────
    // Delete / detail state
    // ──────────────────────────────────────────

    public ?int          $deletingId = null;
    public ?BehaviourLog $viewing    = null;

    // ──────────────────────────────────────────
    // Per-student timeline state
    // ──────────────────────────────────────────

    public ?int    $timelineStudentId   = null;
    public ?string $timelineStudentName = null;
    public array   $timelineLogs        = [];

    // ──────────────────────────────────────────
    // Filters
    // ──────────────────────────────────────────

    public string $search           = '';
    public string $filterStudent    = '';
    public string $filterType       = '';
    public string $filterCategory   = '';
    public string $filterDateFrom   = '';
    public string $filterDateTo     = '';

    // ──────────────────────────────────────────
    // View mode
    // ──────────────────────────────────────────

    public string $viewMode = 'log'; // log | overview

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function mount(int $classId): void
    {
        $class = ClassRoom::forTeacher(Auth::id())->findOrFail($classId);
        $this->classId = $classId;
        $this->resolveRole($class);
        $this->date    = today()->toDateString();
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
    public function logs()
    {
        return BehaviourLog::with('student')
            ->forTeacher(Auth::id())
            ->whereHas('student', fn ($q) => $q->where('class_id', $this->classId))
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('description',  'like', "%{$this->search}%")
                      ->orWhere('category',   'like', "%{$this->search}%")
                      ->orWhere('action_taken','like', "%{$this->search}%")
                      ->orWhereHas('student', fn ($q) =>
                          $q->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name',  'like', "%{$this->search}%")
                      )
                ))
            ->when($this->filterStudent   !== '', fn ($q) => $q->forStudent((int) $this->filterStudent))
            ->when($this->filterType      !== '', fn ($q) => $q->where('type', $this->filterType))
            ->when($this->filterCategory  !== '', fn ($q) => $q->where('category', $this->filterCategory))
            ->when($this->filterDateFrom  !== '', fn ($q) => $q->whereDate('date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo    !== '', fn ($q) => $q->whereDate('date', '<=', $this->filterDateTo))
            ->recent()
            ->get();
    }

    #[Computed]
    public function categories(): array
    {
        return BehaviourLog::forTeacher(Auth::id())
            ->whereHas('student', fn ($q) => $q->where('class_id', $this->classId))
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();
    }

    /**
     * Per-student summary for overview: positive count, negative count, last log date.
     */
    #[Computed]
    public function overview(): array
    {
        $all = BehaviourLog::forTeacher(Auth::id())
            ->whereHas('student', fn ($q) => $q->where('class_id', $this->classId))
            ->recent()
            ->get();

        $result = [];

        foreach ($this->students as $student) {
            $studentLogs = $all->where('student_id', $student->id);
            $result[$student->id] = [
                'student'  => $student,
                'positive' => $studentLogs->where('type', 'positive')->count(),
                'negative' => $studentLogs->where('type', 'negative')->count(),
                'total'    => $studentLogs->count(),
                'last'     => $studentLogs->first()?->date?->format('d M Y') ?? null,
                'lastType' => $studentLogs->first()?->type ?? null,
            ];
        }

        return $result;
    }

    #[Computed]
    public function stats(): array
    {
        $all      = $this->logs;
        $positive = $all->where('type', 'positive')->count();
        $negative = $all->where('type', 'negative')->count();
        $total    = $all->count();
        $students = $all->pluck('student_id')->unique()->count();

        return compact('positive', 'negative', 'total', 'students');
    }

    // ──────────────────────────────────────────
    // Create
    // ──────────────────────────────────────────

    public function openCreateModal(?int $studentId = null): void
    {
        $this->resetForm();
        if ($studentId) {
            $this->student_id = (string) $studentId;
        }
        $this->showFormModal = true;
    }

    // ──────────────────────────────────────────
    // Edit
    // ──────────────────────────────────────────

    public function openEditModal(int $id): void
    {
        $log = $this->findOwned($id);

        $this->editingId    = $log->id;
        $this->student_id   = (string) $log->student_id;
        $this->type         = $log->type;
        $this->category     = $log->category;
        $this->description  = $log->description;
        $this->action_taken = $log->action_taken ?? '';
        $this->date         = $log->date->toDateString();

        $this->showFormModal = true;
    }

    // ──────────────────────────────────────────
    // Save
    // ──────────────────────────────────────────

    public function save(): void
    {
        $this->validate();

        $data = [
            'student_id'   => (int) $this->student_id,
            'type'         => $this->type,
            'category'     => $this->category,
            'description'  => $this->description,
            'action_taken' => $this->action_taken ?: null,
            'date'         => $this->date,
        ];

        if ($this->editingId) {
            $this->findOwned($this->editingId)->update($data);
            $message = 'Behaviour log updated.';
        } else {
            BehaviourLog::create(array_merge($data, ['user_id' => Auth::id()]));
            $message = 'Behaviour log recorded.';
        }

        $this->showFormModal = false;
        $this->resetForm();
        $this->bustCache();

        $type = $data['type'] === 'positive' ? 'success' : 'warning';
        $this->notification()->$type(title: 'Saved!', description: $message);
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
            $this->notification()->warning(title: 'Deleted', description: 'Behaviour log removed.');
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
    // Per-student timeline
    // ──────────────────────────────────────────

    public function openStudentTimeline(int $studentId): void
    {
        $student = $this->students->find($studentId);
        if (! $student) return;

        $this->timelineStudentId   = $studentId;
        $this->timelineStudentName = $student->full_name;
        $this->timelineLogs        = BehaviourLog::forTeacher(Auth::id())
            ->forStudent($studentId)
            ->recent()
            ->get()
            ->toArray();

        $this->showStudentModal = true;
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    private function findOwned(int $id): BehaviourLog
    {
        return BehaviourLog::forTeacher(Auth::id())->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId    = null;
        $this->student_id   = '';
        $this->type         = 'positive';
        $this->category     = '';
        $this->description  = '';
        $this->action_taken = '';
        $this->date         = today()->toDateString();
        $this->resetValidation();
    }

    private function bustCache(): void
    {
        unset($this->logs, $this->overview, $this->stats, $this->categories);
    }
};
?>

{{-- resources/views/livewire/behaviour-log-manager.blade.php --}}
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
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Behaviour Log</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        {{ $this->stats['total'] }} {{ Str::plural('entry', $this->stats['total']) }} &middot;
                        <span class="text-emerald-600 font-medium">{{ $this->stats['positive'] }} positive</span> &middot;
                        <span class="text-red-500 font-medium">{{ $this->stats['negative'] }} negative</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    {{-- View toggle --}}
                    <div class="flex items-center bg-slate-100 rounded-lg p-1 gap-1">
                        <button wire:click="$set('viewMode','log')" @class([
                            'px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                            'bg-white shadow text-slate-800'      => $viewMode === 'log',
                            'text-slate-500 hover:text-slate-700' => $viewMode !== 'log',
                        ])><x-icon name="list-bullet" class="w-4 h-4 inline -mt-0.5 mr-1" />Log</button>
                        <button wire:click="$set('viewMode','overview')" @class([
                            'px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                            'bg-white shadow text-slate-800'      => $viewMode === 'overview',
                            'text-slate-500 hover:text-slate-700' => $viewMode !== 'overview',
                        ])><x-icon name="chart-bar" class="w-4 h-4 inline -mt-0.5 mr-1" />Overview</button>
                    </div>
                    <a
                        href="{{ route('user.behaviour-logs.print', $classId) }}"
                        target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50 hover:border-indigo-400 transition-colors"
                    >
                        <x-icon name="printer" class="w-4 h-4" />
                        Print Log
                    </a>
                    <x-button
                        wire:click="openCreateModal"
                        icon="plus"
                        label="Add Log"
                        primary
                        class="w-full sm:w-auto"
                    />
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto px-6 py-8 space-y-6">

        {{-- ══════════════════════════════════════════════════════════
             STATS STRIP
        ══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Total Logs</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ $this->stats['total'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-emerald-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Positive</p>
                <div class="flex items-baseline gap-2 mt-1">
                    <p class="text-2xl font-bold text-emerald-600">{{ $this->stats['positive'] }}</p>
                    <span class="text-lg">✅</span>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-red-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Negative</p>
                <div class="flex items-baseline gap-2 mt-1">
                    <p class="text-2xl font-bold text-red-500">{{ $this->stats['negative'] }}</p>
                    <span class="text-lg">⚠️</span>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Students Involved</p>
                <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $this->stats['students'] }}</p>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             LOG VIEW
        ══════════════════════════════════════════════════════════ --}}
        @if ($viewMode === 'log')

            {{-- Filter bar --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div class="col-span-2 sm:col-span-1">
                        <x-input wire:model.live.debounce.300ms="search" placeholder="Search…" icon="magnifying-glass" shadowless />
                    </div>
                    <x-native-select wire:model.live="filterStudent" shadowless>
                        <option value="">All students</option>
                        @foreach ($this->students as $s)
                            <option value="{{ $s->id }}">{{ $s->full_name }}</option>
                        @endforeach
                    </x-native-select>
                    <x-native-select wire:model.live="filterType" shadowless>
                        <option value="">All types</option>
                        <option value="positive">✅ Positive</option>
                        <option value="negative">⚠️ Negative</option>
                    </x-native-select>
                    <x-native-select wire:model.live="filterCategory" shadowless>
                        <option value="">All categories</option>
                        @foreach ($this->categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </x-native-select>
                    <div class="flex gap-2">
                        <x-input wire:model.live="filterDateFrom" type="date" shadowless placeholder="From" class="flex-1" />
                        <x-input wire:model.live="filterDateTo"   type="date" shadowless placeholder="To"   class="flex-1" />
                    </div>
                </div>
            </div>

            @if ($this->logs->isEmpty())
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                    <x-icon name="clipboard-document-list" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                    <p class="text-slate-500 font-medium">No behaviour logs found.</p>
                    <p class="text-slate-400 text-sm mt-1">Start tracking student behaviour.</p>
                    <x-button wire:click="openCreateModal" label="Add Log" primary class="mt-5" />
                </div>
            @else
                {{-- Timeline log --}}
                <div class="space-y-3">
                    @foreach ($this->logs as $log)
                    @php
                        $isPos = $log->type === 'positive';
                        $borderColor = $isPos ? 'border-l-emerald-400' : 'border-l-red-400';
                        $dotColor    = $isPos ? 'bg-emerald-400' : 'bg-red-400';
                        $badgeColor  = $isPos
                            ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                            : 'bg-red-100 text-red-700 border-red-200';
                        $catColor = $isPos
                            ? 'bg-emerald-50 text-emerald-600'
                            : 'bg-red-50 text-red-600';
                    @endphp
                    <div
                        wire:key="log-{{ $log->id }}"
                        class="bg-white rounded-xl border border-l-4 border-slate-200 {{ $borderColor }} shadow-sm px-5 py-4 flex gap-4 hover:shadow-md transition-shadow"
                    >
                        {{-- Dot + icon --}}
                        <div class="shrink-0 flex flex-col items-center pt-1">
                            <div class="w-8 h-8 rounded-full {{ $dotColor }} text-white flex items-center justify-center text-sm font-bold shadow-sm">
                                {{ $isPos ? '✅' : '⚠️' }}
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2 flex-wrap">
                                <div class="flex items-center gap-2 flex-wrap">
                                    {{-- Student --}}
                                    <span class="font-semibold text-slate-800 text-sm">
                                        {{ $log->student->full_name ?? '—' }}
                                    </span>
                                    {{-- Type badge --}}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $badgeColor }}">
                                        {{ ucfirst($log->type) }}
                                    </span>
                                    {{-- Category --}}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $catColor }}">
                                        {{ $log->category }}
                                    </span>
                                </div>
                                {{-- Date --}}
                                <span class="text-xs text-slate-400 shrink-0">{{ $log->date->format('D, d M Y') }}</span>
                            </div>

                            {{-- Description --}}
                            <p class="text-sm text-slate-700 mt-1.5 leading-relaxed">{{ $log->description }}</p>

                            {{-- Action taken --}}
                            @if ($log->action_taken)
                                <div class="mt-2 flex items-start gap-1.5">
                                    <x-icon name="arrow-right-circle" class="w-3.5 h-3.5 text-slate-400 mt-0.5 shrink-0" />
                                    <p class="text-xs text-slate-500 italic">{{ $log->action_taken }}</p>
                                </div>
                            @endif

                            {{-- Actions --}}
                            <div class="flex items-center gap-1 mt-3">
                                <x-button wire:click="openDetailModal({{ $log->id }})" icon="eye"    flat xs class="text-slate-400" />
                                <x-button wire:click="openEditModal({{ $log->id }})"   icon="pencil" flat xs class="text-indigo-600" />
                                <x-button wire:click="confirmDelete({{ $log->id }})"  icon="trash"  flat xs class="text-red-400" />
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

        {{-- ══════════════════════════════════════════════════════════
             OVERVIEW (per-student summary)
        ══════════════════════════════════════════════════════════ --}}
        @else
            @if (empty($this->overview))
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-16 text-center">
                    <p class="text-slate-400 text-sm">No students in this class.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($this->overview as $row)
                    @php
                        $pos      = $row['positive'];
                        $neg      = $row['negative'];
                        $total    = $row['total'];
                        $posRatio = $total > 0 ? round(($pos / $total) * 100) : 0;
                        $sentiment = match(true) {
                            $total === 0        => ['text-slate-400',   '—',   'bg-slate-100'],
                            $posRatio >= 70     => ['text-emerald-600', '😊',  'bg-emerald-50'],
                            $posRatio >= 40     => ['text-amber-500',   '😐',  'bg-amber-50'],
                            default             => ['text-red-500',     '😟',  'bg-red-50'],
                        };
                    @endphp
                    <div
                        wire:key="ov-{{ $row['student']->id }}"
                        class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition-shadow"
                    >
                        {{-- Student header --}}
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($row['student']->first_name, 0, 1)) }}{{ strtoupper(substr($row['student']->last_name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800 text-sm leading-tight">{{ $row['student']->full_name }}</p>
                                    @if ($row['last'])
                                        <p class="text-xs text-slate-400">Last: {{ $row['last'] }}</p>
                                    @else
                                        <p class="text-xs text-slate-300 italic">No logs yet</p>
                                    @endif
                                </div>
                            </div>
                            <div class="{{ $sentiment[2] }} w-10 h-10 rounded-full flex items-center justify-center text-xl">
                                {{ $sentiment[1] }}
                            </div>
                        </div>

                        {{-- Pos / Neg counts --}}
                        <div class="grid grid-cols-3 gap-2 mb-3">
                            <div class="text-center">
                                <p class="text-lg font-bold text-emerald-600">{{ $pos }}</p>
                                <p class="text-xs text-slate-400">Positive</p>
                            </div>
                            <div class="text-center">
                                <p class="text-lg font-bold text-red-500">{{ $neg }}</p>
                                <p class="text-xs text-slate-400">Negative</p>
                            </div>
                            <div class="text-center">
                                <p class="text-lg font-bold text-slate-600">{{ $total }}</p>
                                <p class="text-xs text-slate-400">Total</p>
                            </div>
                        </div>

                        {{-- Positive ratio bar --}}
                        @if ($total > 0)
                        <div class="mb-4">
                            <div class="flex justify-between text-xs text-slate-400 mb-1">
                                <span>Positive ratio</span>
                                <span class="{{ $sentiment[0] }} font-semibold">{{ $posRatio }}%</span>
                            </div>
                            <div class="w-full bg-red-100 rounded-full h-2 overflow-hidden">
                                <div class="bg-emerald-400 h-2 rounded-full transition-all" style="width: {{ $posRatio }}%"></div>
                            </div>
                        </div>
                        @else
                            <div class="mb-4 text-center text-xs text-slate-300 italic py-2">No logs recorded</div>
                        @endif

                        {{-- Actions --}}
                        <div class="flex gap-2">
                            <x-button
                                wire:click="openStudentTimeline({{ $row['student']->id }})"
                                icon="clock"
                                label="Timeline"
                                flat xs
                                class="text-indigo-600 flex-1 justify-center"
                            />
                            <x-button
                                wire:click="openCreateModal({{ $row['student']->id }})"
                                icon="plus"
                                label="Add Log"
                                flat xs
                                class="text-slate-500 flex-1 justify-center"
                            />
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        @endif

    </div>{{-- /--}}


    {{-- ══════════════════════════════════════════════════════════
         CREATE / EDIT MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal
        wire:model.live="showFormModal"
        :title="$editingId ? 'Edit Behaviour Log' : 'Add Behaviour Log'"
        blur persistent width="2xl"
    >
       <x-card class="relative">
         <div class="space-y-4 p-1">

            {{-- Type toggle --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Type</label>
                <div class="flex gap-3">
                    <button
                        wire:click="$set('type','positive')"
                        @class([
                            'flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all',
                            'border-emerald-400 bg-emerald-50 text-emerald-700 shadow-sm' => $type === 'positive',
                            'border-slate-200 bg-white text-slate-400 hover:border-slate-300' => $type !== 'positive',
                        ])
                    >
                        <span>✅</span> Positive
                    </button>
                    <button
                        wire:click="$set('type','negative')"
                        @class([
                            'flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all',
                            'border-red-400 bg-red-50 text-red-700 shadow-sm' => $type === 'negative',
                            'border-slate-200 bg-white text-slate-400 hover:border-slate-300' => $type !== 'negative',
                        ])
                    >
                        <span>⚠️</span> Negative
                    </button>
                </div>
                @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-native-select
                    wire:model="student_id"
                    label="Student"
                    :error="$errors->first('student_id')"
                >
                    <option value="">Select student…</option>
                    @foreach ($this->students as $s)
                        <option value="{{ $s->id }}">{{ $s->full_name }}</option>
                    @endforeach
                </x-native-select>

                <x-input
                    wire:model="date"
                    label="Date"
                    type="date"
                    :error="$errors->first('date')"
                />
            </div>

            {{-- Category: free text + common suggestions --}}
            <div>
                <x-input
                    wire:model="category"
                    label="Category"
                    placeholder="e.g. Classroom Conduct, Academic Effort, Punctuality…"
                    :error="$errors->first('category')"
                />
                {{-- Quick pick chips --}}
                @php
                    $posChips = ['Academic Effort','Helping Others','Leadership','Punctuality','Improvement'];
                    $negChips = ['Classroom Disruption','Bullying','Late Submission','Disrespect','Absence'];
                    $chips    = $type === 'positive' ? $posChips : $negChips;
                @endphp
                <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach ($chips as $chip)
                        <button
                            wire:click="$set('category','{{ $chip }}')"
                            type="button"
                            @class([
                                'px-2.5 py-1 rounded-full text-xs font-medium border transition-colors',
                                'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' => $type === 'positive',
                                'border-red-300 bg-red-50 text-red-700 hover:bg-red-100' => $type === 'negative',
                            ])
                        >{{ $chip }}</button>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea
                    wire:model="description"
                    rows="3"
                    placeholder="Describe the behaviour in detail…"
                    class="w-full rounded-lg border border-slate-300 shadow-sm text-sm px-3 py-2.5 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y"
                ></textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Action Taken <span class="text-slate-400 font-normal">(optional)</span></label>
                <textarea
                    wire:model="action_taken"
                    rows="2"
                    placeholder="e.g. Spoke with student, contacted parent, detention…"
                    class="w-full rounded-lg border border-slate-300 shadow-sm text-sm px-3 py-2.5 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y"
                ></textarea>
                @error('action_taken') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button wire:click="$set('showFormModal', false)" label="Cancel" flat />
                <x-button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    :label="$editingId ? 'Update Log' : 'Save Log'"
                    :primary="$type === 'positive'"
                    :positive="false"
                    spinner="save"
                    @class(['bg-red-600 hover:bg-red-700 text-white border border-red-600 rounded-lg px-4 py-2 text-sm font-medium' => $type === 'negative'])
                />
            </div>
        </x-slot>
       </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         DETAIL MODAL
    ══════════════════════════════════════════════════════════ --}}
    @if ($viewing)
    @php
        $isPos = $viewing->type === 'positive';
        $detailBg = $isPos ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200';
    @endphp
    <x-modal wire:model.live="showDetailModal" title="Behaviour Log Detail" blur width="xl">
        <x-card class="relative">
            <div class="space-y-4 p-1">

            {{-- Header card --}}
            <div class="border rounded-xl px-4 py-3 flex items-start gap-3 {{ $detailBg }}">
                <div class="text-2xl mt-0.5">{{ $viewing->type_icon }}</div>
                <div>
                    <p class="font-bold text-slate-800">{{ $viewing->student->full_name ?? '—' }}</p>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        <span class="text-xs font-semibold {{ $isPos ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ ucfirst($viewing->type) }}
                        </span>
                        <span class="text-slate-400 text-xs">·</span>
                        <span class="text-xs text-slate-600 font-medium">{{ $viewing->category }}</span>
                        <span class="text-slate-400 text-xs">·</span>
                        <span class="text-xs text-slate-500">{{ $viewing->date->format('D, d M Y') }}</span>
                    </div>
                </div>
            </div>

            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-1">Description</p>
                <p class="text-sm text-slate-700 leading-relaxed">{{ $viewing->description }}</p>
            </div>

            @if ($viewing->action_taken)
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-1">Action Taken</p>
                <p class="text-sm text-slate-600 leading-relaxed">{{ $viewing->action_taken }}</p>
            </div>
            @endif

            <div class="text-xs text-slate-400 border-t border-slate-100 pt-3">
                Logged {{ $viewing->created_at->format('d M Y, H:i') }}
            </div>
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
         DELETE MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showDeleteModal" title="Delete Log" blur width="lg">
        <x-card class="relative">
            <div class="flex items-start gap-4 p-1">
            <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-red-50">
                <x-icon name="exclamation-triangle" class="w-6 h-6 text-red-500" />
            </div>
            <div>
                <p class="text-slate-700 font-medium">Delete this behaviour log entry?</p>
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
         STUDENT TIMELINE MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal
        wire:model.live="showStudentModal"
        :title="'Timeline — ' . ($timelineStudentName ?? '')"
        blur
        width="2xl"
    >
        <x-card class="relative">
            <div class="p-1 max-h-[65vh] overflow-y-auto space-y-3">
            @if (empty($timelineLogs))
                <p class="text-slate-400 text-sm text-center py-10">No behaviour logs for this student.</p>
            @else
                {{-- Mini stats --}}
                @php
                    $tlPos = collect($timelineLogs)->where('type','positive')->count();
                    $tlNeg = collect($timelineLogs)->where('type','negative')->count();
                    $tlTot = count($timelineLogs);
                    $tlRatio = $tlTot > 0 ? round(($tlPos / $tlTot) * 100) : 0;
                @endphp
                <div class="flex items-center gap-4 bg-slate-50 rounded-xl px-4 py-3 mb-2">
                    <div class="text-center flex-1">
                        <p class="text-lg font-bold text-emerald-600">{{ $tlPos }}</p>
                        <p class="text-xs text-slate-400">Positive</p>
                    </div>
                    <div class="text-center flex-1">
                        <p class="text-lg font-bold text-red-500">{{ $tlNeg }}</p>
                        <p class="text-xs text-slate-400">Negative</p>
                    </div>
                    <div class="flex-[2]">
                        <div class="flex justify-between text-xs text-slate-400 mb-1">
                            <span>Positive ratio</span>
                            <span class="font-semibold">{{ $tlRatio }}%</span>
                        </div>
                        <div class="w-full bg-red-100 rounded-full h-2 overflow-hidden">
                            <div class="bg-emerald-400 h-2 rounded-full" style="width:{{ $tlRatio }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Timeline entries --}}
                <div class="relative pl-6">
                    {{-- Vertical line --}}
                    <div class="absolute left-3 top-0 bottom-0 w-0.5 bg-slate-200"></div>

                    @foreach ($timelineLogs as $tl)
                    @php
                        $tlIsPos = ($tl['type'] ?? 'positive') === 'positive';
                        $tlDot   = $tlIsPos ? 'bg-emerald-400 border-emerald-200' : 'bg-red-400 border-red-200';
                        $tlCard  = $tlIsPos ? 'border-l-emerald-400' : 'border-l-red-400';
                    @endphp
                    <div class="relative mb-3">
                        {{-- Dot --}}
                        <div class="absolute -left-3.5 top-3 w-4 h-4 rounded-full border-2 {{ $tlDot }} shadow-sm flex items-center justify-center text-xs">
                        </div>

                        <div class="bg-white border border-l-4 border-slate-200 {{ $tlCard }} rounded-lg px-4 py-3 ml-2">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="{{ $tlIsPos ? 'text-emerald-700' : 'text-red-700' }} text-xs font-semibold">
                                        {{ $tlIsPos ? '✅ Positive' : '⚠️ Negative' }}
                                    </span>
                                    <span class="text-slate-400 text-xs">·</span>
                                    <span class="text-xs text-slate-600 font-medium">{{ $tl['category'] ?? '' }}</span>
                                </div>
                                <span class="text-xs text-slate-400 shrink-0 whitespace-nowrap">
                                    {{ isset($tl['date']) ? \Carbon\Carbon::parse($tl['date'])->format('d M Y') : '' }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-700 leading-relaxed">{{ $tl['description'] ?? '' }}</p>
                            @if (! empty($tl['action_taken']))
                                <p class="text-xs text-slate-500 italic mt-1.5">
                                    <x-icon name="arrow-right-circle" class="w-3 h-3 inline -mt-0.5 mr-0.5" />
                                    {{ $tl['action_taken'] }}
                                </p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex justify-between w-full">
                @if ($timelineStudentId)
                    <x-button
                        wire:click="openCreateModal({{ $timelineStudentId }})"
                        icon="plus"
                        label="Add Log for Student"
                        outline
                    />
                @endif
                <x-button wire:click="$set('showStudentModal', false)" label="Close" flat />
            </div>
        </x-slot>
        </x-card>
    </x-modal>

</div>
