<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // ──────────────────────────────────────────
    // Filters
    // ──────────────────────────────────────────

    public string $search       = '';
    public string $filterStatus = '';  // '', 'subscribed', 'unsubscribed'

    // ──────────────────────────────────────────
    // Edit modal
    // ──────────────────────────────────────────

    public bool    $editModal  = false;
    public ?int    $editUserId = null;
    public string  $editRole   = 'user';

    // ──────────────────────────────────────────
    // Assign subscription modal
    // ──────────────────────────────────────────

    public bool   $assignModal   = false;
    public ?int   $assignUserId  = null;
    public string $assignName    = '';
    public ?int   $assignPlanId  = null;
    public string $assignStatus  = 'active';
    public string $assignEndsAt  = '';
    public string $assignNotes   = '';

    // ──────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────

    #[Computed]
    public function users()
    {
        return User::query()
            ->where('role', '!=', 'super_admin')
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->where('name', 'like', '%' . $this->search . '%')
                       ->orWhere('email', 'like', '%' . $this->search . '%')
                       ->orWhere('school_name', 'like', '%' . $this->search . '%')
                )
            )
            ->when($this->filterStatus === 'subscribed', fn ($q) =>
                $q->whereHas('subscriptions', fn ($s) =>
                    $s->whereIn('status', ['active', 'trial'])->where('ends_at', '>', now())
                )
            )
            ->when($this->filterStatus === 'unsubscribed', fn ($q) =>
                $q->whereDoesntHave('subscriptions', fn ($s) =>
                    $s->whereIn('status', ['active', 'trial'])->where('ends_at', '>', now())
                )
            )
            ->withCount('classes', 'students')
            ->with(['subscriptions' => fn ($q) =>
                $q->whereIn('status', ['active', 'trial'])
                  ->where('ends_at', '>', now())
                  ->with('plan')
                  ->latest()
                  ->limit(1)
            ])
            ->latest()
            ->paginate(20);
    }

    #[Computed]
    public function plans()
    {
        return SubscriptionPlan::orderBy('sort_order')->get();
    }

    // ──────────────────────────────────────────
    // Edit role modal
    // ──────────────────────────────────────────

    public function openEditModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editUserId = $userId;
        $this->editRole   = $user->role;
        $this->editModal  = true;
    }

    public function saveRole(): void
    {
        $this->validate(['editRole' => 'required|in:user,super_admin']);

        User::findOrFail($this->editUserId)->update(['role' => $this->editRole]);

        $this->editModal = false;
        session()->flash('status', 'User role updated.');
    }

    // ──────────────────────────────────────────
    // Assign subscription modal
    // ──────────────────────────────────────────

    public function openAssignModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->assignUserId = $userId;
        $this->assignName   = $user->name;
        $this->assignPlanId = null;
        $this->assignStatus = 'active';
        $this->assignEndsAt = now()->addMonth()->format('Y-m-d');
        $this->assignNotes  = '';
        $this->assignModal  = true;
    }

    public function saveSubscription(): void
    {
        $this->validate([
            'assignPlanId'  => 'required|exists:subscription_plans,id',
            'assignStatus'  => 'required|in:active,trial',
            'assignEndsAt'  => 'required|date|after:today',
            'assignNotes'   => 'nullable|string|max:500',
        ]);

        Subscription::create([
            'user_id'    => $this->assignUserId,
            'plan_id'    => $this->assignPlanId,
            'status'     => $this->assignStatus,
            'starts_at'  => now(),
            'ends_at'    => $this->assignEndsAt,
            'notes'      => $this->assignNotes ?: null,
        ]);

        $this->assignModal = false;
        unset($this->users);
        session()->flash('status', 'Subscription assigned to ' . $this->assignName . '.');
    }

    // ──────────────────────────────────────────
    // Misc
    // ──────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    <x-notifications />
    <x-dialog />

    {{-- ─────────────────────────────────────────── --}}
    {{-- Page Header --}}
    {{-- ─────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Users</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $this->users->total() }} registered teachers</p>
        </div>
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Filters --}}
    {{-- ─────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <x-input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name, email or school…"
                icon="magnifying-glass"
                class="w-full"
            />
        </div>
        <x-select
            wire:model.live="filterStatus"
            placeholder="All Users"
            class="sm:w-48"
            :options="[
                ['label' => 'Subscribed',   'value' => 'subscribed'],
                ['label' => 'Unsubscribed', 'value' => 'unsubscribed'],
            ]"
            option-label="label"
            option-value="value"
        />
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Table --}}
    {{-- ─────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">User</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">School</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600">Classes</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600">Students</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Subscription</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Joined</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->users as $user)
                    @php $sub = $user->subscriptions->first(); @endphp
                    <tr class="transition hover:bg-gray-50" wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $user->name }}</div>
                            <div class="text-xs text-gray-400">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $user->school_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $user->classes_count }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $user->students_count }}</td>
                        <td class="px-4 py-3">
                            @if ($sub)
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700' => $sub->status === 'active',
                                    'bg-yellow-100 text-yellow-700'   => $sub->status === 'trial',
                                ])>
                                    {{ ucfirst($sub->status) }} — {{ $sub->plan->name }}
                                </span>
                                <div class="mt-0.5 text-xs text-gray-400">
                                    expires {{ $sub->ends_at->format('d M Y') }}
                                    @if ($sub->isExpiringSoon())
                                        <span class="text-orange-500">(soon)</span>
                                    @endif
                                </div>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">
                                    None
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <x-mini-button
                                    icon="key"
                                    color="secondary"
                                    wire:click="openAssignModal({{ $user->id }})"
                                    title="Assign subscription"
                                />
                                <x-mini-button
                                    icon="pencil"
                                    color="secondary"
                                    wire:click="openEditModal({{ $user->id }})"
                                    title="Edit role"
                                />
                                <form method="POST" action="{{ route('admin.impersonate.start', $user) }}" class="inline">
                                    @csrf
                                    <button
                                        type="submit"
                                        title="View as this user"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 bg-white text-amber-600 hover:bg-amber-50 hover:border-amber-300 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <x-icon name="inbox" class="mx-auto mb-2 h-10 w-10 text-gray-300" />
                            No users found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->users->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">
                {{ $this->users->links() }}
            </div>
        @endif
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Edit Role Modal --}}
    {{-- ─────────────────────────────────────────── --}}
    <x-modal wire:model="editModal" max-width="sm" persistent>
        <x-card title="Edit User Role" class="relative">
            <div class="p-2">
                <x-select
                    wire:model="editRole"
                    label="Role"
                    :options="[
                        ['label' => 'User (Teacher)', 'value' => 'user'],
                        ['label' => 'Super Admin',    'value' => 'super_admin'],
                    ]"
                    option-label="label"
                    option-value="value"
                />
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('editModal', false)" />
                    <x-button label="Save" icon="check" wire:click="saveRole" spinner="saveRole" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Assign Subscription Modal --}}
    {{-- ─────────────────────────────────────────── --}}
    <x-modal wire:model="assignModal" max-width="lg" persistent>
        <x-card :title="'Assign Subscription — ' . $assignName" class="relative">
            <div class="space-y-4 p-2">
                <x-select
                    wire:model="assignPlanId"
                    label="Plan"
                    placeholder="— select plan —"
                    :options="$this->plans->map(fn ($p) => ['label' => $p->name . ' (' . $p->formatted_price . '/' . $p->billing_cycle . ')', 'value' => $p->id])->toArray()"
                    option-label="label"
                    option-value="value"
                />
                <div class="grid grid-cols-2 gap-4">
                    <x-select
                        wire:model="assignStatus"
                        label="Status"
                        :options="[
                            ['label' => 'Active', 'value' => 'active'],
                            ['label' => 'Trial',  'value' => 'trial'],
                        ]"
                        option-label="label"
                        option-value="value"
                    />
                    <x-input wire:model="assignEndsAt" type="date" label="Expires on" />
                </div>
                <x-textarea wire:model="assignNotes" label="Notes (optional)" rows="2"
                    placeholder="e.g. Manual payment via bank transfer K120" />
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('assignModal', false)" />
                    <x-button label="Assign" icon="check" wire:click="saveSubscription" spinner="saveSubscription" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

</div>
