<div class="bg-surface border border-border rounded-2xl p-6" data-dashboard-sections>
    <h3 class="text-sm font-semibold text-ink">Secțiuni dashboard</h3>
    <p class="mt-1 text-sm text-ink-soft leading-relaxed">
        Ce secțiuni apar pe pagina principală a panoului și în ce ordine. Se salvează pe loc. Secțiunile noi, adăugate în aplicație, apar aici active implicit.
    </p>

    <div class="mt-4 space-y-2" wire:sort.ghost="reorder">
        @foreach ($sections as $s)
            <div wire:key="dash-{{ $s['key'] }}" wire:sort:item="{{ $s['key'] }}" data-section="{{ $s['key'] }}"
                 class="flex items-center gap-2 rounded-lg border border-border bg-surface px-2 py-2 md:px-3">

                <span wire:sort:handle class="hidden md:flex items-center justify-center w-6 h-6 shrink-0 text-ink-soft/40 cursor-grab active:cursor-grabbing" title="Trage ca să reordonezi">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></svg>
                </span>
                <div class="flex md:hidden flex-col shrink-0">
                    <button type="button" wire:click="moveUp('{{ $s['key'] }}')" @if ($loop->first) disabled @endif
                            class="inline-flex items-center justify-center w-6 h-5 rounded text-ink-soft/60 hover:text-ink hover:bg-bg disabled:opacity-30 disabled:pointer-events-none" aria-label="Mută mai sus">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                    </button>
                    <button type="button" wire:click="moveDown('{{ $s['key'] }}')" @if ($loop->last) disabled @endif
                            class="inline-flex items-center justify-center w-6 h-5 rounded text-ink-soft/60 hover:text-ink hover:bg-bg disabled:opacity-30 disabled:pointer-events-none" aria-label="Mută mai jos">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                </div>

                <div class="flex-1 min-w-0 {{ $s['enabled'] ? '' : 'opacity-60' }}">
                    <span class="block text-sm font-medium text-ink truncate">
                        {{ $s['label'] }}
                        @if ($s['superadmin_only'])
                            <span class="ml-1 inline-flex items-center rounded-full bg-bg text-ink-soft text-[10px] font-medium px-1.5 py-0.5">doar superadmin</span>
                        @endif
                    </span>
                    <span class="block text-xs text-ink-soft truncate">{{ $s['description'] }}</span>
                </div>

                {{-- Activ / inactiv --}}
                <button type="button" wire:click="toggle('{{ $s['key'] }}')" role="switch" aria-checked="{{ $s['enabled'] ? 'true' : 'false' }}"
                        aria-label="{{ $s['enabled'] ? 'Dezactivează' : 'Activează' }} secțiunea {{ $s['label'] }}" data-toggle="{{ $s['key'] }}"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors {{ $s['enabled'] ? 'bg-primary' : 'bg-border' }}">
                    <span class="inline-block h-[18px] w-[18px] transform rounded-full bg-white shadow transition-transform {{ $s['enabled'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>
        @endforeach
    </div>
</div>
