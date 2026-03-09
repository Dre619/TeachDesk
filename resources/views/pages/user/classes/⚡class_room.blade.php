<?php

use App\Models\ClassRoom;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use WireUi\Traits\WireUiActions;


new class extends Component
{
    use WireUiActions;

    // ──────────────────────────────────────────
    // Modal visibility flags
    // ──────────────────────────────────────────

    public bool $showFormModal    = false;
    public bool $showDeleteModal  = false;
    public bool $showDetailModal  = false;

    // ──────────────────────────────────────────
    // Form state
    // ──────────────────────────────────────────

    public ?int $editingId = null;

    #[Rule('required|string|max:100')]
    public string $name = '';

    #[Rule('required|string|max:100')]
    public string $subject = '';

    #[Rule('required|integer|min:2000|max:2100')]
    public int $academic_year;

    #[Rule('boolean')]
    public bool $is_active = true;

    // ──────────────────────────────────────────
    // Delete / detail state
    // ──────────────────────────────────────────

    public ?int $deletingId   = null;
    public ?ClassRoom $viewing = null;

    // ──────────────────────────────────────────
    // Filter / search state
    // ──────────────────────────────────────────

    public string $search       = '';
    public string $filterYear   = '';
    public string $filterActive = '';

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function mount(): void
    {
        $this->academic_year = (int) date('Y');
    }

    // ──────────────────────────────────────────
    // Computed property – filtered classroom list
    // ──────────────────────────────────────────

    #[Computed]
    public function classrooms()
    {
        return ClassRoom::forTeacher(Auth::id())
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('subject', 'like', "%{$this->search}%")
                ))
            ->when($this->filterYear !== '', fn ($q) =>
                $q->forYear((int) $this->filterYear)
            )
            ->when($this->filterActive !== '', fn ($q) =>
                $q->where('is_active', (bool) $this->filterActive)
            )
            ->withCount(['students as active_students_count' => fn ($q) =>
                $q->whereNull('deleted_at')
            ])
            ->latest()
            ->get();
    }

    #[Computed]
    public function availableYears(): array
    {
        return ClassRoom::forTeacher(Auth::id())
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year')
            ->toArray();
    }

    // ──────────────────────────────────────────
    // Create
    // ──────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    // ──────────────────────────────────────────
    // Edit
    // ──────────────────────────────────────────

    public function openEditModal(int $id): void
    {
        $classroom = $this->findOwned($id);

        $this->editingId      = $classroom->id;
        $this->name           = $classroom->name;
        $this->subject        = $classroom->subject;
        $this->academic_year  = $classroom->academic_year;
        $this->is_active      = $classroom->is_active;

        $this->showFormModal = true;
    }

    // ──────────────────────────────────────────
    // Save (create or update)
    // ──────────────────────────────────────────

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'          => $this->name,
            'subject'       => $this->subject,
            'academic_year' => $this->academic_year,
            'is_active'     => $this->is_active,
        ];

        if ($this->editingId) {
            $this->findOwned($this->editingId)->update($data);
            $message = 'Class updated successfully.';
        } else {
            Auth::user()->classes()->create($data);
            $message = 'Class created successfully.';
        }

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->classrooms); // bust computed cache

        $this->notification()->success(title: 'Done!', description: $message);
    }

    // ──────────────────────────────────────────
    // Delete
    // ──────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deletingId       = $id;
        $this->showDeleteModal  = true;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            $this->findOwned($this->deletingId)->delete();
            unset($this->classrooms);

            $this->notification()->warning(
                title: 'Deleted',
                description: 'The class has been removed.'
            );
        }

        $this->showDeleteModal = false;
        $this->deletingId      = null;
    }

    // ──────────────────────────────────────────
    // Detail view
    // ──────────────────────────────────────────

    public function openDetailModal(int $id): void
    {
        $this->viewing         = $this->findOwned($id)->load('activeStudents');
        $this->showDetailModal = true;
    }

    // ──────────────────────────────────────────
    // Toggle active status inline
    // ──────────────────────────────────────────

    public function toggleActive(int $id): void
    {
        $classroom = $this->findOwned($id);
        $classroom->update(['is_active' => ! $classroom->is_active]);
        unset($this->classrooms);
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    private function findOwned(int $id): ClassRoom
    {
        return ClassRoom::where('user_id', Auth::id())->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId     = null;
        $this->name          = '';
        $this->subject       = '';
        $this->academic_year = (int) date('Y');
        $this->is_active     = true;
        $this->resetValidation();
    }
};
?>

