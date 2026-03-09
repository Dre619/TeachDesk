@if (session('subscription_warning'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition
        class="relative flex items-start gap-4 bg-red-600 px-4 py-3 text-white sm:items-center sm:px-6"
        role="alert"
    >
        <svg class="mt-0.5 h-5 w-5 shrink-0 sm:mt-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>

        <p class="flex-1 text-sm font-medium">
            {{ session('subscription_warning') }}
            <a href="{{ route('subscription.plans') }}" class="ml-2 underline underline-offset-2 hover:text-red-100">
                View Plans →
            </a>
        </p>

        <button @click="show = false" class="ml-auto shrink-0 rounded p-1 hover:bg-red-500" aria-label="Dismiss">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>
@endif

{{-- ── Expiry warning ───────────────────────────────────────── --}}
@if (session('subscription_expiry_warning'))
    @php $warn = session('subscription_expiry_warning'); @endphp
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition
        class="relative flex items-start gap-4 bg-amber-500 px-4 py-3 text-white sm:items-center sm:px-6"
        role="alert"
    >
        <svg class="mt-0.5 h-5 w-5 shrink-0 sm:mt-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>

        <p class="flex-1 text-sm font-medium">
            {{ $warn['message'] }} Renew before <strong>{{ $warn['ends_at'] }}</strong> to avoid interruption.
            <a href="{{ $warn['renew_url'] }}" class="ml-2 underline underline-offset-2 hover:text-amber-100">
                Renew Now →
            </a>
        </p>

        <button @click="show = false" class="ml-auto shrink-0 rounded p-1 hover:bg-amber-400" aria-label="Dismiss">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>
@endif

{{-- ── Upgrade prompt (feature not on current plan) ────────── --}}
@if (session('upgrade_prompt'))
    @php $prompt = session('upgrade_prompt'); @endphp
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition
        class="relative flex items-start gap-4 bg-indigo-600 px-4 py-3 text-white sm:items-center sm:px-6"
        role="alert"
    >
        <svg class="mt-0.5 h-5 w-5 shrink-0 sm:mt-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
        </svg>

        <p class="flex-1 text-sm font-medium">
            {{ $prompt['message'] }}
            <a href="{{ route('subscription.plans') }}" class="ml-2 underline underline-offset-2 hover:text-indigo-100">
                Upgrade Plan →
            </a>
        </p>

        <button @click="show = false" class="ml-auto shrink-0 rounded p-1 hover:bg-indigo-500" aria-label="Dismiss">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>
@endif
