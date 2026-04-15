<div class="space-y-0.5">

    {{-- "All Classes" top-level link --}}
    <flux:sidebar.item
        icon="academic-cap"
        :href="route('user.class.rooms')"
        :current="request()->routeIs('user.class.rooms')"
        wire:navigate
    >
        {{ __('All Classes') }}
    </flux:sidebar.item>

    {{-- Expandable class tree --}}
    @forelse($classes as $class)
        @php $isOwned = $class->user_id === $userId; @endphp

        <div
            wire:key="sidebar-class-{{ $class->id }}"
            x-data="{
                open: false,
                init() {
                    const stored = localStorage.getItem('sc_open_{{ $class->id }}');
                    this.open = stored !== null
                        ? stored === 'true'
                        : {{ $currentClassId == $class->id ? 'true' : 'false' }};
                },
                toggle() {
                    this.open = !this.open;
                    localStorage.setItem('sc_open_{{ $class->id }}', this.open);
                }
            }"
        >
            {{-- Class row (click to expand / collapse) --}}
            <button
                @click="toggle()"
                type="button"
                class="group flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                :class="open ? 'bg-zinc-100 dark:bg-zinc-800' : ''"
            >
                @if($isOwned)
                    <svg class="size-4 shrink-0 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 3.741-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                    </svg>
                @else
                    <svg class="size-4 shrink-0 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                @endif

                <span class="min-w-0 flex-1 truncate text-left text-xs leading-tight">
                    {{ $class->name }}
                    <span class="block text-[10px] font-normal text-zinc-400 dark:text-zinc-500">{{ $class->academic_year }}</span>
                </span>

                @if(! $isOwned)
                    <span class="shrink-0 rounded bg-amber-100 px-1 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">
                        invited
                    </span>
                @endif

                <svg x-show="!open" class="size-3 shrink-0 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <svg x-show="open" class="size-3 shrink-0 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            {{-- Sub-items --}}
            <div
                x-show="open"
                x-transition:enter="transition-all duration-150 ease-out"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition-all duration-100 ease-in"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="ml-3 mt-0.5 mb-1 space-y-0.5 border-l border-zinc-200 pl-3 dark:border-zinc-700"
            >
                @php
                    $activeBase = 'flex items-center gap-2 rounded-md px-2 py-1 text-xs font-semibold text-zinc-900 bg-zinc-200 dark:bg-zinc-700 dark:text-white transition-colors';
                    $inactiveBase = 'flex items-center gap-2 rounded-md px-2 py-1 text-xs text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100 transition-colors';
                @endphp

                @if($isOwned)
                    {{-- Students --}}
                    @php $isActive = request()->routeIs('user.students') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.students', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        Students
                    </a>

                    {{-- Lesson Plans --}}
                    @php $isActive = request()->routeIs('user.lesson.plans') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.lesson.plans', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-violet-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        Lesson Plans
                    </a>

                    {{-- Assessments --}}
                    @php $isActive = request()->routeIs('user.assesments') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.assesments', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                        Assessments
                    </a>

                    {{-- Attendance --}}
                    @php $isActive = request()->routeIs('user.attendance') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.attendance', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                        </svg>
                        Attendance
                    </a>

                    {{-- Reports --}}
                    @php $isActive = request()->routeIs('user.reports') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.reports', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        Reports
                    </a>

                    {{-- Analytics --}}
                    @php $isActive = request()->routeIs('user.analytics') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.analytics', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        Analytics
                    </a>

                    {{-- Grade Book --}}
                    @php $isActive = request()->routeIs('user.gradebook') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.gradebook', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-teal-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125h-1.5m2.625-1.5v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375v-1.5m7.5-3.375v-1.5a1.125 1.125 0 0 0-1.125-1.125m-13.5 3V8.25m0 0H9m-2.625 0H5.625m0 0A1.125 1.125 0 0 0 4.5 9.375M6.75 8.25V6m0 2.25h7.5" />
                        </svg>
                        Grade Book
                    </a>

                    {{-- Behaviour Logs --}}
                    @php $isActive = request()->routeIs('user.behaviour-logs') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.behaviour-logs', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-rose-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        Behaviour Logs
                    </a>

                    {{-- Members --}}
                    @php $isActive = request()->routeIs('user.members') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.members', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-teal-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        Members
                    </a>

                @else
                    {{-- Invited: Assessments only --}}
                    @php $isActive = request()->routeIs('user.assesments') && $currentClassId == $class->id; @endphp
                    <a href="{{ route('user.assesments', $class->id) }}" wire:navigate class="{{ $isActive ? $activeBase : $inactiveBase }}">
                        <svg class="size-3.5 shrink-0 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                        Assessments
                    </a>
                @endif
            </div>
        </div>
    @empty
        <p class="px-2 py-2 text-xs text-zinc-400 dark:text-zinc-500">No classes yet.</p>
    @endforelse

    {{-- Older years toggle --}}
    @if($hasOlderClasses)
        <button
            wire:click="$toggle('showOlderYears')"
            type="button"
            class="mt-1 flex w-full items-center gap-1.5 rounded-md px-2 py-1 text-xs text-zinc-400 transition-colors hover:text-zinc-600 dark:hover:text-zinc-300"
        >
            <svg class="size-3 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            {{ $showOlderYears ? 'Hide older years' : 'Show older years' }}
        </button>
    @endif

</div>