<div class="min-h-screen bg-slate-50 font-sans">

    {{-- ══════════════════════════════════════════════════════════
         PAGE HEADER
    ══════════════════════════════════════════════════════════ --}}
    <div class="bg-white border-b border-slate-200 px-6 py-5">
        <div class="mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">My Classrooms</h1>
                <p class="text-sm text-slate-500 mt-0.5">
                    {{ $this->classrooms->count() }}
                    {{ Str::plural('class', $this->classrooms->count()) }} found
                </p>
            </div>
            <x-button
                wire:click="openCreateModal"
                icon="plus"
                label="New Class"
                primary
                class="w-full sm:w-auto"
            />
        </div>
    </div>

    <div class="mx-auto px-6 py-8 space-y-6">

        {{-- ══════════════════════════════════════════════════════════
             FILTERS BAR
        ══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

                {{-- Search --}}
                <div class="sm:col-span-1">
                    <x-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search name or subject…"
                        icon="magnifying-glass"
                        shadowless
                    />
                </div>

                {{-- Year filter --}}
                <div>
                    <x-native-select wire:model.live="filterYear" shadowless>
                        <option value="">All years</option>
                        @foreach ($this->availableYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </x-native-select>
                </div>

                {{-- Status filter --}}
                <div>
                    <x-native-select wire:model.live="filterActive" shadowless>
                        <option value="">All statuses</option>
                        <option value="1">Active</option>
                        <option value="0">Archived</option>
                    </x-native-select>
                </div>

            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             CLASS GRID
        ══════════════════════════════════════════════════════════ --}}
        @if ($this->classrooms->isEmpty())
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                <x-icon name="academic-cap" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-500 font-medium">No classes found.</p>
                <p class="text-slate-400 text-sm mt-1">Create your first class to get started.</p>
                <x-button wire:click="openCreateModal" label="Create Class" primary class="mt-5" />
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($this->classrooms as $classroom)
                    <div
                        wire:key="classroom-{{ $classroom->id }}"
                        class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow duration-200 flex flex-col"
                    >
                        {{-- Card header --}}
                        <div class="px-5 pt-5 pb-4 flex-1">
                            <div class="flex items-start justify-between gap-2 mb-3">
                                <div>
                                    <h2 class="text-base font-bold text-slate-800 leading-tight">
                                        {{ $classroom->name }}
                                    </h2>
                                    <p class="text-sm text-slate-500 mt-0.5">{{ $classroom->subject }}</p>
                                </div>
                                {{-- Active badge --}}
                                @if ($classroom->is_active)
                                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Archived
                                    </span>
                                @endif
                            </div>

                            {{-- Stats row --}}
                            <div class="flex items-center gap-4 text-sm text-slate-500">
                                <span class="flex items-center gap-1.5">
                                    <x-icon name="users" class="w-4 h-4 text-slate-400" />
                                    {{ $classroom->active_students_count }}
                                    {{ Str::plural('student', $classroom->active_students_count) }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <x-icon name="calendar" class="w-4 h-4 text-slate-400" />
                                    {{ $classroom->academic_year }}
                                </span>
                            </div>
                        </div>

                        {{-- Card footer actions --}}
                        @if($classroom->user_id == auth()->id())
                            {{-- Nav grid --}}
                            <div class="border-t border-slate-100 px-4 pt-3 pb-2">
                                <div class="grid grid-cols-4 gap-0.5">

                                    {{-- Students --}}
                                    <a href="{{ route('user.students', $classroom->id) }}" wire:navigate
                                       class="group flex flex-col items-center gap-1 rounded-lg p-2 transition-colors hover:bg-indigo-50">
                                        <svg class="size-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                        </svg>
                                        <span class="text-[10px] leading-tight text-slate-500 group-hover:text-indigo-700">Students</span>
                                    </a>

                                    {{-- Lesson Plans --}}
                                    <a href="{{ route('user.lesson.plans', $classroom->id) }}" wire:navigate
                                       class="group flex flex-col items-center gap-1 rounded-lg p-2 transition-colors hover:bg-violet-50">
                                        <svg class="size-4 text-violet-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        <span class="text-[10px] leading-tight text-slate-500 group-hover:text-violet-700">Plans</span>
                                    </a>

                                    {{-- Assessments --}}
                                    <a href="{{ route('user.assesments', $classroom->id) }}" wire:navigate
                                       class="group flex flex-col items-center gap-1 rounded-lg p-2 transition-colors hover:bg-blue-50">
                                        <svg class="size-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                        </svg>
                                        <span class="text-[10px] leading-tight text-slate-500 group-hover:text-blue-700">Assessments</span>
                                    </a>

                                    {{-- Attendance --}}
                                    <a href="{{ route('user.attendance', $classroom->id) }}" wire:navigate
                                       class="group flex flex-col items-center gap-1 rounded-lg p-2 transition-colors hover:bg-emerald-50">
                                        <svg class="size-4 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                                        </svg>
                                        <span class="text-[10px] leading-tight text-slate-500 group-hover:text-emerald-700">Attendance</span>
                                    </a>

                                    {{-- Reports --}}
                                    <a href="{{ route('user.reports', $classroom->id) }}" wire:navigate
                                       class="group flex flex-col items-center gap-1 rounded-lg p-2 transition-colors hover:bg-amber-50">
                                        <svg class="size-4 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                        </svg>
                                        <span class="text-[10px] leading-tight text-slate-500 group-hover:text-amber-700">Reports</span>
                                    </a>

                                    {{-- Behaviour Logs --}}
                                    <a href="{{ route('user.behaviour-logs', $classroom->id) }}" wire:navigate
                                       class="group flex flex-col items-center gap-1 rounded-lg p-2 transition-colors hover:bg-rose-50">
                                        <svg class="size-4 text-rose-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </svg>
                                        <span class="text-[10px] leading-tight text-slate-500 group-hover:text-rose-700">Behaviour</span>
                                    </a>

                                    {{-- Members --}}
                                    <a href="{{ route('user.members', $classroom->id) }}" wire:navigate
                                       class="group flex flex-col items-center gap-1 rounded-lg p-2 transition-colors hover:bg-teal-50">
                                        <svg class="size-4 text-teal-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                        </svg>
                                        <span class="text-[10px] leading-tight text-slate-500 group-hover:text-teal-700">Members</span>
                                    </a>

                                </div>

                                {{-- Management actions --}}
                                <div class="mt-2 flex items-center justify-between border-t border-slate-100 pt-2">
                                    <button
                                        wire:click="openDetailModal({{ $classroom->id }})"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
                                    >
                                        <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        View
                                    </button>
                                    <div class="flex items-center gap-1">
                                        <button
                                            wire:click="openEditModal({{ $classroom->id }})"
                                            class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-indigo-50 hover:text-indigo-600"
                                            title="Edit"
                                        >
                                            <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                        </button>
                                        <button
                                            wire:click="toggleActive({{ $classroom->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleActive({{ $classroom->id }})"
                                            class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                                            title="{{ $classroom->is_active ? 'Archive' : 'Restore' }}"
                                        >
                                            @if($classroom->is_active)
                                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                                </svg>
                                            @else
                                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                </svg>
                                            @endif
                                        </button>
                                        <button
                                            wire:click="confirmDelete({{ $classroom->id }})"
                                            class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                                            title="Delete"
                                        >
                                            <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Invited: Assessments only --}}
                            <div class="border-t border-slate-100 px-4 py-3">
                                <a href="{{ route('user.assesments', $classroom->id) }}" wire:navigate
                                   class="group flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 transition-colors hover:bg-blue-50 hover:text-blue-700">
                                    <svg class="size-4 shrink-0 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                    </svg>
                                    Assessments
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

    </div>{{-- /max-w-7xl --}}


    {{-- ══════════════════════════════════════════════════════════
         CREATE / EDIT MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal
        wire:model.live="showFormModal"
        :title="$editingId ? 'Edit Class' : 'Create New Class'"
        blur
        persistent
        width="xl"
    >
        <x-card class="relative">
            <div class="space-y-4 p-1">

            <x-input
                wire:model="name"
                label="Class Name"
                placeholder="e.g. Grade 8A"
                :error="$errors->first('name')"
            />

            <x-input
                wire:model="subject"
                label="Subject"
                placeholder="e.g. Mathematics"
                :error="$errors->first('subject')"
            />

            <x-input
                wire:model="academic_year"
                label="Academic Year"
                type="number"
                placeholder="{{ date('Y') }}"
                :error="$errors->first('academic_year')"
            />

            <x-toggle
                wire:model="is_active"
                label="Active"
                left-label="Archived"
            />

        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button
                    wire:click="$set('showFormModal', false)"
                    label="Cancel"
                    flat
                />
                <x-button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    :label="$editingId ? 'Update Class' : 'Create Class'"
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
    <x-modal
        wire:model.live="showDeleteModal"
        title="Delete Class"
        blur
        width="lg"
    >
        <x-card class="relative">
            <div class="flex items-start gap-4 p-1">
            <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-red-50">
                <x-icon name="exclamation-triangle" class="w-6 h-6 text-red-500" />
            </div>
            <div>
                <p class="text-slate-700 font-medium">Are you sure you want to delete this class?</p>
                <p class="text-slate-500 text-sm mt-1">
                    This action cannot be undone. All related attendance, lesson plans, and assessments may be affected.
                </p>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button
                    wire:click="$set('showDeleteModal', false)"
                    label="Cancel"
                    flat
                />
                <x-button
                    wire:click="delete"
                    wire:loading.attr="disabled"
                    wire:target="delete"
                    label="Yes, Delete"
                    red
                    spinner="delete"
                />
            </div>
        </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         DETAIL VIEW MODAL
    ══════════════════════════════════════════════════════════ --}}
    @if ($viewing)
    <x-modal
        wire:model.live="showDetailModal"
        :title="$viewing->display_name"
        blur
        width="2xl"
    >
        <x-card class="relative">
            <div class="space-y-5 p-1">

            {{-- Meta grid --}}
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-slate-400 font-medium uppercase tracking-wide text-xs">Subject</dt>
                    <dd class="mt-1 text-slate-800 font-semibold">{{ $viewing->subject }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400 font-medium uppercase tracking-wide text-xs">Academic Year</dt>
                    <dd class="mt-1 text-slate-800 font-semibold">{{ $viewing->academic_year }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400 font-medium uppercase tracking-wide text-xs">Status</dt>
                    <dd class="mt-1">
                        @if ($viewing->is_active)
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Archived
                            </span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-400 font-medium uppercase tracking-wide text-xs">Total Students</dt>
                    <dd class="mt-1 text-slate-800 font-semibold">
                        {{ $viewing->activeStudents->count() }}
                    </dd>
                </div>
            </dl>

            {{-- Students list --}}
            @if ($viewing->activeStudents->isNotEmpty())
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">
                        Students
                    </h3>
                    <ul class="divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden text-sm">
                        @foreach ($viewing->activeStudents->take(10) as $student)
                            <li class="flex items-center gap-3 px-4 py-2.5 bg-white hover:bg-slate-50 transition-colors">
                                <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($student->name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-slate-700">{{ $student->name ?? 'Unnamed student' }}</span>
                            </li>
                        @endforeach
                        @if ($viewing->activeStudents->count() > 10)
                            <li class="px-4 py-2.5 bg-slate-50 text-slate-400 text-xs italic">
                                + {{ $viewing->activeStudents->count() - 10 }} more…
                            </li>
                        @endif
                    </ul>
                </div>
            @else
                <p class="text-slate-400 text-sm italic">No students enrolled yet.</p>
            @endif

        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button
                    wire:click="openEditModal({{ $viewing->id }})"
                    icon="pencil"
                    label="Edit Class"
                    outline
                />
                <x-button
                    wire:click="$set('showDetailModal', false)"
                    label="Close"
                    flat
                />
            </div>
        </x-slot>
        </x-card>
    </x-modal>
    @endif

</div>
