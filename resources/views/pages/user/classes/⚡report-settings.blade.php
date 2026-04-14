<?php

use App\Models\ClassRoom;
use App\Models\ReportCardSetting;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
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
    // Settings
    // ──────────────────────────────────────────

    public string  $settingSchoolName       = 'Student Report Card';
    public string  $settingSchoolMotto      = '';
    public string  $settingAccentColor      = '#4f46e5';
    public ?string $settingLogoPath         = null;
    public mixed   $settingLogo             = null;
    public bool    $settingShowAttendance   = true;
    public bool    $settingShowConduct      = true;
    public bool    $settingShowGradingScale = true;
    public bool    $settingShowSignatures   = true;
    public string  $settingFooterNote       = '';
    public string  $settingLayout           = 'modern';

    // ──────────────────────────────────────────
    // Preview controls
    // ──────────────────────────────────────────

    public ?int $previewStudentId = null;
    public int  $previewTerm      = 1;
    public int  $previewYear;
    public int  $previewTimestamp = 0;

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function mount(int $classId): void
    {
        $class = ClassRoom::forTeacher(Auth::id())->findOrFail($classId);
        $this->classId     = $classId;
        $this->previewYear = (int) date('Y');
        $this->resolveRole($class);
        $this->loadSettings();

        // Default preview to first student
        $this->previewStudentId = $this->students->first()?->id;
        $this->previewTimestamp = time();
    }

    private function loadSettings(): void
    {
        $s = ReportCardSetting::forTeacherAndClass(Auth::id(), $this->classId);

        $this->settingSchoolName       = $s->school_name       ?? 'Student Report Card';
        $this->settingSchoolMotto      = $s->school_motto      ?? '';
        $this->settingAccentColor      = $s->accent_color      ?? '#4f46e5';
        $this->settingLogoPath         = $s->school_logo       ?? null;
        $this->settingShowAttendance   = (bool) ($s->show_attendance    ?? true);
        $this->settingShowConduct      = (bool) ($s->show_conduct       ?? true);
        $this->settingShowGradingScale = (bool) ($s->show_grading_scale ?? true);
        $this->settingShowSignatures   = (bool) ($s->show_signatures    ?? true);
        $this->settingFooterNote       = $s->footer_note ?? '';
        $this->settingLayout           = $s->layout       ?? 'modern';
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
    public function previewSrc(): string
    {
        if (! $this->previewStudentId) {
            return '';
        }

        return route('user.report-preview', [$this->classId, $this->previewStudentId])
            . '?' . http_build_query([
                'term' => $this->previewTerm,
                'year' => $this->previewYear,
                'ts'   => $this->previewTimestamp,
            ]);
    }

    // ──────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────

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
            'settingLayout'           => 'required|in:modern,classic,compact',
        ]);

        $logoPath = $this->settingLogoPath;

        if ($this->settingLogo) {
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath              = $this->settingLogo->store('logos', 'public');
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
                'layout'             => $this->settingLayout,
            ]
        );

        // Bump the timestamp so the preview iframe reloads with the new settings
        $this->previewTimestamp = time();

        $this->notification()->success(
            title:       'Settings saved!',
            description: 'Preview updated. Regenerate PDFs to apply to existing reports.',
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

        $this->previewTimestamp = time();
        $this->notification()->success(title: 'Logo removed', description: 'Report card logo cleared.');
    }
};
?>

