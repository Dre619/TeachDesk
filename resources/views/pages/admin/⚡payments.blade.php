<?php

use App\Models\Payment;
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
    public string $filterMethod = '';
    public string $filterPlan   = '';

    // ──────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────

    #[Computed]
    public function payments()
    {
        return Payment::query()
            ->with(['user', 'plan'])
            ->when($this->search, fn ($q) =>
                $q->whereHas('user', fn ($u) =>
                    $u->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                )->orWhere('gateway_ref', 'like', '%' . $this->search . '%')
            )
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterMethod, fn ($q) => $q->where('method', $this->filterMethod))
            ->when($this->filterPlan,   fn ($q) => $q->where('plan_id', $this->filterPlan))
            ->latest('paid_at')
            ->paginate(25);
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
            'total'   => Payment::count(),
            'success' => Payment::successful()->count(),
            'pending' => Payment::pending()->count(),
            'failed'  => Payment::failed()->count(),
            'revenue' => (float) Payment::successful()->sum('amount_zmw'),
        ];
    }

    // ──────────────────────────────────────────
    // Misc
    // ──────────────────────────────────────────

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterMethod(): void { $this->resetPage(); }
    public function updatedFilterPlan(): void   { $this->resetPage(); }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    <x-notifications />

    {{-- ─────────────────────────────────────────── --}}
    {{-- Page Header --}}
    {{-- ─────────────────────────────────────────── --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Payments</h1>
        <p class="mt-1 text-sm text-gray-500">All payment transactions across the platform.</p>
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Stats strip --}}
    {{-- ─────────────────────────────────────────── --}}
    @php $stats = $this->stats; @endphp
    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Successful</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $stats['success'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pending</p>
            <p class="mt-1 text-2xl font-bold text-yellow-500">{{ $stats['pending'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Failed</p>
            <p class="mt-1 text-2xl font-bold text-red-500">{{ $stats['failed'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Revenue</p>
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
                placeholder="Search user, email or reference…"
                icon="magnifying-glass"
                class="w-full"
            />
        </div>
        <x-select
            wire:model.live="filterStatus"
            placeholder="All Statuses"
            class="sm:w-36"
            :options="[
                ['label' => 'Successful', 'value' => 'success'],
                ['label' => 'Pending',    'value' => 'pending'],
                ['label' => 'Failed',     'value' => 'failed'],
            ]"
            option-label="label"
            option-value="value"
        />
        <x-select
            wire:model.live="filterMethod"
            placeholder="All Methods"
            class="sm:w-44"
            :options="[
                ['label' => 'MTN Mobile Money', 'value' => 'mtn'],
                ['label' => 'Airtel Money',      'value' => 'airtel'],
                ['label' => 'Card',              'value' => 'card'],
                ['label' => 'PayPal',            'value' => 'paypal'],
                ['label' => 'Manual (Admin)',    'value' => 'manual'],
            ]"
            option-label="label"
            option-value="value"
        />
        <x-select
            wire:model.live="filterPlan"
            placeholder="All Plans"
            class="sm:w-40"
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
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Amount</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Method</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Reference</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->payments as $payment)
                    <tr class="transition hover:bg-gray-50" wire:key="pay-{{ $payment->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $payment->user->name }}</div>
                            <div class="text-xs text-gray-400">{{ $payment->user->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $payment->plan->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 font-semibold text-emerald-700">
                            {{ $payment->formatted_amount }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $payment->method_label }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($payment->gateway_ref)
                                <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600">
                                    {{ $payment->gateway_ref }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-700' => $payment->status === 'success',
                                'bg-yellow-100 text-yellow-700'   => $payment->status === 'pending',
                                'bg-red-100 text-red-600'         => $payment->status === 'failed',
                            ])>
                                {{ ucfirst($payment->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400">
                            {{ $payment->paid_at ? $payment->paid_at->format('d M Y, H:i') : '—' }}
                        </td>
                        <td class="max-w-40 truncate px-4 py-3 text-xs text-gray-400">
                            {{ $payment->notes ?: '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            <x-icon name="inbox" class="mx-auto mb-2 h-10 w-10 text-gray-300" />
                            No payments found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->payments->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">
                {{ $this->payments->links() }}
            </div>
        @endif
    </div>

</div>
