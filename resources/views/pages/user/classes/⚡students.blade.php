<?php

use App\Imports\StudentImport;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use WireUi\Traits\WireUiActions;
use App\Livewire\Concerns\HasClassRoomRole;

new class extends Component
{
    use WithFileUploads;
    use WireUiActions;
    use HasClassRoomRole;

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
    public bool $showImportModal = false;

    // ──────────────────────────────────────────
    // Form state
    // ──────────────────────────────────────────

    public ?int $editingId = null;

    #[Rule('required|string|max:100')]
    public string $first_name = '';

    #[Rule('required|string|max:100')]
    public string $last_name = '';

    #[Rule('required|in:male,female,other')]
    public string $gender = '';

    #[Rule('nullable|date')]
    public string $date_of_birth = '';

    // ──────────────────────────────────────────
    // Delete / detail state
    // ──────────────────────────────────────────

    public ?int     $deletingId = null;
    public ?Student $viewing    = null;

    // ──────────────────────────────────────────
    // Import state
    // ──────────────────────────────────────────

    #[Rule('required|file|mimes:xlsx,xls,csv|max:10240')]
    public $importFile = null;

    public ?string $importSummary = null;

    // ──────────────────────────────────────────
    // Filters
    // ──────────────────────────────────────────

    public string $search       = '';
    public string $filterGender = '';

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function mount(int $classId): void
    {
        // Verify teacher owns this class
        $class = ClassRoom::forTeacher(Auth::id())->findOrFail($classId);
        $this->classId = $classId;
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
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('first_name', 'like', "%{$this->search}%")
                      ->orWhere('last_name',  'like', "%{$this->search}%")
                ))
            ->when($this->filterGender, fn ($q) =>
                $q->where('gender', $this->filterGender)
            )
            ->alphabetical()
            ->get();
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
        $student = $this->findOwned($id);

        $this->editingId      = $student->id;
        $this->first_name     = $student->first_name;
        $this->last_name      = $student->last_name;
        $this->gender         = $student->gender;
        $this->date_of_birth  = $student->date_of_birth?->format('Y-m-d') ?? '';

        $this->showFormModal = true;
    }

    // ──────────────────────────────────────────
    // Save
    // ──────────────────────────────────────────

    public function save(): void
    {
        $this->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'gender'        => 'required|in:male,female,other',
            'date_of_birth' => 'nullable|date',
        ]);

        $data = [
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'gender'        => $this->gender,
            'date_of_birth' => $this->date_of_birth ?: null,
        ];

        if ($this->editingId) {
            $this->findOwned($this->editingId)->update($data);
            $message = "{$this->first_name} {$this->last_name} updated.";
        } else {
            Student::create(array_merge($data, [
                'class_id' => $this->classId,
                'user_id'  => Auth::id(),
            ]));
            $message = "{$this->first_name} {$this->last_name} added.";
        }

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->students);

        $this->notification()->success(title: 'Saved!', description: $message);
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
            $student = $this->findOwned($this->deletingId);
            $name    = $student->full_name;
            $student->delete();
            unset($this->students);

            $this->notification()->warning(title: 'Removed', description: "{$name} has been removed.");
        }

        $this->showDeleteModal = false;
        $this->deletingId      = null;
    }

    // ──────────────────────────────────────────
    // Detail
    // ──────────────────────────────────────────

    public function openDetailModal(int $id): void
    {
        $this->viewing         = $this->findOwned($id);
        $this->showDetailModal = true;
    }

    // ──────────────────────────────────────────
    // Import
    // ──────────────────────────────────────────

    public function openImportModal(): void
    {
        $this->importFile    = null;
        $this->importSummary = null;
        $this->resetValidation('importFile');
        $this->showImportModal = true;
    }

    public function import(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        $importer = new StudentImport($this->classId, Auth::id());

        Excel::import($importer, $this->importFile->getRealPath());

        $failures = $importer->failures();

        $this->importSummary = "Imported: {$importer->importedCount} · Skipped duplicates: {$importer->skippedCount} · Validation errors: " . count($failures);

        $this->importFile = null;
        unset($this->students);

        if ($importer->importedCount > 0) {
            $this->notification()->success(
                title:       'Import complete',
                description: "{$importer->importedCount} students imported successfully.",
            );
        } else {
            $this->notification()->warning(
                title:       'Nothing imported',
                description: 'No new students were added. Check the summary for details.',
            );
        }
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    private function findOwned(int $id): Student
    {
        return Student::forTeacher(Auth::id())
            ->inClass($this->classId)
            ->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId     = null;
        $this->first_name    = '';
        $this->last_name     = '';
        $this->gender        = '';
        $this->date_of_birth = '';
        $this->resetValidation();
    }
};
?>

