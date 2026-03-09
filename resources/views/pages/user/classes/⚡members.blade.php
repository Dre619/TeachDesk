<?php

use App\Actions\InviteMemberAction;
use App\Models\ClassRoom;
use App\Models\ClassRoomMember;
use App\Models\User;
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

    public bool $showInviteModal  = false;
    public bool $showRemoveModal  = false;

    // ──────────────────────────────────────────
    // Invite form state
    // ──────────────────────────────────────────

    #[Rule('required|email|max:255')]
    public string $inviteEmail   = '';

    #[Rule('required|string|max:100')]
    public string $inviteSubject = '';

    public bool $sending = false;

    // ──────────────────────────────────────────
    // Remove state
    // ──────────────────────────────────────────

    public ?int $removingMemberId = null;

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function mount(int $classId): void
    {
        // Only the class owner (form teacher) can manage members
       $class = ClassRoom::where('user_id', Auth::id())->findOrFail($classId);
        $this->classId = $classId;
        $this->resolveRole($class);
    }

    // ──────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────

    #[Computed]
    public function classroom(): ClassRoom
    {
        return ClassRoom::where('user_id', Auth::id())->findOrFail($this->classId);
    }

    #[Computed]
    public function members()
    {
        return ClassRoomMember::with(['teacher', 'invitedBy'])
            ->where('class_id', $this->classId)
            ->orderByRaw("FIELD(status, 'accepted', 'pending', 'declined')")
            ->orderBy('subject')
            ->get();
    }

    #[Computed]
    public function acceptedCount(): int
    {
        return $this->members->where('status', 'accepted')->count();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->members->where('status', 'pending')->count();
    }

    // ──────────────────────────────────────────
    // Invite
    // ──────────────────────────────────────────

    public function openInviteModal(): void
    {
        $this->inviteEmail   = '';
        $this->inviteSubject = '';
        $this->resetValidation();
        $this->showInviteModal = true;
    }

    public function sendInvite(): void
    {
        $this->validate();

        // Prevent inviting yourself
        if (strtolower($this->inviteEmail) === strtolower(Auth::user()->email)) {
            $this->addError('inviteEmail', 'You cannot invite yourself.');
            return;
        }

        // Prevent duplicate pending/accepted invite for same subject
        $exists = ClassRoomMember::where('class_id', $this->classId)
            ->where('subject', $this->inviteSubject)
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        if ($exists) {
            $this->addError('inviteSubject', 'A teacher for this subject has already been invited or accepted.');
            return;
        }

        $this->sending = true;

        try {
            $sentTo = $this->inviteEmail;

            app(InviteMemberAction::class)->execute(
                classroom: $this->classroom,
                email:     $this->inviteEmail,
                subject:   $this->inviteSubject,
                invitedBy: Auth::user(),
            );

            $this->showInviteModal = false;
            $this->inviteEmail     = '';
            $this->inviteSubject   = '';
            unset($this->members, $this->pendingCount, $this->acceptedCount);

            $this->notification()->success(
                title:       'Invitation sent!',
                description: "An email and in-app notification has been sent to {$sentTo}.",
            );
        } catch (\Throwable $e) {
            $this->notification()->error(
                title:       'Failed to send invite',
                description: $e->getMessage(),
            );
        }

        $this->sending = false;
    }

    // ──────────────────────────────────────────
    // Resend invite
    // ──────────────────────────────────────────

    public function resendInvite(int $memberId): void
    {
        $member = ClassRoomMember::where('class_id', $this->classId)
            ->where('status', 'pending')
            ->findOrFail($memberId);

        // Regenerate token
        $member->update(['invite_token' => ClassRoomMember::generateToken()]);

        $member->teacher->notify(new \App\Notifications\ClassInviteNotification($member->fresh()));

        $this->notification()->success(
            title:       'Invite resent',
            description: "A fresh invitation has been sent to {$member->teacher->email}.",
        );
    }

    // ──────────────────────────────────────────
    // Remove member
    // ──────────────────────────────────────────

    public function confirmRemove(int $memberId): void
    {
        $this->removingMemberId = $memberId;
        $this->showRemoveModal  = true;
    }

    public function removeMember(): void
    {
        if ($this->removingMemberId) {
            $member = ClassRoomMember::where('class_id', $this->classId)
                ->findOrFail($this->removingMemberId);

            $name    = $member->teacher->name ?? $member->teacher->email;
            $subject = $member->subject;

            $member->delete();

            unset($this->members, $this->pendingCount, $this->acceptedCount);

            $this->notification()->warning(
                title:       'Member removed',
                description: "{$name} has been removed from {$subject}.",
            );
        }

        $this->showRemoveModal  = false;
        $this->removingMemberId = null;
    }
};
?>

