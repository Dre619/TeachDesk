<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @if(session('impersonating_id'))
        <div class="bg-amber-500 text-white px-5 py-2.5 flex items-center justify-between gap-4 text-sm font-medium sticky top-0 z-50">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Viewing as <strong>{{ session('impersonating_name') }}</strong></span>
            </div>
            <form method="POST" action="{{ route('admin.impersonate.stop') }}">
                @csrf
                <button type="submit" class="flex items-center gap-1.5 bg-white/20 hover:bg-white/30 transition-colors px-3 py-1 rounded-lg text-xs font-semibold">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Exit View
                </button>
            </form>
        </div>
        @endif

        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