<div class="min-h-screen bg-slate-100 font-sans flex flex-col">
<x-notifications/>

    {{-- ══════════════════════════════════════════════════════════
         TOP BAR
    ══════════════════════════════════════════════════════════ --}}
    <div class="bg-white border-b border-slate-200 px-6 py-4 shrink-0">
        <div class="flex items-center justify-between gap-4">

            <div class="flex items-center gap-4 min-w-0">
                <a href="{{ route('user.reports', $classId) }}"
                   class="inline-flex items-center gap-1.5 text-slate-500 hover:text-slate-800 transition-colors text-sm font-medium shrink-0">
                    <x-icon name="arrow-left" class="w-4 h-4" />
                    Back to Report Cards
                </a>
                <div class="h-5 w-px bg-slate-200 shrink-0"></div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-medium truncate">
                        {{ $this->classroom->name }} &middot; Customise
                    </p>
                    <h1 class="text-lg font-bold text-slate-800 leading-tight">Report Card Designer</h1>
                </div>
            </div>

            <x-button
                wire:click="saveSettings"
                wire:loading.attr="disabled"
                wire:target="saveSettings"
                icon="check"
                label="Save Settings"
                primary
                spinner="saveSettings"
                class="shrink-0"
            />
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         TWO-PANEL BODY
    ══════════════════════════════════════════════════════════ --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- ── LEFT PANEL: Settings ── --}}
        <div class="w-[400px] shrink-0 bg-white border-r border-slate-200 overflow-y-auto">
            <div class="p-6 space-y-6">

                {{-- Layout picker --}}
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Layout</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ([
                            'modern'  => ['Modern',  'Progress bars & type breakdown',   'chart-bar'],
                            'classic' => ['Classic', 'Traditional table, one row/subject','table-cells'],
                            'compact' => ['Compact', 'Minimal — subject & grade only',   'list-bullet'],
                        ] as $layout => [$title, $desc, $icon])
                        <button
                            wire:click="$set('settingLayout', '{{ $layout }}')"
                            type="button"
                            class="flex flex-col items-start gap-1 p-2.5 rounded-lg border-2 text-left transition-all {{ $settingLayout === $layout ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-slate-300 bg-slate-50' }}"
                        >
                            <div class="flex items-center gap-1.5 w-full">
                                <x-icon name="{{ $icon }}" class="w-3.5 h-3.5 shrink-0 {{ $settingLayout === $layout ? 'text-indigo-600' : 'text-slate-400' }}" />
                                <span class="text-xs font-bold {{ $settingLayout === $layout ? 'text-indigo-700' : 'text-slate-700' }} truncate">{{ $title }}</span>
                                @if ($settingLayout === $layout)
                                    <x-icon name="check-circle" class="w-3 h-3 text-indigo-500 ml-auto shrink-0" />
                                @endif
                            </div>
                            <p class="text-xs leading-snug {{ $settingLayout === $layout ? 'text-indigo-500' : 'text-slate-400' }}">{{ $desc }}</p>
                        </button>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                {{-- School identity --}}
                <div class="space-y-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">School Identity</p>

                    <x-input
                        wire:model="settingSchoolName"
                        label="School / Report Title"
                        placeholder="e.g. Sunrise Academy"
                        :error="$errors->first('settingSchoolName')"
                    />

                    <x-input
                        wire:model="settingSchoolMotto"
                        label="Motto (optional)"
                        placeholder="e.g. Excellence Through Knowledge"
                        :error="$errors->first('settingSchoolMotto')"
                    />

                    {{-- Logo --}}
                    <div>
                        <p class="text-xs font-semibold text-slate-600 mb-1">Logo (optional)</p>
                        @if ($settingLogoPath)
                            <div class="flex items-center gap-3 mb-2">
                                <img src="{{ Storage::disk('public')->url($settingLogoPath) }}"
                                     alt="Logo"
                                     class="h-12 w-auto rounded border border-slate-200 bg-slate-50 object-contain p-1" />
                                <button wire:click="removeLogo" type="button"
                                        class="text-xs text-red-400 hover:underline">
                                    Remove
                                </button>
                            </div>
                        @endif
                        <label class="flex items-center gap-2 cursor-pointer">
                            <div class="flex-1 border border-dashed border-slate-300 rounded-lg px-4 py-3 text-center hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                                @if ($settingLogo)
                                    <p class="text-xs text-indigo-600 font-medium">{{ $settingLogo->getClientOriginalName() }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Click to change</p>
                                @else
                                    <x-icon name="photo" class="w-4 h-4 text-slate-400 mx-auto mb-1" />
                                    <p class="text-xs text-slate-500">PNG / JPG / SVG · max 2 MB</p>
                                @endif
                            </div>
                            <input type="file" wire:model="settingLogo" accept="image/*" class="sr-only" />
                        </label>
                        @error('settingLogo') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                {{-- Accent colour --}}
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Accent Colour</p>
                    <div class="flex items-center gap-3 flex-wrap">
                        <input
                            type="color"
                            wire:model.live="settingAccentColor"
                            class="w-10 h-10 rounded cursor-pointer border border-slate-200 shrink-0"
                        />
                        <x-input
                            wire:model.live.debounce.300ms="settingAccentColor"
                            placeholder="#4f46e5"
                            class="font-mono w-28"
                            :error="$errors->first('settingAccentColor')"
                        />
                        <div class="flex gap-1.5 flex-wrap">
                            @foreach (['#4f46e5','#059669','#0284c7','#dc2626','#d97706','#7c3aed','#0f172a'] as $preset)
                            <button
                                wire:click="$set('settingAccentColor', '{{ $preset }}')"
                                type="button"
                                class="w-6 h-6 rounded-full border-2 {{ $settingAccentColor === $preset ? 'border-slate-800 scale-110' : 'border-transparent' }} transition-all"
                                style="background:{{ $preset }}"
                            ></button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                {{-- Sections to include --}}
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Sections to Include</p>
                    <div class="space-y-2.5">
                        @foreach ([
                            ['settingShowAttendance',   'Attendance Summary'],
                            ['settingShowConduct',      'Conduct Grade'],
                            ['settingShowGradingScale', 'ECZ Grading Scale'],
                            ['settingShowSignatures',   'Signature Lines'],
                        ] as [$prop, $label])
                        <label class="flex items-center gap-2.5 cursor-pointer select-none">
                            <input type="checkbox" wire:model="{{ $prop }}" class="rounded text-indigo-600" />
                            <span class="text-sm text-slate-700">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                {{-- Footer note --}}
                <x-input
                    wire:model="settingFooterNote"
                    label="Footer Note (optional)"
                    placeholder="e.g. This report is computer generated."
                    :error="$errors->first('settingFooterNote')"
                />

                {{-- Save --}}
                <x-button
                    wire:click="saveSettings"
                    wire:loading.attr="disabled"
                    wire:target="saveSettings"
                    icon="check"
                    label="Save Settings"
                    primary
                    spinner="saveSettings"
                    class="w-full"
                />

            </div>
        </div>

        {{-- ── RIGHT PANEL: Live Preview ── --}}
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- Preview controls bar --}}
            <div class="bg-white border-b border-slate-200 px-5 py-3 flex items-center gap-4 shrink-0">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide shrink-0">Preview</p>

                @if ($this->students->isNotEmpty())
                <div class="flex items-center gap-3 flex-1 flex-wrap">
                    <select
                        wire:model.live="previewStudentId"
                        class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    >
                        @foreach ($this->students as $student)
                            <option value="{{ $student->id }}">{{ $student->full_name }}</option>
                        @endforeach
                    </select>

                    <select
                        wire:model.live="previewTerm"
                        class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    >
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                    </select>

                    <input
                        type="number"
                        wire:model.live.debounce.500ms="previewYear"
                        class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 text-slate-700 bg-white w-24 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        placeholder="Year"
                    />
                </div>

                <span class="text-xs text-slate-400 shrink-0">Auto-updates on save</span>
                @endif
            </div>

            {{-- Iframe area --}}
            <div class="flex-1 overflow-auto bg-slate-200 p-6">
                @if ($this->students->isEmpty())
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <x-icon name="users" class="w-12 h-12 text-slate-400 mx-auto mb-3" />
                            <p class="text-slate-500 font-medium">No students in this class yet.</p>
                            <p class="text-sm text-slate-400 mt-1">Add students to see a live preview.</p>
                        </div>
                    </div>
                @elseif (! $this->previewSrc)
                    <div class="flex items-center justify-center h-full">
                        <p class="text-slate-400 text-sm">Select a student above to preview.</p>
                    </div>
                @else
                    {{-- Paper-style iframe wrapper --}}
                    <div class="mx-auto" style="width: 794px;">
                        <div class="rounded shadow-2xl overflow-hidden" style="height: calc(100vh - 160px);">
                            <iframe
                                src="{{ $this->previewSrc }}"
                                style="width: 100%; height: 100%; border: none; display: block;"
                                loading="lazy"
                            ></iframe>
                        </div>
                        <p class="text-center text-xs text-slate-400 mt-3">
                            This is an exact preview of the PDF output &mdash; save settings to refresh.
                        </p>
                    </div>
                @endif
            </div>

        </div>

    </div>

</div>
