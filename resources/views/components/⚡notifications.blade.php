<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\ClassRoomMember;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WireUiActions;

    // ──────────────────────────────────────────
    // UI state
    // ──────────────────────────────────────────

    public bool $open = false;

    // ──────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────

    #[Computed]
    public function notifications()
    {
        return Auth::user()
            ->notifications()
            ->latest()
            ->take(20)
            ->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    // ──────────────────────────────────────────
    // Poll every 30s for new notifications
    // ──────────────────────────────────────────

    // (add wire:poll.30s to the component root div in the blade)

    // ──────────────────────────────────────────
    // Accept invite
    // ──────────────────────────────────────────

    public function acceptInvite(string $notificationId, string $token): void
    {
        $member = ClassRoomMember::where('invite_token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $member) {
            $this->notification()->error(
                title:       'Invite not found',
                description: 'This invitation may have already been used or cancelled.',
            );
            $this->markRead($notificationId);
            return;
        }

        $member->update([
            'status'       => 'accepted',
            'accepted_at'  => now(),
            'invite_token' => null,
        ]);

        // Notify the form teacher
        $member->invitedBy->notify(
            new \App\Notifications\InviteAcceptedNotification($member)
        );

        $this->markRead($notificationId);
        unset($this->notifications, $this->unreadCount);

        $this->notification()->success(
            title:       'Invitation accepted!',
            description: "You've joined {$member->classroom->name} as {$member->subject} teacher.",
        );

        // Redirect so the class appears in their list
        $this->redirect(route('user.class.rooms'));
    }

    // ──────────────────────────────────────────
    // Decline invite
    // ──────────────────────────────────────────

    public function declineInvite(string $notificationId, string $token): void
    {
        $member = ClassRoomMember::where('invite_token', $token)
            ->where('status', 'pending')
            ->first();

        if ($member) {
            $member->update([
                'status'       => 'declined',
                'invite_token' => null,
            ]);

            // Notify form teacher of decline
            $member->invitedBy->notify(
                new \App\Notifications\InviteDeclinedNotification($member)
            );
        }

        $this->markRead($notificationId);
        unset($this->notifications, $this->unreadCount);

        $this->notification()->warning(
            title:       'Invitation declined',
            description: 'You have declined this class invitation.',
        );
    }

    // ──────────────────────────────────────────
    // Mark single notification read
    // ──────────────────────────────────────────

    public function markRead(string $notificationId): void
    {
        Auth::user()
            ->notifications()
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);

        unset($this->notifications, $this->unreadCount);
    }

    // ──────────────────────────────────────────
    // Mark all read
    // ──────────────────────────────────────────

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        unset($this->notifications, $this->unreadCount);
    }

    // ──────────────────────────────────────────
    // Toggle panel
    // ──────────────────────────────────────────

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function close(): void
    {
        $this->open = false;
    }
};
?>

{{-- resources/views/livewire/notification-bell.blade.php --}}
{{-- Drop this component into your app layout nav bar:  <livewire:notification-bell /> --}}

