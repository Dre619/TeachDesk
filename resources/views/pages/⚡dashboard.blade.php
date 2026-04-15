<?php

use App\Models\Assessment;
use App\Models\ClassRoom;
use App\Models\ClassTransfer;
use App\Models\LessonPlan;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    // ──────────────────────────────────────────
    // Super-admin stats
    // ──────────────────────────────────────────

    #[Computed]
    public function totalUsers(): int
    {
        return User::where('role', '!=', 'super_admin')->count();
    }

    #[Computed]
    public function activeSubscriptions(): int
    {
        return Subscription::active()->count();
    }

    #[Computed]
    public function trialSubscriptions(): int
    {
        return Subscription::where('status', 'trial')
            ->where('ends_at', '>', now())
            ->count();
    }

    #[Computed]
    public function totalRevenue(): float
    {
        return (float) Payment::successful()->sum('amount_zmw');
    }

    #[Computed]
    public function recentUsers()
    {
        return User::where('role', '!=', 'super_admin')
            ->latest()
            ->take(7)
            ->get();
    }

    #[Computed]
    public function recentPayments()
    {
        return Payment::with(['user', 'plan'])
            ->successful()
            ->latest('paid_at')
            ->take(7)
            ->get();
    }

    #[Computed]
    public function subscriptionsByStatus(): array
    {
        return Subscription::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    #[Computed]
    public function planBreakdown()
    {
        return SubscriptionPlan::withCount([
            'subscriptions as active_count' => fn ($q) => $q->active(),
        ])->orderByDesc('active_count')->get();
    }

    // ──────────────────────────────────────────
    // User (teacher) stats
    // ──────────────────────────────────────────

    #[Computed]
    public function myClasses()
    {
        return ClassRoom::forTeacher(Auth::id())
            ->withCount(['students as active_students_count' => fn ($q) => $q->whereNull('deleted_at')])
            ->latest()
            ->get();
    }

    #[Computed]
    public function totalStudents(): int
    {
        return Student::where('user_id', Auth::id())
            ->whereNull('deleted_at')
            ->count();
    }

    #[Computed]
    public function assessmentsThisTerm(): int
    {
        return Assessment::where('user_id', Auth::id())
            ->where('academic_year', date('Y'))
            ->count();
    }

    #[Computed]
    public function mySubscription(): ?Subscription
    {
        return Auth::user()->activeSubscription()->with('plan')->first();
    }

    #[Computed]
    public function recentClasses()
    {
        return ClassRoom::forTeacher(Auth::id())
            ->withCount(['students as active_students_count' => fn ($q) => $q->whereNull('deleted_at')])
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function thisWeekPlans()
    {
        return LessonPlan::forTeacher(Auth::id())
            ->nonTemplates()
            ->where('week_number', now()->weekOfYear)
            ->where('academic_year', (int) now()->year)
            ->with('classRoom')
            ->orderBy('term')
            ->get();
    }

    #[Computed]
    public function recentAssessmentEntries()
    {
        return Assessment::where('user_id', Auth::id())
            ->with(['student', 'classRoom'])
            ->latest()
            ->take(6)
            ->get();
    }

    #[Computed]
    public function pendingIncomingTransfers()
    {
        return ClassTransfer::where('to_user_id', Auth::id())
            ->where('status', 'pending')
            ->with(['classroom', 'fromUser'])
            ->latest()
            ->get();
    }
};
?>

<div>
    @if(auth()->user()->role === 'super_admin')

    {{-- ══════════════════════════════════════════════════════════
         SUPER ADMIN DASHBOARD
    ══════════════════════════════════════════════════════════ --}}
    <div class="space-y-8 p-6">

        {{-- Page header --}}
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Admin Dashboard</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Platform overview at a glance.</p>
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

            {{-- Total users --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Total Teachers</p>
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/40">
                        <flux:icon name="users" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </span>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->totalUsers) }}</p>
                <p class="text-xs text-zinc-400 mt-1">Registered accounts</p>
            </div>

            {{-- Active subscriptions --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Active Subscriptions</p>
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40">
                        <flux:icon name="check-badge" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </span>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->activeSubscriptions) }}</p>
                <p class="text-xs text-zinc-400 mt-1">
                    <span class="text-amber-500 font-medium">{{ $this->trialSubscriptions }} on trial</span>
                </p>
            </div>

            {{-- Revenue --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Total Revenue</p>
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/40">
                        <flux:icon name="banknotes" class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </span>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">
                    ZMW {{ number_format($this->totalRevenue, 2) }}
                </p>
                <p class="text-xs text-zinc-400 mt-1">Successful payments</p>
            </div>

            {{-- Plans --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Active Plans</p>
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/40">
                        <flux:icon name="rectangle-stack" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </span>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">
                    {{ $this->planBreakdown->where('is_active', true)->count() }}
                </p>
                <p class="text-xs text-zinc-400 mt-1">Published pricing plans</p>
            </div>
        </div>

        {{-- Main content row --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Recent signups --}}
            <div class="lg:col-span-2 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <h2 class="font-semibold text-sm text-zinc-800 dark:text-zinc-100">Recent Sign-ups</h2>
                    <span class="text-xs text-zinc-400">Last 7</span>
                </div>
                <div class="divide-y divide-zinc-50 dark:divide-zinc-700/50">
                    @forelse($this->recentUsers as $user)
                        <div class="flex items-center gap-3 px-5 py-3">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-xs font-bold shrink-0">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100 truncate">{{ $user->name }}</p>
                                <p class="text-xs text-zinc-400 truncate">{{ $user->email }}</p>
                            </div>
                            <span class="text-xs text-zinc-400 shrink-0">{{ $user->created_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-zinc-400 text-center">No users yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Subscription breakdown --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
                <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <h2 class="font-semibold text-sm text-zinc-800 dark:text-zinc-100">Subscription Status</h2>
                </div>
                <div class="p-5 space-y-3">
                    @foreach([
                        'active'    => ['label' => 'Active',    'color' => 'bg-emerald-500'],
                        'trial'     => ['label' => 'Trial',     'color' => 'bg-amber-400'],
                        'expired'   => ['label' => 'Expired',   'color' => 'bg-red-400'],
                        'cancelled' => ['label' => 'Cancelled', 'color' => 'bg-zinc-400'],
                    ] as $status => $cfg)
                    @php $count = $this->subscriptionsByStatus[$status] ?? 0; @endphp
                    <div class="flex items-center gap-3">
                        <span class="w-2.5 h-2.5 rounded-full {{ $cfg['color'] }} shrink-0"></span>
                        <span class="text-sm text-zinc-600 dark:text-zinc-300 flex-1">{{ $cfg['label'] }}</span>
                        <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $count }}</span>
                    </div>
                    @endforeach
                </div>

                {{-- Plan breakdown --}}
                <div class="px-5 pb-5 border-t border-zinc-100 dark:border-zinc-700 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 mb-3">By Plan</p>
                    <div class="space-y-2">
                        @foreach($this->planBreakdown as $plan)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-300 truncate">{{ $plan->name }}</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-100 ml-2">{{ $plan->active_count }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent payments --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                <h2 class="font-semibold text-sm text-zinc-800 dark:text-zinc-100">Recent Payments</h2>
                <span class="text-xs text-zinc-400">Last 7 successful</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-400 border-b border-zinc-100 dark:border-zinc-700">
                            <th class="px-5 py-3">Teacher</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3">Method</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-50 dark:divide-zinc-700/50">
                        @forelse($this->recentPayments as $payment)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-5 py-3 text-zinc-700 dark:text-zinc-300 font-medium">{{ $payment->user->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-zinc-500 dark:text-zinc-400">{{ $payment->plan->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 uppercase">
                                    {{ $payment->method }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                ZMW {{ number_format($payment->amount_zmw, 2) }}
                            </td>
                            <td class="px-5 py-3 text-right text-zinc-400 text-xs">
                                {{ $payment->paid_at?->format('d M Y') ?? '—' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-zinc-400">No payments yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    @else

    {{-- ══════════════════════════════════════════════════════════
         TEACHER / USER DASHBOARD
    ══════════════════════════════════════════════════════════ --}}
    <div class="space-y-8 p-6">

        {{-- ── No school set up yet ────────────────────────── --}}
        @if(! auth()->user()->school_id)
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl px-5 py-4 flex items-center gap-4">
            <div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 3.741-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-indigo-900">Your account isn't linked to a school yet</p>
                <p class="text-sm text-indigo-700 mt-0.5">Link your account to your school so your data can be associated correctly.</p>
            </div>
            <a href="{{ route('profile.edit') }}" class="shrink-0 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                Set up school
            </a>
        </div>
        @endif

        {{-- ── Incoming transfer requests ───────────────────── --}}
        @if ($this->pendingIncomingTransfers->isNotEmpty())
            <div class="space-y-3">
                @foreach ($this->pendingIncomingTransfers as $transfer)
                <div class="bg-amber-50 border border-amber-300 rounded-xl px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-5 h-5 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-amber-900 leading-tight">
                                Class transfer request
                            </p>
                            <p class="text-sm text-amber-700 mt-0.5">
                                <strong>{{ $transfer->fromUser->name }}</strong> wants to transfer
                                <strong>{{ $transfer->classroom->name }}</strong>
                                ({{ $transfer->classroom->subject }} · {{ $transfer->classroom->academic_year }}) to you.
                            </p>
                            @if ($transfer->message)
                                <p class="text-xs text-amber-600 mt-1 italic">"{{ $transfer->message }}"</p>
                            @endif
                            <p class="text-xs text-amber-500 mt-1">Sent {{ $transfer->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a
                            href="{{ route('class-transfer.decline', $transfer->token) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-amber-300 bg-white text-amber-700 text-sm font-medium hover:bg-amber-50 transition-colors"
                        >
                            Decline
                        </a>
                        <a
                            href="{{ route('class-transfer.accept', $transfer->token) }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold transition-colors"
                        >
                            Accept Transfer
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        @endif

        {{-- Page header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
                    Welcome back, {{ auth()->user()->first_name }} 👋
                </h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                    Here's what's happening in your classes.
                </p>
            </div>
            <flux:button :href="route('user.class.rooms')" icon="academic-cap" wire:navigate>
                My Classes
            </flux:button>
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

            {{-- Classes --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Classes</p>
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/40">
                        <flux:icon name="academic-cap" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </span>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $this->myClasses->count() }}</p>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ $this->myClasses->where('user_id', auth()->id())->count() }} owned &middot;
                    {{ $this->myClasses->where('user_id', '!=', auth()->id())->count() }} shared
                </p>
            </div>

            {{-- Students --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Students</p>
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-900/40">
                        <flux:icon name="users" class="w-5 h-5 text-sky-600 dark:text-sky-400" />
                    </span>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->totalStudents) }}</p>
                <p class="text-xs text-zinc-400 mt-1">Across all your classes</p>
            </div>

            {{-- Assessments --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Assessments</p>
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/40">
                        <flux:icon name="clipboard-document-list" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </span>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->assessmentsThisTerm) }}</p>
                <p class="text-xs text-zinc-400 mt-1">Recorded in {{ date('Y') }}</p>
            </div>

            {{-- Subscription --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Subscription</p>
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40">
                        <flux:icon name="check-badge" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </span>
                </div>
                @if($this->mySubscription)
                    <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $this->mySubscription->daysRemaining() }}</p>
                    <p class="text-xs text-zinc-400 mt-1">
                        days left &middot;
                        <span class="font-medium text-indigo-600 dark:text-indigo-400">
                            {{ $this->mySubscription->plan->name ?? 'Plan' }}
                        </span>
                    </p>
                @else
                    <p class="text-sm font-semibold text-red-500 mt-2">No active plan</p>
                    <a href="{{ route('subscription.plans') }}" wire:navigate class="text-xs text-indigo-600 hover:underline mt-1 inline-block">
                        View plans →
                    </a>
                @endif
            </div>
        </div>

        {{-- Main content row --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Recent classes --}}
            <div class="lg:col-span-2 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <h2 class="font-semibold text-sm text-zinc-800 dark:text-zinc-100">Recent Classes</h2>
                    <a href="{{ route('user.class.rooms') }}" wire:navigate class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-medium">
                        View all →
                    </a>
                </div>
                <div class="divide-y divide-zinc-50 dark:divide-zinc-700/50">
                    @forelse($this->recentClasses as $class)
                    <div class="flex items-center gap-4 px-5 py-3.5">
                        <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center shrink-0">
                            <flux:icon name="academic-cap" class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 truncate">{{ $class->name }}</p>
                            <p class="text-xs text-zinc-400">
                                {{ $class->subject }} &middot; {{ $class->academic_year }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                                {{ $class->active_students_count }}
                            </p>
                            <p class="text-xs text-zinc-400">students</p>
                        </div>
                        @if($class->is_active)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-zinc-100 dark:bg-zinc-700 text-zinc-500 border border-zinc-200 dark:border-zinc-600 shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                                Archived
                            </span>
                        @endif
                    </div>
                    @empty
                    <div class="px-5 py-12 text-center">
                        <flux:icon name="academic-cap" class="w-10 h-10 text-zinc-300 mx-auto mb-3" />
                        <p class="text-sm text-zinc-400">No classes yet.</p>
                        <flux:button :href="route('user.class.rooms')" size="sm" class="mt-3" wire:navigate>
                            Create your first class
                        </flux:button>
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Quick actions + subscription card --}}
            <div class="space-y-5">

                {{-- Quick actions --}}
                <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-5">
                    <h2 class="font-semibold text-sm text-zinc-800 dark:text-zinc-100 mb-4">Quick Actions</h2>
                    <div class="space-y-2">
                        <a href="{{ route('user.class.rooms') }}" wire:navigate
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors group">
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/40 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-800/40 transition-colors">
                                <flux:icon name="academic-cap" class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                            </span>
                            <span class="text-sm text-zinc-700 dark:text-zinc-200 font-medium">Manage Classes</span>
                            <flux:icon name="chevron-right" class="w-4 h-4 text-zinc-300 ml-auto" />
                        </a>
                        <a href="{{ route('subscription.plans') }}" wire:navigate
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors group">
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-800/40 transition-colors">
                                <flux:icon name="credit-card" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                            </span>
                            <span class="text-sm text-zinc-700 dark:text-zinc-200 font-medium">Subscription Plans</span>
                            <flux:icon name="chevron-right" class="w-4 h-4 text-zinc-300 ml-auto" />
                        </a>
                        <a href="{{ route('profile.edit') }}" wire:navigate
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors group">
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700 group-hover:bg-zinc-200 dark:group-hover:bg-zinc-600 transition-colors">
                                <flux:icon name="cog-6-tooth" class="w-4 h-4 text-zinc-500 dark:text-zinc-400" />
                            </span>
                            <span class="text-sm text-zinc-700 dark:text-zinc-200 font-medium">Settings</span>
                            <flux:icon name="chevron-right" class="w-4 h-4 text-zinc-300 ml-auto" />
                        </a>
                    </div>
                </div>

                {{-- Subscription details --}}
                @if($this->mySubscription)
                <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 dark:from-indigo-700 dark:to-indigo-900 rounded-2xl p-5 text-white shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Your Plan</p>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            {{ ucfirst($this->mySubscription->status) }}
                        </span>
                    </div>
                    <p class="text-xl font-bold mb-1">{{ $this->mySubscription->plan->name ?? 'Plan' }}</p>
                    <p class="text-indigo-200 text-sm">
                        Expires {{ $this->mySubscription->ends_at->format('d M Y') }}
                    </p>
                    @if($this->mySubscription->isExpiringSoon())
                    <div class="mt-3 flex items-center gap-2 text-xs bg-white/10 rounded-lg px-3 py-2">
                        <flux:icon name="exclamation-triangle" class="w-4 h-4 text-amber-300 shrink-0" />
                        <span class="text-white">{{ $this->mySubscription->daysRemaining() }} days left — renew soon</span>
                    </div>
                    @endif
                </div>
                @endif

            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             SECOND ROW — This week + Recent assessments
        ══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- This week's lesson plans --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <div>
                        <h2 class="font-semibold text-sm text-zinc-800 dark:text-zinc-100">This Week's Plans</h2>
                        <p class="text-xs text-zinc-400 mt-0.5">Week {{ now()->weekOfYear }} &middot; {{ now()->year }}</p>
                    </div>
                    @if($this->thisWeekPlans->isNotEmpty())
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 text-xs font-bold">
                            {{ $this->thisWeekPlans->count() }}
                        </span>
                    @endif
                </div>

                @if($this->thisWeekPlans->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <flux:icon name="document-text" class="w-9 h-9 text-zinc-300 mx-auto mb-2" />
                        <p class="text-sm text-zinc-400">No plans for this week yet.</p>
                        @if($this->myClasses->isNotEmpty())
                            <a href="{{ route('user.lesson.plans', $this->myClasses->first()->id) }}" wire:navigate
                               class="text-xs text-indigo-600 hover:underline mt-1 inline-block">
                                Create a lesson plan →
                            </a>
                        @endif
                    </div>
                @else
                    <div class="divide-y divide-zinc-50 dark:divide-zinc-700/50">
                        @foreach($this->thisWeekPlans as $plan)
                        <div class="flex items-center gap-3 px-5 py-3">
                            @php
                                $termDot = match($plan->term) {
                                    1 => 'bg-blue-500',
                                    2 => 'bg-amber-500',
                                    3 => 'bg-emerald-500',
                                    default => 'bg-zinc-400',
                                };
                            @endphp
                            <span class="w-2 h-2 rounded-full {{ $termDot }} shrink-0"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100 truncate">{{ $plan->title }}</p>
                                <p class="text-xs text-zinc-400 truncate">
                                    {{ $plan->subject }}
                                    @if($plan->classRoom) &middot; {{ $plan->classRoom->name }} @endif
                                    @if($plan->duration_label) &middot; {{ $plan->duration_label }} @endif
                                </p>
                            </div>
                            @if($plan->classRoom)
                                <a href="{{ route('user.lesson.plans', $plan->class_id) }}" wire:navigate
                                   class="text-xs text-indigo-500 hover:text-indigo-700 shrink-0 font-medium">
                                    Open →
                                </a>
                            @endif
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent assessment entries --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <h2 class="font-semibold text-sm text-zinc-800 dark:text-zinc-100">Recent Assessments</h2>
                    <span class="text-xs text-zinc-400">Last 6 entries</span>
                </div>

                @if($this->recentAssessmentEntries->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <flux:icon name="clipboard-document-list" class="w-9 h-9 text-zinc-300 mx-auto mb-2" />
                        <p class="text-sm text-zinc-400">No assessments recorded yet.</p>
                    </div>
                @else
                    <div class="divide-y divide-zinc-50 dark:divide-zinc-700/50">
                        @foreach($this->recentAssessmentEntries as $entry)
                        @php
                            $gradeBg = match($entry->grade) {
                                'A'     => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
                                'B'     => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
                                'C'     => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
                                'D','E' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400',
                                default => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
                            };
                        @endphp
                        <div class="flex items-center gap-3 px-5 py-3">
                            <div class="w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 flex items-center justify-center text-xs font-bold shrink-0">
                                {{ strtoupper(substr($entry->student->name ?? '?', 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100 truncate">
                                    {{ $entry->student->name ?? '—' }}
                                </p>
                                <p class="text-xs text-zinc-400 truncate">
                                    {{ $entry->subject }} &middot; {{ $entry->type_label }}
                                    @if($entry->classRoom) &middot; {{ $entry->classRoom->name }} @endif
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-xs font-bold {{ $gradeBg }}">
                                    {{ $entry->grade }}
                                </span>
                                <p class="text-xs text-zinc-400 mt-0.5">{{ $entry->percentage }}%</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

    </div>
    @endif
</div>