{{-- resources/views/livewire/student-manager.blade.php --}}
<div class="min-h-screen bg-slate-50 font-sans">

    {{-- ══════════════════════════════════════════════════════════
         PAGE HEADER
    ══════════════════════════════════════════════════════════ --}}
    <div class="bg-white border-b border-slate-200 px-6 py-5">
        <div class=" mx-auto">

            {{-- Breadcrumb --}}
            <p class="text-xs text-slate-400 mb-1 uppercase tracking-wide font-medium">
                {{ $this->classroom->name }} &middot; {{ $this->classroom->subject }} &middot; {{ $this->classroom->academic_year }}
            </p>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Students</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        {{ $this->students->count() }} {{ Str::plural('student', $this->students->count()) }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <x-button
                        wire:click="openImportModal"
                        icon="arrow-up-tray"
                        label="Import Excel"
                        outline
                        class="w-full sm:w-auto"
                    />
                    <x-button
                        wire:click="openCreateModal"
                        icon="plus"
                        label="Add Student"
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
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <x-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by name…"
                        icon="magnifying-glass"
                        shadowless
                    />
                </div>
                <div>
                    <x-native-select wire:model.live="filterGender" shadowless>
                        <option value="">All genders</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </x-native-select>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             STUDENTS TABLE
        ══════════════════════════════════════════════════════════ --}}
        @if ($this->students->isEmpty())
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                <x-icon name="user-group" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-500 font-medium">No students found.</p>
                <p class="text-slate-400 text-sm mt-1">Add students manually or import from Excel.</p>
                <div class="flex items-center justify-center gap-3 mt-5">
                    <x-button wire:click="openImportModal" label="Import Excel" outline />
                    <x-button wire:click="openCreateModal" label="Add Student" primary />
                </div>
            </div>
        @else
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-left">
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs">Name</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden sm:table-cell">Gender</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden md:table-cell">Date of Birth</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs hidden md:table-cell">Age</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 uppercase tracking-wide text-xs text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->students as $student)
                            <tr wire:key="student-{{ $student->id }}" class="hover:bg-slate-50 transition-colors">

                                {{-- Avatar + name --}}
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div @class([
                                            'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                            'bg-blue-100 text-blue-700'   => $student->gender === 'male',
                                            'bg-pink-100 text-pink-700'   => $student->gender === 'female',
                                            'bg-violet-100 text-violet-700' => $student->gender === 'other',
                                        ])>
                                            {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800">{{ $student->full_name }}</p>
                                            <p class="text-xs text-slate-400">{{ $student->register_name }}</p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Gender --}}
                                <td class="px-5 py-3 hidden sm:table-cell">
                                    <span @class([
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-blue-50 text-blue-700'     => $student->gender === 'male',
                                        'bg-pink-50 text-pink-700'     => $student->gender === 'female',
                                        'bg-violet-50 text-violet-700' => $student->gender === 'other',
                                    ])>
                                        {{ ucfirst($student->gender) }}
                                    </span>
                                </td>

                                {{-- DOB --}}
                                <td class="px-5 py-3 text-slate-500 hidden md:table-cell">
                                    {{ $student->date_of_birth?->format('d M Y') ?? '—' }}
                                </td>

                                {{-- Age --}}
                                <td class="px-5 py-3 text-slate-500 hidden md:table-cell">
                                    {{ $student->age ? $student->age . ' yrs' : '—' }}
                                </td>

                                {{-- Actions --}}
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button
                                            wire:click="openDetailModal({{ $student->id }})"
                                            icon="eye"
                                            flat xs
                                            class="text-slate-500"
                                        />
                                        <x-button
                                            wire:click="openEditModal({{ $student->id }})"
                                            icon="pencil"
                                            flat xs
                                            class="text-indigo-600"
                                        />
                                        <x-button
                                            wire:click="confirmDelete({{ $student->id }})"
                                            icon="trash"
                                            flat xs
                                            class="text-red-500"
                                        />
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>{{-- /max-w-7xl --}}


    {{-- ══════════════════════════════════════════════════════════
         CREATE / EDIT MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal
        wire:model.live="showFormModal"
        :title="$editingId ? 'Edit Student' : 'Add Student'"
        blur
        persistent
        width="xl"
    >
        <x-card class="relative">
            <div class="space-y-4 p-1">

            <div class="grid grid-cols-2 gap-4">
                <x-input
                    wire:model="first_name"
                    label="First Name"
                    placeholder="e.g. Mwila"
                    :error="$errors->first('first_name')"
                />
                <x-input
                    wire:model="last_name"
                    label="Last Name"
                    placeholder="e.g. Banda"
                    :error="$errors->first('last_name')"
                />
            </div>

            <x-select
                :options="[
                    ['label'=>'Male','value'=>'male'],
                    ['label'=>'Female','value'=>'female']
                ]"
                option-label="label"
                option-value="value"
                label="Gender"
                wire:model='gender'
            />

            <x-datetime-picker
                wire:model="date_of_birth"
                label="Date of Birth"
                :without-time="true"
                type="date"
                :error="$errors->first('date_of_birth')"
            />

        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button wire:click="$set('showFormModal', false)" label="Cancel" flat />
                <x-button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    :label="$editingId ? 'Update' : 'Add Student'"
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
    <x-modal wire:model.live="showDeleteModal" title="Remove Student" blur width="lg">
        <x-card class="relative">
            <div class="flex items-start gap-4 p-1">
            <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-red-50">
                <x-icon name="exclamation-triangle" class="w-6 h-6 text-red-500" />
            </div>
            <div>
                <p class="text-slate-700 font-medium">Remove this student from the class?</p>
                <p class="text-slate-500 text-sm mt-1">
                    The student record will be soft-deleted. Attendance and assessment records are preserved.
                </p>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button wire:click="$set('showDeleteModal', false)" label="Cancel" flat />
                <x-button
                    wire:click="delete"
                    wire:loading.attr="disabled"
                    wire:target="delete"
                    label="Yes, Remove"
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
    <x-modal wire:model.live="showDetailModal" :title="$viewing->full_name" blur width="xl">
       <x-card class="relative">
             <div class="space-y-4 p-1">

            <div class="flex items-center gap-4">
                {{-- Avatar --}}
                <div @class([
                    'w-16 h-16 rounded-2xl flex items-center justify-center text-xl font-bold shrink-0',
                    'bg-blue-100 text-blue-700'     => $viewing->gender === 'male',
                    'bg-pink-100 text-pink-700'     => $viewing->gender === 'female',
                    'bg-violet-100 text-violet-700' => $viewing->gender === 'other',
                ])>
                    {{ strtoupper(substr($viewing->first_name, 0, 1)) }}{{ strtoupper(substr($viewing->last_name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-xl font-bold text-slate-800">{{ $viewing->full_name }}</p>
                    <p class="text-sm text-slate-400">{{ $viewing->register_name }}</p>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm border-t border-slate-100 pt-4">
                <div>
                    <dt class="text-slate-400 text-xs uppercase tracking-wide font-medium">Gender</dt>
                    <dd class="mt-1 text-slate-700 font-semibold">{{ ucfirst($viewing->gender) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400 text-xs uppercase tracking-wide font-medium">Age</dt>
                    <dd class="mt-1 text-slate-700 font-semibold">{{ $viewing->age ? $viewing->age . ' years old' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400 text-xs uppercase tracking-wide font-medium">Date of Birth</dt>
                    <dd class="mt-1 text-slate-700 font-semibold">{{ $viewing->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400 text-xs uppercase tracking-wide font-medium">Class</dt>
                    <dd class="mt-1 text-slate-700 font-semibold">{{ $this->classroom->name }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400 text-xs uppercase tracking-wide font-medium">Added</dt>
                    <dd class="mt-1 text-slate-700 font-semibold">{{ $viewing->created_at->format('d M Y') }}</dd>
                </div>
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
         IMPORT MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal
        wire:model.live="showImportModal"
        title="Import Students from Excel"
        blur
        persistent
        width="xl"
    >
        <x-card class="relative">
            <div class="space-y-5 p-1">

            {{-- Instructions --}}
            <div class="bg-slate-50 rounded-lg border border-slate-200 p-4 text-sm text-slate-600 space-y-1">
                <p class="font-semibold text-slate-700 mb-2">Spreadsheet format</p>
                <p>Your file must have a <strong>heading row</strong> with these columns:</p>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full text-xs border border-slate-200 rounded text-left">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="px-3 py-1.5 font-semibold text-slate-600">first_name</th>
                                <th class="px-3 py-1.5 font-semibold text-slate-600">last_name</th>
                                <th class="px-3 py-1.5 font-semibold text-slate-600">gender</th>
                                <th class="px-3 py-1.5 font-semibold text-slate-600">date_of_birth</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="bg-white">
                                <td class="px-3 py-1.5 text-slate-500">Mwila</td>
                                <td class="px-3 py-1.5 text-slate-500">Banda</td>
                                <td class="px-3 py-1.5 text-slate-500">male</td>
                                <td class="px-3 py-1.5 text-slate-500">2010-03-15</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="list-disc list-inside text-xs text-slate-500 mt-2 space-y-0.5">
                    <li>Gender must be: <code>male</code>, <code>female</code>, or <code>other</code></li>
                    <li>Date formats accepted: YYYY-MM-DD, DD/MM/YYYY</li>
                    <li>Duplicate students (same name in this class) are skipped</li>
                    <li>Accepted file types: .xlsx, .xls, .csv (max 10 MB)</li>
                </ul>
            </div>

            {{-- File upload --}}
            <x-input
                type="file"
                wire:model="importFile"
                label="Choose file"
                accept=".xlsx,.xls,.csv"
                :error="$errors->first('importFile')"
            />

            {{-- Import summary (shown after import) --}}
            @if ($importSummary)
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 text-sm text-indigo-800">
                    <p class="font-semibold mb-0.5">Import summary</p>
                    <p>{{ $importSummary }}</p>
                </div>
            @endif

        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-button wire:click="$set('showImportModal', false)" label="Close" flat />
                <x-button
                    wire:click="import"
                    wire:loading.attr="disabled"
                    wire:target="import"
                    icon="arrow-up-tray"
                    label="Import Now"
                    primary
                    spinner="import"
                />
            </div>
        </x-slot>
        </x-card>
    </x-modal>

</div>
