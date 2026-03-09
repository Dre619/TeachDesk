<?php

use App\Models\ClassRoom;
use App\Models\LessonPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
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

    public bool $showFormModal   = false;
    public bool $showDeleteModal = false;
    public bool $showDetailModal = false;

    // ──────────────────────────────────────────
    // Form state
    // ──────────────────────────────────────────

    public ?int $editingId = null;

    #[Rule('required|string|max:200')]
    public string $title = '';

    #[Rule('required|string|max:100')]
    public string $subject = '';

    #[Rule('required|string|max:200')]
    public string $topic = '';

    #[Rule('required|integer|in:1,2,3')]
    public int $term = 1;

    #[Rule('required|integer|min:1|max:52')]
    public int $week_number = 1;

    #[Rule('required|integer|min:2000|max:2100')]
    public int $academic_year;

    #[Rule('nullable|integer|min:1|max:300')]
    public ?int $duration_minutes = null;

    #[Rule('nullable|string|max:2000')]
    public string $objectives = '';

    #[Rule('nullable|string|max:2000')]
    public string $resources = '';

    #[Rule('nullable|string|max:5000')]
    public string $content = '';

    #[Rule('nullable|string|max:2000')]
    public string $assessment = '';

    #[Rule('nullable|string|max:2000')]
    public string $homework = '';

    // ──────────────────────────────────────────
    // Delete / detail state
    // ──────────────────────────────────────────

    public ?int        $deletingId  = null;
    public ?LessonPlan $viewing     = null;
    public array       $exportingIds = [];

    // ──────────────────────────────────────────
    // Filters
    // ──────────────────────────────────────────

    public string $search     = '';
    public string $filterTerm = '';
    public string $filterYear = '';
    public string $filterWeek = '';

    // ──────────────────────────────────────────
    // View mode
    // ──────────────────────────────────────────

    public string $viewMode = 'list';

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function mount(int $classId): void
    {
        $class = ClassRoom::forTeacher(Auth::id())->findOrFail($classId);
        $this->classId       = $classId;
        $this->resolveRole($class);
        $this->academic_year = (int) date('Y');
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
    public function lessonPlans()
    {
        return LessonPlan::forTeacher(Auth::id())
            ->forClass($this->classId)
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('title',   'like', "%{$this->search}%")
                      ->orWhere('topic',   'like', "%{$this->search}%")
                      ->orWhere('subject', 'like', "%{$this->search}%")
                ))
            ->when($this->filterTerm !== '', fn ($q) =>
                $q->where('term', (int) $this->filterTerm)
            )
            ->when($this->filterYear !== '', fn ($q) =>
                $q->where('academic_year', (int) $this->filterYear)
            )
            ->when($this->filterWeek !== '', fn ($q) =>
                $q->where('week_number', (int) $this->filterWeek)
            )
            ->chronological()
            ->get();
    }

    #[Computed]
    public function availableYears(): array
    {
        return LessonPlan::forTeacher(Auth::id())
            ->forClass($this->classId)
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year')
            ->toArray();
    }

    // ──────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $plan = $this->findOwned($id);

        $this->editingId        = $plan->id;
        $this->title            = $plan->title;
        $this->subject          = $plan->subject;
        $this->topic            = $plan->topic;
        $this->term             = $plan->term;
        $this->week_number      = $plan->week_number;
        $this->academic_year    = $plan->academic_year;
        $this->duration_minutes = $plan->duration_minutes;
        $this->objectives       = $plan->objectives   ?? '';
        $this->resources        = $plan->resources    ?? '';
        $this->content          = $plan->content      ?? '';
        $this->assessment       = $plan->assessment   ?? '';
        $this->homework         = $plan->homework     ?? '';

        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title'            => $this->title,
            'subject'          => $this->subject,
            'topic'            => $this->topic,
            'term'             => $this->term,
            'week_number'      => $this->week_number,
            'academic_year'    => $this->academic_year,
            'duration_minutes' => $this->duration_minutes ?: null,
            'objectives'       => $this->objectives   ?: null,
            'resources'        => $this->resources    ?: null,
            'content'          => $this->content      ?: null,
            'assessment'       => $this->assessment   ?: null,
            'homework'         => $this->homework     ?: null,
        ];

        if ($this->editingId) {
            $this->findOwned($this->editingId)->update($data);
            $message = "'{$this->title}' updated.";
        } else {
            LessonPlan::create(array_merge($data, [
                'user_id'  => Auth::id(),
                'class_id' => $this->classId,
            ]));
            $message = "'{$this->title}' created.";
        }

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->lessonPlans);

        $this->notification()->success(title: 'Saved!', description: $message);
    }

    public function duplicate(int $id): void
    {
        $copy = $this->findOwned($id)->duplicate();
        $copy->save();
        unset($this->lessonPlans);

        $this->notification()->success(
            title:       'Duplicated',
            description: "'{$copy->title}' created as a copy.",
        );
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId      = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            $plan  = $this->findOwned($this->deletingId);
            $title = $plan->title;
            $plan->delete();
            unset($this->lessonPlans);

            $this->showDetailModal = false;
            $this->viewing         = null;

            $this->notification()->warning(
                title:       'Deleted',
                description: "'{$title}' has been removed.",
            );
        }

        $this->showDeleteModal = false;
        $this->deletingId      = null;
    }

    public function openDetailModal(int $id): void
    {
        $this->viewing         = $this->findOwned($id);
        $this->showDetailModal = true;
    }

    // ──────────────────────────────────────────
    // PDF export
    // ──────────────────────────────────────────

    public function exportPdf(int $id): mixed
    {
        $this->exportingIds[] = $id;

        try {
            $plan      = $this->findOwned($id);
            $classroom = $this->classroom;
            $teacher   = Auth::user();

            $html = view('lessons.lesson-plan-pdf', compact('plan', 'classroom', 'teacher'))->render();

            $filename = 'lesson-plan-' . \Str::slug($plan->title) . '-' . $plan->academic_year . '-t' . $plan->term . '-w' . $plan->week_number . '.pdf';
            $path     = 'lesson-plans/' . $filename;
            $fullPath = Storage::disk('public')->path($path);

            @mkdir(dirname($fullPath), 0755, true);

            Browsershot::html($html)
                ->format('A4')
                ->margins(10, 10, 10, 10)
                ->showBackground()
                ->waitUntilNetworkIdle()
                ->save($fullPath);

            $this->exportingIds = array_values(array_diff($this->exportingIds, [$id]));

            return response()->download($fullPath, $filename)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            $this->exportingIds = array_values(array_diff($this->exportingIds, [$id]));
            $this->notification()->error(title: 'Export failed', description: $e->getMessage());
            return null;
        }
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    private function findOwned(int $id): LessonPlan
    {
        return LessonPlan::forTeacher(Auth::id())
            ->forClass($this->classId)
            ->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId        = null;
        $this->title            = '';
        $this->subject          = '';
        $this->topic            = '';
        $this->term             = 1;
        $this->week_number      = 1;
        $this->academic_year    = (int) date('Y');
        $this->duration_minutes = null;
        $this->objectives       = '';
        $this->resources        = '';
        $this->content          = '';
        $this->assessment       = '';
        $this->homework         = '';
        $this->resetValidation();
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
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Lesson Plans</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        {{ $this->lessonPlans->count() }} {{ Str::plural('plan', $this->lessonPlans->count()) }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    {{-- View toggle --}}
                    <div class="flex items-center bg-slate-100 rounded-lg p-1 gap-1">
                        <button
                            wire:click="$set('viewMode', 'list')"
                            @class([
                                'px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                                'bg-white shadow text-slate-800' => $viewMode === 'list',
                                'text-slate-500 hover:text-slate-700' => $viewMode !== 'list',
                            ])
                        >
                            <x-icon name="list-bullet" class="w-4 h-4 inline-block -mt-0.5 mr-1" />
                            List
                        </button>
                        <button
                            wire:click="$set('viewMode', 'grid')"
                            @class([
                                'px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                                'bg-white shadow text-slate-800' => $viewMode === 'grid',
                                'text-slate-500 hover:text-slate-700' => $viewMode !== 'grid',
                            ])
                        >
                            <x-icon name="table-cells" class="w-4 h-4 inline-block -mt-0.5 mr-1" />
                            Term Grid
                        </button>
                    </div>

                    <x-button
                        wire:click="openCreateModal"
                        icon="plus"
                        label="New Plan"
                        primary
                        class="w-full sm:w-auto"
                    />
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto px-6 py-8 space-y-6">

        {{-- ══════════════════════════════════════════════════════════
             FILTER BAR
        ══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="col-span-2 sm:col-span-1">
                    <x-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search title, topic…"
                        icon="magnifying-glass"
                        shadowless
                    />
                </div>
                <div>
                    <x-native-select wire:model.live="filterTerm" shadowless>
                        <option value="">All terms</option>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                    </x-native-select>
                </div>
                <div>
                    <x-native-select wire:model.live="filterYear" shadowless>
                        <option value="">All years</option>
                        @foreach ($this->availableYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </x-native-select>
                </div>
                <div>
                    <x-input
                        wire:model.live.debounce.300ms="filterWeek"
                        type="number"
                        placeholder="Week #"
                        shadowless
                        min="1"
                        max="52"
                    />
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             EMPTY STATE
        ══════════════════════════════════════════════════════════ --}}
        @if ($this->lessonPlans->isEmpty())
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                <x-icon name="document-text" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-500 font-medium">No lesson plans found.</p>
                <p class="text-slate-400 text-sm mt-1">Create your first plan to get started.</p>
                <x-button wire:click="openCreateModal" label="Create Lesson Plan" primary class="mt-5" />
            </div>

        {{-- ══════════════════════════════════════════════════════════
             LIST VIEW
        ══════════════════════════════════════════════════════════ --}}
        @elseif ($viewMode === 'list')
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-left">
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Title / Topic</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden sm:table-cell">Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden md:table-cell">Term</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden md:table-cell">Week</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden lg:table-cell">Duration</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->lessonPlans as $plan)
                        @php $isExporting = in_array($plan->id, $exportingIds); @endphp
                        <tr wire:key="plan-{{ $plan->id }}" class="hover:bg-slate-50 transition-colors">

                            <td class="px-5 py-3">
                                <p class="font-semibold text-slate-800 leading-tight">{{ $plan->title }}</p>
                                <p class="text-xs text-slate-400 mt-0.5 line-clamp-1">{{ $plan->topic }}</p>
                            </td>

                            <td class="px-5 py-3 hidden sm:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                    {{ $plan->subject }}
                                </span>
                            </td>

                            <td class="px-5 py-3 hidden md:table-cell">
                                @php
                                    $termColors = [
                                        1 => 'bg-blue-50 text-blue-700',
                                        2 => 'bg-amber-50 text-amber-700',
                                        3 => 'bg-emerald-50 text-emerald-700',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $termColors[$plan->term] ?? 'bg-slate-100 text-slate-600' }}">
                                    Term {{ $plan->term }}
                                </span>
                            </td>

                            <td class="px-5 py-3 text-slate-600 text-xs hidden md:table-cell">
                                Wk {{ $plan->week_number }}
                            </td>

                            <td class="px-5 py-3 text-slate-500 text-xs hidden lg:table-cell">
                                {{ $plan->duration_label ?? '—' }}
                            </td>

                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <x-button
                                        wire:click="openDetailModal({{ $plan->id }})"
                                        icon="eye"
                                        flat xs
                                        class="text-slate-500"
                                        title="View"
                                    />
                                    <x-button
                                        wire:click="exportPdf({{ $plan->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="exportPdf({{ $plan->id }})"
                                        :icon="$isExporting ? 'arrow-path' : 'arrow-down-tray'"
                                        flat xs
                                        class="text-violet-600"
                                        title="Export PDF"
                                        :disabled="$isExporting"
                                    />
                                    <x-button
                                        wire:click="duplicate({{ $plan->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="duplicate({{ $plan->id }})"
                                        icon="document-duplicate"
                                        flat xs
                                        class="text-sky-600"
                                        title="Duplicate"
                                    />
                                    <x-button
                                        wire:click="openEditModal({{ $plan->id }})"
                                        icon="pencil"
                                        flat xs
                                        class="text-indigo-600"
                                        title="Edit"
                                    />
                                    <x-button
                                        wire:click="confirmDelete({{ $plan->id }})"
                                        icon="trash"
                                        flat xs
                                        class="text-red-500"
                                        title="Delete"
                                    />
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        {{-- ══════════════════════════════════════════════════════════
             TERM GRID VIEW
        ══════════════════════════════════════════════════════════ --}}
        @else
            @php
                $termMeta = [
                    1 => ['label' => 'Term 1', 'sub' => 'Jan – Apr', 'ring' => 'ring-blue-200',   'header' => 'bg-blue-600'],
                    2 => ['label' => 'Term 2', 'sub' => 'May – Aug', 'ring' => 'ring-amber-200',  'header' => 'bg-amber-500'],
                    3 => ['label' => 'Term 3', 'sub' => 'Sep – Dec', 'ring' => 'ring-emerald-200','header' => 'bg-emerald-600'],
                ];
                $grouped = $this->lessonPlans->groupBy('term');
            @endphp

            <div class="space-y-8">
                @foreach ([1, 2, 3] as $termNum)
                    @php
                        $meta   = $termMeta[$termNum];
                        $plans  = $grouped->get($termNum, collect());
                        $byWeek = $plans->groupBy('week_number');
                    @endphp

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden ring-1 {{ $meta['ring'] }}">
                        <div class="{{ $meta['header'] }} px-5 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <h2 class="text-white font-bold text-base">{{ $meta['label'] }}</h2>
                                <span class="text-white/70 text-sm">{{ $meta['sub'] }}</span>
                            </div>
                            <span class="bg-white/20 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                                {{ $plans->count() }} {{ Str::plural('plan', $plans->count()) }}
                            </span>
                        </div>

                        @if ($plans->isEmpty())
                            <div class="py-10 text-center text-slate-400 text-sm">No plans for this term yet.</div>
                        @else
                            <div class="p-4 overflow-x-auto">
                                <div class="flex gap-3" style="min-width: max-content;">
                                    @foreach ($byWeek->sortKeys() as $week => $weekPlans)
                                        <div class="w-52 shrink-0">
                                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2 px-1">
                                                Week {{ $week }}
                                            </div>
                                            <div class="space-y-2">
                                                @foreach ($weekPlans as $plan)
                                                    <div
                                                        wire:key="grid-{{ $plan->id }}"
                                                        class="group relative bg-slate-50 border border-slate-200 rounded-lg p-3 hover:border-slate-300 hover:shadow-sm transition-all cursor-pointer"
                                                        wire:click="openDetailModal({{ $plan->id }})"
                                                    >
                                                        <p class="font-semibold text-slate-800 text-xs leading-tight line-clamp-2">{{ $plan->title }}</p>
                                                        <p class="text-xs text-slate-400 mt-1 line-clamp-1">{{ $plan->topic }}</p>
                                                        <div class="flex items-center justify-between mt-1.5">
                                                            <p class="text-xs text-indigo-600 font-medium">{{ $plan->subject }}</p>
                                                            @if ($plan->duration_label)
                                                                <p class="text-xs text-slate-400">{{ $plan->duration_label }}</p>
                                                            @endif
                                                        </div>

                                                        {{-- Hover actions --}}
                                                        <div class="absolute top-2 right-2 hidden group-hover:flex items-center gap-1">
                                                            <button
                                                                wire:click.stop="exportPdf({{ $plan->id }})"
                                                                class="p-1 rounded bg-white border border-slate-200 text-violet-600 hover:bg-violet-50"
                                                                title="Export PDF"
                                                            >
                                                                <x-icon name="arrow-down-tray" class="w-3 h-3" />
                                                            </button>
                                                            <button
                                                                wire:click.stop="duplicate({{ $plan->id }})"
                                                                class="p-1 rounded bg-white border border-slate-200 text-sky-600 hover:bg-sky-50"
                                                                title="Duplicate"
                                                            >
                                                                <x-icon name="document-duplicate" class="w-3 h-3" />
                                                            </button>
                                                            <button
                                                                wire:click.stop="openEditModal({{ $plan->id }})"
                                                                class="p-1 rounded bg-white border border-slate-200 text-indigo-600 hover:bg-indigo-50"
                                                                title="Edit"
                                                            >
                                                                <x-icon name="pencil" class="w-3 h-3" />
                                                            </button>
                                                            <button
                                                                wire:click.stop="confirmDelete({{ $plan->id }})"
                                                                class="p-1 rounded bg-white border border-slate-200 text-red-500 hover:bg-red-50"
                                                                title="Delete"
                                                            >
                                                                <x-icon name="trash" class="w-3 h-3" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

    </div>


    {{-- ══════════════════════════════════════════════════════════
         CREATE / EDIT MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal
        wire:model.live="showFormModal"
        :title="$editingId ? 'Edit Lesson Plan' : 'New Lesson Plan'"
        blur
        persistent
        width="3xl"
    >
        <x-card class="relative">
            <div class="p-1 space-y-5 max-h-[75vh] overflow-y-auto">

                {{-- ── Basic info ── --}}
                <div class="space-y-3">
                    <x-input
                        wire:model="title"
                        label="Title"
                        placeholder="e.g. Introduction to Fractions"
                        :error="$errors->first('title')"
                    />
                    <div class="grid grid-cols-2 gap-4">
                        <x-input
                            wire:model="subject"
                            label="Subject"
                            placeholder="e.g. Mathematics"
                            :error="$errors->first('subject')"
                        />
                        <x-input
                            wire:model="topic"
                            label="Topic"
                            placeholder="e.g. Adding fractions"
                            :error="$errors->first('topic')"
                        />
                    </div>
                    <div class="grid grid-cols-4 gap-4">
                        <x-native-select wire:model="term" label="Term" :error="$errors->first('term')">
                            <option value="1">Term 1 (Jan–Apr)</option>
                            <option value="2">Term 2 (May–Aug)</option>
                            <option value="3">Term 3 (Sep–Dec)</option>
                        </x-native-select>
                        <x-input wire:model="week_number" label="Week" type="number" min="1" max="52" :error="$errors->first('week_number')" />
                        <x-input wire:model="academic_year" label="Year" type="number" :error="$errors->first('academic_year')" />
                        <x-input wire:model="duration_minutes" label="Duration (min)" type="number" min="1" max="300" placeholder="e.g. 40" :error="$errors->first('duration_minutes')" />
                    </div>
                </div>

                {{-- ── Section divider ── --}}
                <div class="border-t border-slate-100 pt-4 space-y-4">

                    {{-- Learning objectives --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-5 h-5 rounded bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">O</span>
                            <label class="text-sm font-semibold text-slate-700">Learning Objectives</label>
                        </div>
                        <textarea
                            wire:model="objectives"
                            rows="3"
                            placeholder="By the end of this lesson, students will be able to…"
                            class="w-full rounded-lg border border-slate-300 shadow-sm text-sm px-3 py-2.5 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                        ></textarea>
                        @error('objectives') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Resources --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-5 h-5 rounded bg-yellow-100 text-yellow-700 text-xs font-bold flex items-center justify-center">R</span>
                            <label class="text-sm font-semibold text-slate-700">Materials &amp; Resources</label>
                        </div>
                        <textarea
                            wire:model="resources"
                            rows="2"
                            placeholder="Textbooks, worksheets, charts, calculators…"
                            class="w-full rounded-lg border border-slate-300 shadow-sm text-sm px-3 py-2.5 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                        ></textarea>
                        @error('resources') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Lesson content --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-5 h-5 rounded bg-violet-100 text-violet-700 text-xs font-bold flex items-center justify-center">L</span>
                            <label class="text-sm font-semibold text-slate-700">Lesson Content &amp; Activities</label>
                        </div>
                        <textarea
                            wire:model="content"
                            rows="5"
                            placeholder="Introduction, step-by-step activities, examples, group work…"
                            class="w-full rounded-lg border border-slate-300 shadow-sm text-sm px-3 py-2.5 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                        ></textarea>
                        @error('content') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Assessment + Homework side by side --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-5 h-5 rounded bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center">A</span>
                                <label class="text-sm font-semibold text-slate-700">Assessment &amp; Closure</label>
                            </div>
                            <textarea
                                wire:model="assessment"
                                rows="3"
                                placeholder="How will you check understanding? Oral questions, exit ticket…"
                                class="w-full rounded-lg border border-slate-300 shadow-sm text-sm px-3 py-2.5 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                            ></textarea>
                            @error('assessment') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-5 h-5 rounded bg-orange-100 text-orange-700 text-xs font-bold flex items-center justify-center">H</span>
                                <label class="text-sm font-semibold text-slate-700">Homework &amp; Extension</label>
                            </div>
                            <textarea
                                wire:model="homework"
                                rows="3"
                                placeholder="Homework task, extension activity for fast finishers…"
                                class="w-full rounded-lg border border-slate-300 shadow-sm text-sm px-3 py-2.5 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                            ></textarea>
                            @error('homework') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                </div>

            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('showFormModal', false)" label="Cancel" flat />
                    <x-button
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        :label="$editingId ? 'Update Plan' : 'Create Plan'"
                        primary
                        spinner="save"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         DELETE CONFIRMATION MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showDeleteModal" title="Delete Lesson Plan" blur width="lg">
        <x-card class="relative">
            <div class="flex items-start gap-4 p-1">
                <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-red-50">
                    <x-icon name="exclamation-triangle" class="w-6 h-6 text-red-500" />
                </div>
                <div>
                    <p class="text-slate-700 font-medium">Delete this lesson plan?</p>
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
         DETAIL VIEW MODAL
    ══════════════════════════════════════════════════════════ --}}
    @if ($viewing)
    <x-modal wire:model.live="showDetailModal" :title="$viewing->title" blur width="2xl">
        <x-card class="relative">
            <div class="space-y-4 p-1 max-h-[75vh] overflow-y-auto">

                {{-- Meta badges --}}
                @php
                    $termColors = [
                        1 => 'bg-blue-50 text-blue-700 border-blue-200',
                        2 => 'bg-amber-50 text-amber-700 border-amber-200',
                        3 => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    ];
                @endphp
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $termColors[$viewing->term] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                        {{ $viewing->term_label }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                        <x-icon name="calendar" class="w-3.5 h-3.5" />
                        Week {{ $viewing->week_number }} &middot; {{ $viewing->academic_year }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ $viewing->subject }}
                    </span>
                    @if ($viewing->duration_label)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-violet-50 text-violet-700 border border-violet-200">
                            <x-icon name="clock" class="w-3.5 h-3.5" />
                            {{ $viewing->duration_label }}
                        </span>
                    @endif
                </div>

                {{-- Topic --}}
                <div class="bg-slate-50 rounded-lg border border-slate-200 px-4 py-3">
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-0.5">Topic</p>
                    <p class="text-slate-800 font-bold">{{ $viewing->topic }}</p>
                </div>

                {{-- Structured sections --}}
                @php
                    $sections = [
                        ['key' => 'objectives', 'label' => 'Learning Objectives',        'icon' => 'O', 'colors' => 'bg-blue-50 border-l-blue-400',   'ic' => 'bg-blue-100 text-blue-700'],
                        ['key' => 'resources',  'label' => 'Materials & Resources',       'icon' => 'R', 'colors' => 'bg-yellow-50 border-l-yellow-400','ic' => 'bg-yellow-100 text-yellow-700'],
                        ['key' => 'content',    'label' => 'Lesson Content & Activities', 'icon' => 'L', 'colors' => 'bg-violet-50 border-l-violet-400','ic' => 'bg-violet-100 text-violet-700'],
                        ['key' => 'assessment', 'label' => 'Assessment & Closure',        'icon' => 'A', 'colors' => 'bg-emerald-50 border-l-emerald-400','ic' => 'bg-emerald-100 text-emerald-700'],
                        ['key' => 'homework',   'label' => 'Homework & Extension',        'icon' => 'H', 'colors' => 'bg-orange-50 border-l-orange-400','ic' => 'bg-orange-100 text-orange-700'],
                    ];
                @endphp

                @foreach ($sections as $sec)
                @php $value = $viewing->{$sec['key']}; @endphp
                @if ($value)
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center {{ $sec['ic'] }}">{{ $sec['icon'] }}</span>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $sec['label'] }}</p>
                    </div>
                    <div class="rounded-lg border border-l-4 border-slate-200 {{ $sec['colors'] }} px-4 py-3 text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">{{ $value }}</div>
                </div>
                @endif
                @endforeach

                @if (! $viewing->objectives && ! $viewing->resources && ! $viewing->content && ! $viewing->assessment && ! $viewing->homework)
                    <div class="text-center py-6 text-slate-400 text-sm italic">No content recorded for this plan.</div>
                @endif

            </div>

            <x-slot name="footer">
                <div class="flex justify-between gap-3">
                    <div class="flex gap-2">
                        <x-button
                            wire:click="exportPdf({{ $viewing->id }})"
                            wire:loading.attr="disabled"
                            wire:target="exportPdf({{ $viewing->id }})"
                            icon="arrow-down-tray"
                            label="Export PDF"
                            outline
                            class="text-violet-700 border-violet-300 hover:bg-violet-50"
                            spinner="exportPdf({{ $viewing->id }})"
                        />
                        <x-button wire:click="duplicate({{ $viewing->id }})" icon="document-duplicate" label="Duplicate" outline />
                        <x-button wire:click="openEditModal({{ $viewing->id }})" icon="pencil" label="Edit" outline />
                    </div>
                    <x-button wire:click="$set('showDetailModal', false)" label="Close" flat />
                </div>
            </x-slot>
        </x-card>
    </x-modal>
    @endif

</div>