{{-- resources/views/livewire/members-manager.blade.php --}}
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
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Subject Teachers</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        <span class="text-emerald-600 font-medium">{{ $this->acceptedCount }} active</span>
                        &middot;
                        @if ($this->pendingCount > 0)
                            <span class="text-amber-500 font-medium">{{ $this->pendingCount }} pending</span>
                        @else
                            <span>0 pending</span>
                        @endif
                    </p>
                </div>
                <x-button
                    wire:click="openInviteModal"
                    icon="envelope"
                    label="Invite Subject Teacher"
                    primary
                    class="w-full sm:w-auto"
                />
            </div>
        </div>
    </div>

    <div class="mx-auto px-6 py-8 space-y-6">

        {{-- ══════════════════════════════════════════════════════════
             FORM TEACHER CARD (you)
        ══════════════════════════════════════════════════════════ --}}
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl px-5 py-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-indigo-200 text-indigo-800 flex items-center justify-center text-sm font-bold shrink-0">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-indigo-900">{{ Auth::user()->name }}</p>
                <p class="text-xs text-indigo-500">{{ Auth::user()->email }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700 border border-indigo-300">
                    <x-icon name="academic-cap" class="w-3.5 h-3.5" />
                    Form Teacher
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-white text-indigo-600 border border-indigo-200">
                    {{ $this->classroom->subject }}
                </span>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             EMPTY STATE
        ══════════════════════════════════════════════════════════ --}}
        @if ($this->members->isEmpty())
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-20 text-center">
                <x-icon name="user-plus" class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-500 font-medium">No subject teachers yet.</p>
                <p class="text-slate-400 text-sm mt-1 max-w-sm mx-auto">
                    Invite colleagues to enter marks for their subjects.
                    They'll only see their own subject's data.
                </p>
                <x-button wire:click="openInviteModal" label="Invite First Teacher" primary class="mt-5" />
            </div>

        {{-- ══════════════════════════════════════════════════════════
             MEMBERS LIST
        ══════════════════════════════════════════════════════════ --}}
        @else
            <div class="space-y-3">
                @foreach ($this->members as $member)
                @php
                    $statusConfig = match($member->status) {
                        'accepted' => [
                            'badge'  => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            'dot'    => 'bg-emerald-500',
                            'label'  => 'Active',
                            'card'   => 'border-slate-200',
                        ],
                        'pending' => [
                            'badge'  => 'bg-amber-100 text-amber-700 border-amber-200',
                            'dot'    => 'bg-amber-400',
                            'label'  => 'Invite Pending',
                            'card'   => 'border-amber-200',
                        ],
                        'declined' => [
                            'badge'  => 'bg-red-100 text-red-600 border-red-200',
                            'dot'    => 'bg-red-400',
                            'label'  => 'Declined',
                            'card'   => 'border-red-200',
                        ],
                        default => [
                            'badge'  => 'bg-slate-100 text-slate-500 border-slate-200',
                            'dot'    => 'bg-slate-400',
                            'label'  => ucfirst($member->status),
                            'card'   => 'border-slate-200',
                        ],
                    };
                @endphp
                <div
                    wire:key="member-{{ $member->id }}"
                    class="bg-white rounded-xl border {{ $statusConfig['card'] }} shadow-sm px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4 hover:shadow-md transition-shadow"
                >
                    {{-- Avatar --}}
                    <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-sm font-bold shrink-0">
                        {{ strtoupper(substr($member->teacher->name ?? $member->teacher->email, 0, 1)) }}
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-semibold text-slate-800">
                                {{ $member->teacher->name ?? $member->teacher->email }}
                            </p>
                            {{-- Status badge --}}
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold border {{ $statusConfig['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                                {{ $statusConfig['label'] }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $member->teacher->email }}</p>
                        <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                            {{-- Subject pill --}}
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                <x-icon name="book-open" class="w-3 h-3" />
                                {{ $member->subject }}
                            </span>
                            {{-- Invited by / accepted at --}}
                            @if ($member->status === 'accepted' && $member->accepted_at)
                                <span class="text-xs text-slate-400">
                                    Joined {{ $member->accepted_at->format('d M Y') }}
                                </span>
                            @elseif ($member->status === 'pending')
                                <span class="text-xs text-slate-400">
                                    Invited {{ $member->created_at->diffForHumans() }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($member->status === 'pending')
                            <x-button
                                wire:click="resendInvite({{ $member->id }})"
                                wire:loading.attr="disabled"
                                wire:target="resendInvite({{ $member->id }})"
                                icon="arrow-path"
                                label="Resend"
                                flat xs
                                class="text-amber-600"
                                spinner="resendInvite({{ $member->id }})"
                            />
                        @endif
                        <x-button
                            wire:click="confirmRemove({{ $member->id }})"
                            icon="trash"
                            label="Remove"
                            flat xs
                            class="text-red-500"
                        />
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Subject coverage summary --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Subject Coverage</p>
                <div class="flex flex-wrap gap-2">
                    {{-- Form teacher's own subject --}}
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-100 text-indigo-700 border border-indigo-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                        {{ $this->classroom->subject }}
                        <span class="text-indigo-400 font-normal">(you)</span>
                    </span>
                    {{-- Accepted subject teachers --}}
                    @foreach ($this->members->where('status', 'accepted') as $m)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            {{ $m->subject }}
                        </span>
                    @endforeach
                    {{-- Pending subjects --}}
                    @foreach ($this->members->where('status', 'pending') as $m)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-600 border border-amber-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            {{ $m->subject }}
                            <span class="text-amber-400 font-normal">(pending)</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

    </div>{{-- /container --}}


    {{-- ══════════════════════════════════════════════════════════
         INVITE MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showInviteModal" title="Invite Subject Teacher" blur persistent width="xl">
        <x-card class="relative">
            <div class="space-y-5 p-1">

                {{-- Info banner --}}
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 flex items-start gap-3">
                    <x-icon name="information-circle" class="w-5 h-5 text-indigo-500 shrink-0 mt-0.5" />
                    <p class="text-sm text-indigo-700 leading-relaxed">
                        The teacher will receive an <strong>email</strong> and an
                        <strong>in-app notification</strong> with a link to accept.
                        They will only see the assessment tab for their assigned subject.
                    </p>
                </div>

                <x-input
                    wire:model="inviteEmail"
                    label="Teacher's Email Address"
                    placeholder="colleague@school.edu"
                    type="email"
                    icon="envelope"
                    :error="$errors->first('inviteEmail')"
                />

                <div>
                    <x-input
                        wire:model="inviteSubject"
                        label="Subject They Teach"
                        placeholder="e.g. Mathematics, English Language, Science…"
                        :error="$errors->first('inviteSubject')"
                    />
                    {{-- Quick-pick chips --}}
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach (['Mathematics','English','Science','Social Studies','Civic Education','Religious Education','Physical Education','Home Economics','Art','ICT'] as $subj)
                            <button
                                wire:click="$set('inviteSubject', '{{ $subj }}')"
                                type="button"
                                class="px-2.5 py-1 rounded-full text-xs font-medium border border-slate-200 bg-slate-50 text-slate-600 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 transition-colors"
                            >{{ $subj }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Preview --}}
                @if ($inviteEmail && $inviteSubject)
                    <div class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm text-slate-600">
                        <p class="font-medium text-slate-700 mb-1">Invite preview</p>
                        <p>
                            <span class="font-semibold text-indigo-700">{{ $inviteEmail }}</span>
                            will be invited to enter marks for
                            <span class="font-semibold text-indigo-700">{{ $inviteSubject }}</span>
                            in <span class="font-semibold">{{ $this->classroom->name }}</span>.
                        </p>
                    </div>
                @endif

            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('showInviteModal', false)" label="Cancel" flat />
                    <x-button
                        wire:click="sendInvite"
                        wire:loading.attr="disabled"
                        wire:target="sendInvite"
                        icon="paper-airplane"
                        label="Send Invitation"
                        primary
                        spinner="sendInvite"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════
         REMOVE CONFIRMATION MODAL
    ══════════════════════════════════════════════════════════ --}}
    <x-modal wire:model.live="showRemoveModal" title="Remove Teacher" blur width="lg">
        <x-card class="relative">
            <div class="flex items-start gap-4 p-1">
                <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-red-50">
                    <x-icon name="exclamation-triangle" class="w-6 h-6 text-red-500" />
                </div>
                <div>
                    <p class="text-slate-700 font-medium">Remove this teacher from the class?</p>
                    <p class="text-slate-500 text-sm mt-1">
                        Their assessment records will be preserved, but they will
                        lose access to this class immediately.
                    </p>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('showRemoveModal', false)" label="Cancel" flat />
                    <x-button
                        wire:click="removeMember"
                        wire:loading.attr="disabled"
                        wire:target="removeMember"
                        label="Yes, Remove"
                        red
                        spinner="removeMember"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

</div>
