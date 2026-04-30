<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6"
              x-data="schoolPicker()" x-init="init()">
            @csrf

            {{-- Name --}}
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            {{-- Email --}}
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            {{-- Password --}}
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                viewable
            />

            {{-- Confirm Password --}}
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                viewable
            />

            {{-- ── School section ──────────────────────────────── --}}
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <div class="flex-1 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                    <span class="text-xs text-zinc-400 font-medium uppercase tracking-wide">Your School</span>
                    <div class="flex-1 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Enter your school's EMIS number to find it, or add it if it's not listed yet.
                    You can also skip this and set it up later.
                </p>

                {{-- EMIS search bar --}}
                <div x-show="!found && !creating" class="flex gap-2">
                    <div class="flex-1">
                        <flux:input
                            x-model="emisQuery"
                            placeholder="Enter EMIS number or school name…"
                            :label="__('EMIS Number / School Name')"
                            @keydown.enter.prevent="search"
                        />
                    </div>
                    <div class="flex items-end">
                        <flux:button type="button" @click="search">
                            <span x-show="!searching">Find</span>
                            <span x-show="searching">…</span>
                        </flux:button>
                    </div>
                </div>

                {{-- Search results --}}
                <div x-show="results.length > 0 && !found && !creating" class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <p class="px-3 py-2 text-xs font-semibold text-zinc-400 uppercase tracking-wide bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                        Select your school
                    </p>
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700 max-h-48 overflow-y-auto">
                        <template x-for="school in results" :key="school.id">
                            <button
                                type="button"
                                @click="selectSchool(school)"
                                class="w-full text-left px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
                            >
                                <p class="font-semibold text-sm text-zinc-800 dark:text-zinc-200" x-text="school.name"></p>
                                <p class="text-xs text-zinc-400 mt-0.5">
                                    EMIS: <span class="font-mono font-semibold" x-text="school.emis_number"></span>
                                    &middot; <span x-text="school.district"></span>, <span x-text="school.province"></span>
                                </p>
                            </button>
                        </template>
                    </div>
                    <div class="px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700">
                        <button type="button" @click="startCreating()" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                            My school isn't listed — add it
                        </button>
                    </div>
                </div>

                {{-- No results nudge --}}
                <div x-show="searched && results.length === 0 && !found && !creating"
                     class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    No school found for "<span class="font-semibold" x-text="emisQuery"></span>".
                    <button type="button" @click="startCreating()" class="ml-1 underline font-medium">Add your school</button>
                </div>

                {{-- Selected school confirmation --}}
                <div x-show="found" class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-emerald-900 dark:text-emerald-200 text-sm" x-text="selectedSchool?.name"></p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">
                            EMIS: <span class="font-mono font-semibold" x-text="selectedSchool?.emis_number"></span>
                            &middot; <span x-text="selectedSchool?.district"></span>, <span x-text="selectedSchool?.province"></span>
                        </p>
                    </div>
                    <button type="button" @click="reset()" class="text-xs text-emerald-700 underline shrink-0">Change</button>
                </div>

                {{-- Add new school form --}}
                <div x-show="creating" class="rounded-xl border border-indigo-200 bg-indigo-50/40 dark:bg-indigo-900/10 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-indigo-800 dark:text-indigo-300">Add your school</p>
                        <button type="button" @click="reset()" class="text-xs text-indigo-600 underline">Cancel</button>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">EMIS Number <span class="text-red-500">*</span></label>
                        <input
                            x-model="newSchool.emis_number"
                            type="text"
                            placeholder="e.g. 101001"
                            class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">School Name <span class="text-red-500">*</span></label>
                        <input
                            x-model="newSchool.name"
                            type="text"
                            placeholder="e.g. Munali Secondary School"
                            class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">Province <span class="text-red-500">*</span></label>
                            <select
                                x-model="newSchool.province"
                                class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="">Select…</option>
                                @foreach(\App\Models\School::PROVINCES as $province)
                                    <option value="{{ $province }}">{{ $province }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">District <span class="text-red-500">*</span></label>
                            <input
                                x-model="newSchool.district"
                                type="text"
                                placeholder="e.g. Lusaka"
                                class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">School Type <span class="text-red-500">*</span></label>
                        <select
                            x-model="newSchool.type"
                            class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">Select…</option>
                            @foreach(\App\Models\School::TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Skip hint --}}
                <p x-show="!found && !creating" class="text-xs text-zinc-400 text-center">
                    No EMIS number to hand?
                    <button type="button" @click="skip()" class="underline hover:text-zinc-600">Skip for now</button>
                </p>
            </div>

            {{-- Hidden fields submitted with the form --}}
            <input type="hidden" name="school_id"          x-bind:value="selectedSchool?.id ?? ''">
            <input type="hidden" name="new_emis_number"    x-bind:value="creating ? newSchool.emis_number : ''">
            <input type="hidden" name="new_school_name"    x-bind:value="creating ? newSchool.name : ''">
            <input type="hidden" name="new_school_province" x-bind:value="creating ? newSchool.province : ''">
            <input type="hidden" name="new_school_district" x-bind:value="creating ? newSchool.district : ''">
            <input type="hidden" name="new_school_type"    x-bind:value="creating ? newSchool.type : ''">

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Create account') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>

<script>
function schoolPicker() {
    return {
        emisQuery:      '',
        results:        [],
        searching:      false,
        searched:       false,
        found:          false,
        creating:       false,
        selectedSchool: null,
        newSchool: {
            emis_number: '',
            name:        '',
            province:    '',
            district:    '',
            type:        '',
        },

        init() {
            // Pre-fill EMIS query if old() value for emis was present (on validation fail)
            const oldEmis = '{{ old('new_emis_number') }}';
            if (oldEmis) {
                this.newSchool.emis_number = oldEmis;
                this.newSchool.name        = '{{ old('new_school_name') }}';
                this.newSchool.province    = '{{ old('new_school_province') }}';
                this.newSchool.district    = '{{ old('new_school_district') }}';
                this.newSchool.type        = '{{ old('new_school_type') }}';
                this.creating = true;
            }
        },

        async search() {
            if (this.emisQuery.trim().length < 2) return;
            this.searching = true;
            this.searched  = false;
            this.results   = [];

            try {
                const res = await fetch(`/schools/search?q=${encodeURIComponent(this.emisQuery)}`);
                this.results  = await res.json();
                this.searched = true;
            } catch (e) {
                console.error(e);
            } finally {
                this.searching = false;
            }
        },

        selectSchool(school) {
            this.selectedSchool = school;
            this.found          = true;
            this.results        = [];
            this.creating       = false;
        },

        startCreating() {
            this.creating       = true;
            this.found          = false;
            this.results        = [];
            this.selectedSchool = null;
            // Pre-fill EMIS from what they typed
            if (!this.newSchool.emis_number) {
                this.newSchool.emis_number = this.emisQuery;
            }
        },

        skip() {
            this.reset();
        },

        reset() {
            this.found          = false;
            this.creating       = false;
            this.searched       = false;
            this.results        = [];
            this.selectedSchool = null;
            this.emisQuery      = '';
            this.newSchool      = { emis_number: '', name: '', province: '', district: '', type: '' };
        },
    }
}
</script>