<div class="relative" wire:poll.30s x-data @click.outside="$wire.close()">

    {{-- ── Bell button ─────────────────────────────────── --}}
    <button
        wire:click="toggle"
        class="relative p-2 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
        aria-label="Notifications"
    >
        <x-icon name="bell" class="w-6 h-6" />

        {{-- Unread badge --}}
        @if ($this->unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white text-xs font-bold leading-none ring-2 ring-white">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- ── Notification panel ──────────────────────────── --}}
    @if ($open)
    <div
        class="absolute right-0 mt-2 w-96 bg-white rounded-2xl shadow-xl border border-slate-200 z-50 overflow-hidden"
        style="max-height: 80vh;"
    >
        {{-- Panel header --}}
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <h2 class="font-bold text-slate-800 text-sm">Notifications</h2>
                @if ($this->unreadCount > 0)
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full bg-red-100 text-red-600 text-xs font-semibold">
                        {{ $this->unreadCount }} new
                    </span>
                @endif
            </div>
            @if ($this->unreadCount > 0)
                <button
                    wire:click="markAllRead"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition-colors"
                >
                    Mark all read
                </button>
            @endif
        </div>

        {{-- Notification list --}}
        <div class="overflow-y-auto divide-y divide-slate-50" style="max-height: calc(80vh - 56px);">

            @forelse ($this->notifications as $notif)
            @php
                $data    = $notif->data;
                $isRead  = ! is_null($notif->read_at);
                $isInvite = ($data['type'] ?? '') === 'class_invite';
                $isAccepted = ($data['type'] ?? '') === 'invite_accepted';
                $isDeclined = ($data['type'] ?? '') === 'invite_declined';
            @endphp

            <div
                wire:key="notif-{{ $notif->id }}"
                @class([
                    'px-5 py-4 transition-colors',
                    'bg-indigo-50/50'  => ! $isRead,
                    'bg-white hover:bg-slate-50' => $isRead,
                ])
            >
                {{-- ── CLASS INVITE notification ──────────── --}}
                @if ($isInvite)
                    <div class="flex items-start gap-3">
                        {{-- Icon --}}
                        <div class="shrink-0 w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center">
                            <x-icon name="academic-cap" class="w-5 h-5 text-indigo-600" />
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-700 leading-snug">
                                <span class="font-semibold text-slate-900">{{ $data['invited_by'] ?? 'A teacher' }}</span>
                                invited you to teach
                                <span class="font-semibold text-indigo-700">{{ $data['subject'] ?? '' }}</span>
                                in class
                                <span class="font-semibold">{{ $data['class_name'] ?? '' }}</span>.
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $notif->created_at->diffForHumans() }}</p>

                            {{-- Accept / Decline buttons --}}
                            @if (! $isRead && isset($data['token']))
                                <div class="flex items-center gap-2 mt-3">
                                    <button
                                        wire:click="acceptInvite('{{ $notif->id }}', '{{ $data['token'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="acceptInvite('{{ $notif->id }}', '{{ $data['token'] }}')"
                                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors"
                                    >
                                        <x-icon name="check" class="w-3.5 h-3.5" />
                                        Accept
                                    </button>
                                    <button
                                        wire:click="declineInvite('{{ $notif->id }}', '{{ $data['token'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="declineInvite('{{ $notif->id }}', '{{ $data['token'] }}')"
                                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-red-50 border border-slate-200 hover:border-red-200 text-slate-600 hover:text-red-600 text-xs font-semibold transition-colors"
                                    >
                                        <x-icon name="x-mark" class="w-3.5 h-3.5" />
                                        Decline
                                    </button>
                                </div>
                            @elseif ($isRead && isset($data['token']))
                                <p class="text-xs text-slate-400 italic mt-1">Already responded.</p>
                            @endif
                        </div>

                        {{-- Unread dot --}}
                        @if (! $isRead)
                            <div class="shrink-0 w-2 h-2 rounded-full bg-indigo-500 mt-1.5"></div>
                        @endif
                    </div>

                {{-- ── INVITE ACCEPTED notification ───────── --}}
                @elseif ($isAccepted)
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center">
                            <x-icon name="check-circle" class="w-5 h-5 text-emerald-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-700 leading-snug">
                                <span class="font-semibold text-slate-900">{{ $data['teacher_name'] ?? 'A teacher' }}</span>
                                accepted your invitation to teach
                                <span class="font-semibold text-indigo-700">{{ $data['subject'] ?? '' }}</span>
                                in <span class="font-semibold">{{ $data['class_name'] ?? '' }}</span>.
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                        @if (! $isRead)
                            <div class="shrink-0 w-2 h-2 rounded-full bg-emerald-500 mt-1.5"></div>
                        @endif
                    </div>
                    @if (! $isRead)
                        <div class="mt-2 ml-12">
                            <button wire:click="markRead('{{ $notif->id }}')" class="text-xs text-slate-400 hover:text-slate-600">Dismiss</button>
                        </div>
                    @endif

                {{-- ── INVITE DECLINED notification ───────── --}}
                @elseif ($isDeclined)
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-9 h-9 rounded-full bg-red-100 flex items-center justify-center">
                            <x-icon name="x-circle" class="w-5 h-5 text-red-500" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-700 leading-snug">
                                <span class="font-semibold text-slate-900">{{ $data['teacher_name'] ?? 'A teacher' }}</span>
                                declined your invitation to teach
                                <span class="font-semibold text-indigo-700">{{ $data['subject'] ?? '' }}</span>
                                in <span class="font-semibold">{{ $data['class_name'] ?? '' }}</span>.
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                        @if (! $isRead)
                            <div class="shrink-0 w-2 h-2 rounded-full bg-red-400 mt-1.5"></div>
                        @endif
                    </div>
                    @if (! $isRead)
                        <div class="mt-2 ml-12">
                            <button wire:click="markRead('{{ $notif->id }}')" class="text-xs text-slate-400 hover:text-slate-600">Dismiss</button>
                        </div>
                    @endif

                {{-- ── GENERIC notification ───────────────── --}}
                @else
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center">
                            <x-icon name="bell" class="w-4 h-4 text-slate-500" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-700">{{ $data['message'] ?? 'New notification' }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                        @if (! $isRead)
                            <button wire:click="markRead('{{ $notif->id }}')" class="shrink-0 w-2 h-2 rounded-full bg-slate-400 mt-1.5 hover:bg-slate-600 transition-colors" title="Mark read"></button>
                        @endif
                    </div>
                @endif
            </div>

            @empty
                <div class="py-16 text-center">
                    <x-icon name="bell-slash" class="w-10 h-10 text-slate-300 mx-auto mb-3" />
                    <p class="text-slate-400 text-sm font-medium">No notifications yet.</p>
                </div>
            @endforelse

        </div>
    </div>
    @endif

</div>
