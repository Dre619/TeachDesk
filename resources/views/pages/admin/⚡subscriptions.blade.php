<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
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
    public string $filterStatus = '';
    public string $filterPlan   = '';

    // ──────────────────────────────────────────
    // Edit modal — adjust expiry / status
    // ──────────────────────────────────────────

    public bool    $editModal      = false;
    public ?int    $editSubId      = null;
    public string  $editSubUser    = '';
    public string  $editStatus     = 'active';
    public string  $editEndsAt     = '';
    public string  $editNotes      = '';

    // ──────────────────────────────────────────
    // Payment modal — record a manual payment
    // ──────────────────────────────────────────

    public bool    $paymentModal   = false;
    public ?int    $paymentSubId   = null;
    public string  $paymentSubUser = '';
    public string  $paymentAmount  = '';
    public string  $paymentMethod  = 'manual';
    public string  $paymentRef     = '';
    public string  $paymentNotes   = '';

    // ──────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────

    #[Computed]
    public function subscriptions()
    {
        return Subscription::query()
            ->with(['user', 'plan', 'payments' => fn ($q) => $q->latest()->limit(1)])
            ->when($this->search, fn ($q) =>
                $q->whereHas('user', fn ($u) =>
                    $u->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                )
            )
            ->when($this->filterStatus, fn ($q) =>
                $this->filterStatus === 'expired'
                    ? $q->where(fn ($q2) =>
                        $q2->where('status', 'expired')
                           ->orWhere('ends_at', '<=', now())
                      )
                    : $q->where('status', $this->filterStatus)
                         ->where('ends_at', '>', now())
            )
            ->when($this->filterPlan, fn ($q) =>
                $q->where('plan_id', $this->filterPlan)
            )
            ->latest()
            ->paginate(20);
    }

    #[Computed]
    public function plans()
    {
        return SubscriptionPlan::orderBy('sort_order')->get();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'active'  => Subscription::active()->count(),
            'trial'   => Subscription::trial()->where('ends_at', '>', now())->count(),
            'expired' => Subscription::expired()->count(),
            'revenue' => (float) Payment::successful()->sum('amount_zmw'),
        ];
    }

    // ──────────────────────────────────────────
    // Edit subscription
    // ──────────────────────────────────────────

    public function openEditModal(int $subId): void
    {
        $sub = Subscription::with('user')->findOrFail($subId);
        $this->editSubId   = $subId;
        $this->editSubUser = $sub->user->name;
        $this->editStatus  = $sub->status;
        $this->editEndsAt  = $sub->ends_at->format('Y-m-d');
        $this->editNotes   = $sub->notes ?? '';
        $this->editModal   = true;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editStatus' => 'required|in:active,trial,expired',
            'editEndsAt' => 'required|date',
            'editNotes'  => 'nullable|string|max:500',
        ]);

        Subscription::findOrFail($this->editSubId)->update([
            'status'  => $this->editStatus,
            'ends_at' => $this->editEndsAt,
            'notes'   => $this->editNotes ?: null,
        ]);

        $this->editModal = false;
        unset($this->subscriptions);
        session()->flash('status', 'Subscription updated.');
    }

    // ──────────────────────────────────────────
    // Record payment
    // ──────────────────────────────────────────

    public function openPaymentModal(int $subId): void
    {
        $sub = Subscription::with('user')->findOrFail($subId);
        $this->paymentSubId   = $subId;
        $this->paymentSubUser = $sub->user->name;
        $this->paymentAmount  = '';
        $this->paymentMethod  = 'manual';
        $this->paymentRef     = '';
        $this->paymentNotes   = '';
        $this->paymentModal   = true;
    }

    public function savePayment(): void
    {
        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentMethod' => 'required|in:mtn,airtel,card,paypal,manual',
            'paymentRef'    => 'nullable|string|max:255',
            'paymentNotes'  => 'nullable|string|max:500',
        ]);

        $sub = Subscription::findOrFail($this->paymentSubId);

        Payment::create([
            'user_id'         => $sub->user_id,
            'subscription_id' => $sub->id,
            'plan_id'         => $sub->plan_id,
            'amount_zmw'      => $this->paymentAmount,
            'method'          => $this->paymentMethod,
            'gateway_ref'     => $this->paymentRef ?: null,
            'status'          => 'success',
            'paid_at'         => now(),
            'notes'           => $this->paymentNotes ?: null,
        ]);

        $this->paymentModal = false;
        unset($this->subscriptions, $this->stats);
        session()->flash('status', 'Payment recorded for ' . $this->paymentSubUser . '.');
    }

    // ──────────────────────────────────────────
    // Misc
    // ──────────────────────────────────────────

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterPlan(): void   { $this->resetPage(); }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    <x-notifications />
    <x-dialog />

    {{-- ─────────────────────────────────────────── --}}
    {{-- Page Header --}}
    {{-- ─────────────────────────────────────────── --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Subscriptions</h1>
        <p class="mt-1 text-sm text-gray-500">Manage user subscriptions and record payments.</p>
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Stats strip --}}
    {{-- ─────────────────────────────────────────── --}}
    @php $stats = $this->stats; @endphp
    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Active</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $stats['active'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Trial</p>
            <p class="mt-1 text-2xl font-bold text-yellow-500">{{ $stats['trial'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Expired</p>
            <p class="mt-1 text-2xl font-bold text-gray-400">{{ $stats['expired'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Revenue</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">K{{ number_format($stats['revenue'], 2) }}</p>
        </div>
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Filters --}}
    {{-- ─────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <x-input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by user name or email…"
                icon="magnifying-glass"
                class="w-full"
            />
        </div>
        <x-select
            wire:model.live="filterStatus"
            placeholder="All Statuses"
            class="sm:w-40"
            :options="[
                ['label' => 'Active',  'value' => 'active'],
                ['label' => 'Trial',   'value' => 'trial'],
                ['label' => 'Expired', 'value' => 'expired'],
            ]"
            option-label="label"
            option-value="value"
        />
        <x-select
            wire:model.live="filterPlan"
            placeholder="All Plans"
            class="sm:w-44"
            :options="$this->plans->map(fn ($p) => ['label' => $p->name, 'value' => $p->id])->toArray()"
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
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Plan</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Started</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Expires</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Notes</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->subscriptions as $sub)
                    @php $expired = $sub->ends_at->isPast() || $sub->status === 'expired'; @endphp
                    <tr class="transition hover:bg-gray-50" wire:key="sub-{{ $sub->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $sub->user->name }}</div>
                            <div class="text-xs text-gray-400">{{ $sub->user->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $sub->plan->name }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-red-100 text-red-600'         => $expired,
                                'bg-yellow-100 text-yellow-700'   => !$expired && $sub->status === 'trial',
                                'bg-emerald-100 text-emerald-700' => !$expired && $sub->status === 'active',
                            ])>
                                {{ $expired ? 'Expired' : ucfirst($sub->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $sub->starts_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-xs {{ (!$expired && $sub->isExpiringSoon()) ? 'font-semibold text-orange-500' : 'text-gray-400' }}">
                            {{ $sub->ends_at->format('d M Y') }}
                            @if (!$expired && $sub->isExpiringSoon())
                                <span class="block font-normal text-orange-400">{{ $sub->daysRemaining() }}d left</span>
                            @endif
                        </td>
                        <td class="max-w-40 truncate px-4 py-3 text-xs text-gray-400">
                            {{ $sub->notes ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <x-mini-button
                                    icon="pencil"
                                    color="secondary"
                                    wire:click="openEditModal({{ $sub->id }})"
                                    title="Edit subscription"
                                />
                                <x-mini-button
                                    icon="banknotes"
                                    color="secondary"
                                    wire:click="openPaymentModal({{ $sub->id }})"
                                    title="Record payment"
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <x-icon name="inbox" class="mx-auto mb-2 h-10 w-10 text-gray-300" />
                            No subscriptions found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->subscriptions->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">
                {{ $this->subscriptions->links() }}
            </div>
        @endif
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Edit Subscription Modal --}}
    {{-- ─────────────────────────────────────────── --}}
    <x-modal wire:model="editModal" max-width="lg" persistent>
        <x-card :title="'Edit Subscription — ' . $editSubUser" class="relative">
            <div class="space-y-4 p-2">
                <div class="grid grid-cols-2 gap-4">
                    <x-select
                        wire:model="editStatus"
                        label="Status"
                        :options="[
                            ['label' => 'Active',  'value' => 'active'],
                            ['label' => 'Trial',   'value' => 'trial'],
                            ['label' => 'Expired', 'value' => 'expired'],
                        ]"
                        option-label="label"
                        option-value="value"
                    />
                    <x-input wire:model="editEndsAt" type="date" label="Expires on" />
                </div>
                <x-textarea wire:model="editNotes" label="Notes (optional)" rows="2" />
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('editModal', false)" />
                    <x-button label="Save" icon="check" wire:click="saveEdit" spinner="saveEdit" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Record Payment Modal --}}
    {{-- ─────────────────────────────────────────── --}}
    <x-modal wire:model="paymentModal" max-width="lg" persistent>
        <x-card :title="'Record Payment — ' . $paymentSubUser" class="relative">
            <div class="space-y-4 p-2">
                <div class="grid grid-cols-2 gap-4">
                    <x-input wire:model="paymentAmount" type="number" step="0.01" min="0"
                        label="Amount (ZMW)" placeholder="120.00" />
                    <x-select
                        wire:model="paymentMethod"
                        label="Method"
                        :options="[
                            ['label' => 'Manual (Admin)',    'value' => 'manual'],
                            ['label' => 'MTN Mobile Money', 'value' => 'mtn'],
                            ['label' => 'Airtel Money',      'value' => 'airtel'],
                            ['label' => 'Card',              'value' => 'card'],
                            ['label' => 'PayPal',            'value' => 'paypal'],
                        ]"
                        option-label="label"
                        option-value="value"
                    />
                </div>
                <x-input wire:model="paymentRef" label="Reference / receipt no. (optional)"
                    placeholder="e.g. TXN-20240101" />
                <x-textarea wire:model="paymentNotes" label="Notes (optional)" rows="2" />
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('paymentModal', false)" />
                    <x-button label="Record" icon="check" wire:click="savePayment" spinner="savePayment" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

</div>
