<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        @include('partials.alerts')
        <livewire:notifications></livewire:notifications>
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                {{-- Common --}}
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                @if(auth()->user()->role === 'super_admin')
                {{-- Admin nav --}}
                <flux:sidebar.group heading="Admin" class="grid">
                    <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                        {{ __('Users') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('admin.subscriptions')" :current="request()->routeIs('admin.subscriptions')" wire:navigate>
                        {{ __('Subscriptions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('admin.payments')" :current="request()->routeIs('admin.payments')" wire:navigate>
                        {{ __('Payments') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="rectangle-stack" :href="route('admin.plans')" :current="request()->routeIs('admin.plans')" wire:navigate>
                        {{ __('Plans') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif

                @if(auth()->user()->role === 'user')
                {{-- Teacher nav --}}
                <flux:sidebar.group heading="Teaching" class="grid">
                    <livewire:sidebar-classes />
                </flux:sidebar.group>
                <flux:sidebar.group heading="Account" class="grid">
                    <flux:sidebar.item icon="credit-card" :href="route('subscription.plans')" :current="request()->routeIs('subscription.plans')" wire:navigate>
                        {{ __('Subscription Plans') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>
        {{ $slot }}

        @fluxScripts

        {{-- Refresh CSRF tokens in all forms after wire:navigate page swaps --}}
        <script>
            document.addEventListener('livewire:navigated', () => {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                if (token) {
                    document.querySelectorAll('input[name="_token"]').forEach(el => el.value = token);
                }
            });
        </script>
    </body>
</html>
