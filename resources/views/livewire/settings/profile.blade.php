<section class="w-full">
    @include('partials.settings-heading')
    <x-notifications />

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        {{-- ── School association ─────────────────────────────── --}}
        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-6 mt-2 space-y-4">
            <div>
                <flux:heading size="sm">Your School</flux:heading>
                <flux:subheading>Link your account to your school using its EMIS number.</flux:subheading>
            </div>

            {{-- Currently linked school --}}
            @if ($this->currentSchool)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-4 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <p class="font-semibold text-emerald-900 dark:text-emerald-200">{{ $this->currentSchool->name }}</p>
                        <p class="text-sm text-emerald-600 dark:text-emerald-400 mt-0.5 flex items-center gap-3 flex-wrap">
                            <span class="font-mono font-semibold">EMIS: {{ $this->currentSchool->emis_number }}</span>
                            <span>&middot; {{ $this->currentSchool->district }}, {{ $this->currentSchool->province }}</span>
                            <span>&middot; {{ $this->currentSchool->type_label }}</span>
                        </p>
                    </div>
                    <flux:button wire:click="unlinkSchool" size="sm" variant="ghost" class="text-red-500 shrink-0">
                        Unlink
                    </flux:button>
                </div>

            {{-- No school yet — search / create --}}
            @else
                <div class="space-y-3">

                    {{-- Search bar --}}
                    @if (!$showSchoolCreate)
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <flux:input
                                    wire:model.live.debounce.300ms="schoolSearch"
                                    placeholder="Search by EMIS number or school name…"
                                    icon="magnifying-glass"
                                />
                            </div>
                        </div>

                        {{-- Live results --}}
                        @if (strlen($schoolSearch) >= 2)
                            @if ($this->schoolSearchResults->isNotEmpty())
                                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-700">
                                    @foreach ($this->schoolSearchResults as $school)
                                        <button
                                            wire:click="assignSchool({{ $school->id }})"
                                            type="button"
                                            class="w-full text-left px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
                                        >
                                            <p class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">{{ $school->name }}</p>
                                            <p class="text-xs text-zinc-400 mt-0.5">
                                                EMIS: <span class="font-mono font-semibold">{{ $school->emis_number }}</span>
                                                &middot; {{ $school->district }}, {{ $school->province }}
                                                &middot; {{ $school->type_label }}
                                            </p>
                                        </button>
                                    @endforeach
                                    <div class="px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800">
                                        <button wire:click="$set('showSchoolCreate', true)" type="button" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            My school isn't listed — add it
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 px-4 py-3 text-sm text-amber-800 dark:text-amber-300 flex items-center justify-between">
                                    <span>No school found for "{{ $schoolSearch }}".</span>
                                    <button wire:click="$set('showSchoolCreate', true)" type="button" class="underline font-medium ml-2 shrink-0">
                                        Add it
                                    </button>
                                </div>
                            @endif
                        @endif
                    @endif

                    {{-- Create new school form --}}
                    @if ($showSchoolCreate)
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50/40 dark:bg-indigo-900/10 p-5 space-y-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-indigo-800 dark:text-indigo-300">Add your school to the directory</p>
                                <button wire:click="$set('showSchoolCreate', false)" type="button" class="text-xs text-slate-400 underline">Cancel</button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <flux:input
                                    wire:model="newEmis"
                                    label="EMIS Number"
                                    placeholder="e.g. 101001"
                                    :error="$errors->first('newEmis')"
                                />
                                <flux:input
                                    wire:model="newSchoolName"
                                    label="School Name"
                                    placeholder="e.g. Munali Secondary School"
                                    :error="$errors->first('newSchoolName')"
                                />
                                <flux:select wire:model="newProvince" label="Province" :error="$errors->first('newProvince')">
                                    <option value="">Select province…</option>
                                    @foreach(\App\Models\School::PROVINCES as $province)
                                        <option value="{{ $province }}">{{ $province }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input
                                    wire:model="newDistrict"
                                    label="District"
                                    placeholder="e.g. Lusaka"
                                    :error="$errors->first('newDistrict')"
                                />
                                <div class="sm:col-span-2">
                                    <flux:select wire:model="newType" label="School Type" :error="$errors->first('newType')">
                                        <option value="">Select type…</option>
                                        @foreach(\App\Models\School::TYPES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>

                            <flux:button wire:click="createSchool" variant="primary" size="sm">
                                Add School &amp; Link My Account
                            </flux:button>
                        </div>
                    @endif

                    @if (!$showSchoolCreate && strlen($schoolSearch) < 2)
                        <p class="text-xs text-zinc-400">
                            Type at least 2 characters to search. Can't find your school?
                            <button wire:click="$set('showSchoolCreate', true)" type="button" class="underline hover:text-zinc-600">Add it to the directory.</button>
                        </p>
                    @endif
                </div>
            @endif
        </div>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
