<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WireUiActions;

    // ── Form state ──────────────────────────────────────────────────────────
    public ?int   $selectedPlanId = null;
    public string $billingCycle   = 'monthly'; // 'monthly' | 'yearly'
    public string $notes          = '';

    // ── UI state ────────────────────────────────────────────────────────────
    public bool $isRenewal = false;

    // ── Loaded data ─────────────────────────────────────────────────────────
    public ?Subscription $activeSubscription = null;

    // ──────────────────────────────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->activeSubscription = Auth::user()
            ->subscriptions()
            ->with('plan')
            ->active()
            ->latest()
            ->first();

        if ($this->activeSubscription) {
            $this->isRenewal      = true;
            $this->selectedPlanId = $this->activeSubscription->plan_id;
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Computed properties
    // ──────────────────────────────────────────────────────────────────────

    public function getPlansProperty()
    {
        return SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getSelectedPlanProperty(): ?SubscriptionPlan
    {
        if (! $this->selectedPlanId) {
            return null;
        }

        return $this->plans->firstWhere('id', $this->selectedPlanId);
    }

    public function getPriceProperty(): ?float
    {
        if (! $this->selectedPlan) {
            return null;
        }

        return $this->billingCycle === 'yearly'
            ? $this->selectedPlan->price_zmw*12
            : $this->selectedPlan->price_zmw;
    }

    public function getSavingsProperty(): ?float
    {
        if (! $this->selectedPlan || $this->billingCycle !== 'yearly') {
            return null;
        }

        return ($this->selectedPlan->price_zmw * 12) - $this->selectedPlan->price_zmw;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Map raw feature keys to human-readable labels and icons.
     * Returns an array of ['label' => string, 'icon' => string (SVG path d attr), 'limit' => string|null]
     */
    public function getFeatureList(array $features): array
    {
        $map = [
            'attendance'     => ['label' => 'Attendance Tracking',   'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
            'pdf_attendance' => ['label' => 'PDF Attendance Reports', 'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
            'assessments'    => ['label' => 'Assessments',            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            'lesson_plans'   => ['label' => 'Lesson Plans',           'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
            'report_cards'   => ['label' => 'Report Cards',           'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            'behaviour_logs' => ['label' => 'Behaviour Logs',         'icon' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'],
        ];

        $result = [];

        foreach ($map as $key => $meta) {
            if (! array_key_exists($key, $features)) {
                continue;
            }

            $value = $features[$key];

            // Skip explicitly false/null features
            if ($value === false || $value === null) {
                continue;
            }

            $limit = null;
            if ($key === 'max_classes' && is_int($value)) {
                $limit = "Up to {$value} classes";
            }

            $result[] = array_merge($meta, ['limit' => $limit, 'enabled' => true]);
        }

        // max_classes special case — show even when null (unlimited)
        if (array_key_exists('max_classes', $features)) {
            $result[] = [
                'label'   => 'Classes',
                'icon'    => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 00-1-1h-2a1 1 0 00-1 1v5m4 0H9',
                'limit'   => $features['max_classes'] === null ? 'Unlimited classes' : "Up to {$features['max_classes']} classes",
                'enabled' => true,
            ];
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────────────────────────────

    public function setBillingCycle(string $cycle): void
    {
        $this->billingCycle = $cycle;
    }

    public function selectPlan(int $planId): void
    {
        $this->selectedPlanId = $planId;
    }

    public function requestPayment(): void
    {
        $this->validate([
            'selectedPlanId' => ['required', 'exists:subscription_plans,id'],
            'billingCycle'   => ['required', 'in:monthly,yearly'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        // Pass amount + metadata to JS so Lenco popup can be launched
        $this->dispatch('launchLencoPay', [
            'amount'   => (float) $this->price,
            'planId'   => $this->selectedPlanId,
            'cycle'    => $this->billingCycle,
            'email'    => Auth::user()->email,
            'firstName'=> Auth::user()->first_name ?? '',
            'lastName' => Auth::user()->last_name  ?? '',
        ]);
    }

    /**
     * Called from JS after Lenco returns a successful payment reference.
     * This is the only place a subscription gets created.
     */
    public function completeSubscription(string $reference): void
    {
        $this->validate([
            'selectedPlanId' => ['required', 'exists:subscription_plans,id'],
            'billingCycle'   => ['required', 'in:monthly,yearly'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $plan   = SubscriptionPlan::findOrFail($this->selectedPlanId);
        $user   = Auth::user();
        $months = $this->billingCycle === 'yearly' ? 12 : 1;

        $startsAt = now();
        $endsAt   = $startsAt->copy()->addMonths($months);

        // Expire any current active subscription
        $this->activeSubscription?->update(['status' => 'expired']);

        $subscription = Subscription::create([
            'user_id'           => $user->id,
            'plan_id'           => $plan->id,
            'status'            => 'active',
            'starts_at'         => $startsAt,
            'ends_at'           => $endsAt,
            'notes'             => $this->notes ?: null,
            'payment_reference' => $reference,
        ]);

        $wasRenewal = $this->isRenewal;

        $this->activeSubscription = $subscription->load('plan');
        $this->isRenewal          = true;
        $this->notes              = '';

        $this->notification()->success(
            title: $wasRenewal ? 'Subscription Renewed!' : 'Welcome aboard!',
            description: "Your {$plan->name} plan is now active until {$endsAt->format('M j, Y')}.",
        );

        $this->dispatch('subscription-updated');
    }
};
?>

<div class="bg-gray-50 min-h-screen py-12 px-4"
     x-data="{ billingCycle: $wire.entangle('billingCycle') }">
    <div class="max-w-6xl mx-auto">

        {{-- ── Header ──────────────────────────────────────────────────────── --}}
        <div class="text-center mb-10">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                {{ $isRenewal ? 'Manage Your Plan' : 'Choose Your Plan' }}
            </h1>
            <p class="text-gray-500 text-base">
                {{ $isRenewal ? 'Renew or switch to a different plan.' : 'Pick the plan that fits your school.' }}
            </p>
        </div>

        {{-- ── Expiry warning banner ────────────────────────────────────────── --}}
        @if ($activeSubscription?->isExpiringSoon())
            <div class="mb-6 flex items-center gap-3 bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-xl px-5 py-4 text-sm">
                <svg class="w-5 h-5 flex-shrink-0 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span>
                    Your <strong>{{ $activeSubscription->plan->name }}</strong> plan expires in
                    <strong>{{ $activeSubscription->daysRemaining() }} day(s)</strong>
                    on {{ $activeSubscription->ends_at->format('M j, Y') }}.
                    Renew now to avoid interruption.
                </span>
            </div>
        @endif

        {{-- ── Active subscription badge ────────────────────────────────────── --}}
        @if ($activeSubscription)
            <div class="mb-8 inline-flex items-center gap-3 bg-white border border-gray-200 rounded-full px-4 py-2 shadow-sm text-sm">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 {{ $activeSubscription->isExpiringSoon() ? 'bg-yellow-400' : 'bg-green-400' }}"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $activeSubscription->isExpiringSoon() ? 'bg-yellow-400' : 'bg-green-400' }}"></span>
                </span>
                <span class="text-gray-600">
                    Active: <strong class="text-gray-900">{{ $activeSubscription->plan->name }}</strong>
                    &mdash;
                    <span class="{{ $activeSubscription->isExpiringSoon() ? 'text-yellow-600' : 'text-gray-500' }}">
                        {{ $activeSubscription->daysRemaining() }} day(s) remaining
                    </span>
                </span>
            </div>
        @endif

        {{-- ── Billing cycle toggle ────────────────────────────────────────── --}}
        <div class="flex justify-center mb-10">
            <div class="inline-flex items-center bg-white border border-gray-200 rounded-xl p-1 shadow-sm gap-1">
                <button
                    wire:click="setBillingCycle('monthly')"
                    class="px-6 py-2 rounded-lg text-sm font-medium transition-all duration-150"
                    :class="billingCycle === 'monthly' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                >
                    Monthly
                </button>
                <button
                    wire:click="setBillingCycle('yearly')"
                    class="relative px-6 py-2 rounded-lg text-sm font-medium transition-all duration-150"
                    :class="billingCycle === 'yearly' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                >
                    Yearly
                    <span class="absolute -top-2.5 -right-2 bg-green-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none shadow">
                        SAVE
                    </span>
                </button>
            </div>
        </div>

        {{-- ── Plan cards ──────────────────────────────────────────────────── --}}
        <div class="grid md:grid-cols-3 gap-6 mb-10">
            @foreach ($this->plans as $plan)
                @php
                    $isSelected    = $selectedPlanId === $plan->id;
                    $isCurrent     = $activeSubscription?->plan_id === $plan->id;
                    $isPopular     = $plan->is_popular ?? false;
                    $monthlyPrice  = (float) $plan->price_zmw;
                    $yearlyPrice   = (float) $plan->price_zmw*12;
                    $yearlySavings = ($monthlyPrice * 12) - $monthlyPrice;
                    $featureList   = is_array($plan->features) ? $this->getFeatureList($plan->features) : [];
                @endphp

                <div class="relative flex">
                    <button
                        wire:click="selectPlan({{ $plan->id }})"
                        x-data="{ monthly: {{ $monthlyPrice }}, yearly: {{ $yearlyPrice }}, savings: {{ $yearlySavings }} }"
                        class="w-full text-left bg-white rounded-2xl border-2 p-6 cursor-pointer transition-all duration-200 hover:shadow-lg flex flex-col
                            {{ $isSelected
                                ? 'border-blue-500 ring-2 ring-blue-100 shadow-md'
                                : 'border-gray-200 shadow-sm hover:border-gray-300' }}"
                    >
                        {{-- Popular badge --}}
                        @if ($isPopular)
                            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-bold px-4 py-1 rounded-full shadow-sm tracking-wide">
                                MOST POPULAR
                            </div>
                        @endif

                        {{-- Selected checkmark --}}
                        @if ($isSelected)
                            <div class="absolute top-4 right-4 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center shadow-sm">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        @endif

                        {{-- Header --}}
                        <div class="mb-4 pr-8">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <h3 class="text-xl font-bold text-gray-900">{{ $plan->name }}</h3>
                                @if ($isCurrent)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        Current
                                    </span>
                                @endif
                            </div>
                            @if ($plan->description)
                                <p class="text-gray-500 text-sm">{{ $plan->description }}</p>
                            @endif
                        </div>

                        {{-- Price — reactive --}}
                        <div class="mb-5 pb-5 border-b border-gray-100">
                            <div class="flex items-end gap-1">
                                <span class="text-3xl font-extrabold text-gray-900">
                                    K<span x-text="billingCycle === 'yearly'
                                        ? new Intl.NumberFormat('en-ZM', {maximumFractionDigits:0}).format(yearly)
                                        : new Intl.NumberFormat('en-ZM', {maximumFractionDigits:0}).format(monthly)
                                    "></span>
                                </span>
                                <span class="text-gray-400 text-sm mb-1.5"
                                      x-text="billingCycle === 'yearly' ? '/ year' : '/ month'"></span>
                            </div>
                            {{--<p class="text-xs text-green-600 mt-1 h-4"
                               x-show="billingCycle === 'yearly' && savings > 0"
                               x-text="'Save K' + new Intl.NumberFormat('en-ZM', {maximumFractionDigits:0}).format(savings) + ' vs monthly'">
                            </p>--}}
                        </div>

                        {{-- Features from JSON --}}
                        @if (count($featureList) > 0)
                            <ul class="space-y-2.5 flex-1">
                                @foreach ($featureList as $feature)
                                    <li class="flex items-start gap-2.5 text-sm text-gray-700">
                                        <div class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-50 flex items-center justify-center mt-0.5">
                                            <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}"/>
                                            </svg>
                                        </div>
                                        <span>
                                            {{ $feature['limit'] ?? $feature['label'] }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                    </button>
                </div>
            @endforeach
        </div>

        {{-- ── Order Summary + Notes + CTA ─────────────────────────────────── --}}
        @if ($selectedPlanId)
            <div class="space-y-4">

                @if ($this->selectedPlan)
                    @php
                        $ctaMonthly = (float) $this->selectedPlan->price_zmw;
                        $ctaYearly  = (float) $this->selectedPlan->price_zmw*12;
                        $ctaSavings = ($ctaMonthly * 12) - $ctaMonthly;
                    @endphp

                    {{-- Summary box --}}
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6"
                         x-data="{ monthly: {{ $ctaMonthly }}, yearly: {{ $ctaYearly }}, savings: {{ $ctaSavings }} }">
                        <h3 class="text-base font-semibold text-gray-900 mb-4">Order Summary</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Plan</span>
                                <span class="font-semibold text-gray-900">{{ $this->selectedPlan->name }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Billing Cycle</span>
                                <span class="font-semibold text-gray-900 capitalize" x-text="billingCycle"></span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-gray-100">
                                <span class="text-gray-900 font-semibold">Total</span>
                                <div class="text-right">
                                    <span class="text-xl font-bold text-blue-600">
                                        K<span x-text="billingCycle === 'yearly'
                                            ? new Intl.NumberFormat('en-ZM', {minimumFractionDigits:2, maximumFractionDigits:2}).format(yearly)
                                            : new Intl.NumberFormat('en-ZM', {minimumFractionDigits:2, maximumFractionDigits:2}).format(monthly)
                                        "></span>
                                        <span class="text-sm font-normal text-gray-400"
                                              x-text="billingCycle === 'yearly' ? ' / year' : ' / month'"></span>
                                    </span>
                                    <p class="text-xs text-green-600 mt-0.5"
                                       x-show="billingCycle === 'yearly' && savings > 0"
                                       x-text="'You save K' + new Intl.NumberFormat('en-ZM', {maximumFractionDigits:0}).format(savings) + ' per year'">
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Notes + Submit --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex flex-col sm:flex-row gap-4 items-center">
                        <div class="flex-1 w-full">
                            <label for="notes-field" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Notes <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <input
                                type="text"
                                id="notes-field"
                                wire:model="notes"
                                placeholder="E.g. promo code, special request…"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                            >
                            <p class="text-xs text-gray-400 mt-1">This note will be attached to the subscription record.</p>
                        </div>
                        <div class="flex-shrink-0">
                            <button
                                wire:click="requestPayment"
                                wire:loading.attr="disabled"
                                class="bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 px-8 rounded-xl shadow transition-all hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 whitespace-nowrap"
                            >
                                <span wire:loading.remove wire:target="requestPayment">
                                    {{ $isRenewal ? 'Renew Subscription' : 'Subscribe Now' }}
                                </span>
                                <span wire:loading wire:target="requestPayment">Preparing…</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>

    {{-- ── Lenco Pay integration ───────────────────────────────────────── --}}
    <script src="https://pay.lenco.co/js/v1/inline.js"></script>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('launchLencoPay', ([payload]) => {
                const { amount, email, firstName, lastName } = payload;

                if (!amount || amount <= 0) {
                    alert('Invalid payment amount. Please select a plan and try again.');
                    return;
                }

                LencoPay.getPaid({
                    key:       'pub-071f354dc65dbbb786644b8aa7f0fd601948782e79bf5cbf',
                    reference: 'ref-' + Date.now(),
                    email:     email,
                    amount:    amount,
                    currency:  'ZMW',
                    channels:  ['card', 'mobile-money'],
                    customer: {
                        firstName: firstName || 'Subscriber',
                        lastName:  lastName  || '',
                        phone:     '',
                    },

                    onSuccess: function (response) {
                        // Hand the verified reference back to Livewire to create the subscription
                        @this.completeSubscription(response.reference);
                    },

                    onClose: function () {
                        // User dismissed — no action needed, they can try again
                    },

                    onConfirmationPending: function () {
                        alert('Your payment is pending confirmation. We will activate your plan once verified.');
                    },
                });
            });
        });
    </script>

    {{-- WireUI notifications --}}
    <x-notifications />
</div>
