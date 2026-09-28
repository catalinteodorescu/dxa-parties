<div
    class="bg-surface border border-border rounded-2xl p-6"
    x-data="{
        confirmOpen: false,
        confirmName: '',
        confirmId: null,
        ask(id, name) { this.confirmId = id; this.confirmName = name; this.confirmOpen = true; },
        run() { this.$wire.call('delete', this.confirmId); this.confirmOpen = false; },
    }"
>
    <h3 class="text-sm font-semibold text-ink">Categorii meniu bar</h3>
    <p class="mt-1 text-sm text-ink-soft leading-relaxed">
        Ordinea de aici se folosește în lista de meniu (grupată) și în selectul din formularul de produs.
        Categoriile noi se adaugă din butonul „+” de lângă categorie, la crearea sau modificarea unui produs.
    </p>

    <div class="mt-4 space-y-3">
        @if ($message)
            <x-alert type="success" dismiss-prop="message" :dismiss-value="null">{{ $message }}</x-alert>
        @endif
        @if ($error)
            <x-alert type="error" dismiss-prop="error" :dismiss-value="null">{{ $error }}</x-alert>
        @endif
    </div>

    {{-- wire:sort.ghost: același mecanism ca în restul proiectului (vezi .sortable-ghost în app.css) --}}
    <div class="mt-4 space-y-2" wire:sort.ghost="reorder">
        @forelse ($categories as $c)
            <div wire:key="cat-{{ $c->id }}" wire:sort:item="{{ $c->id }}"
                 class="flex items-center gap-2 rounded-lg border border-border bg-surface px-2 py-1 md:px-3">

                {{-- Mâner drag (desktop) / săgeți (mobil) --}}
                <span wire:sort:handle class="hidden md:flex items-center justify-center w-6 h-6 shrink-0 text-ink-soft/40 cursor-grab active:cursor-grabbing" title="Trage ca să reordonezi">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></svg>
                </span>
                <div class="flex md:hidden flex-col shrink-0">
                    <button type="button" wire:click="moveUp({{ $c->id }})" @if ($loop->first) disabled @endif
                            class="inline-flex items-center justify-center w-6 h-5 rounded text-ink-soft/60 hover:text-ink hover:bg-bg disabled:opacity-30 disabled:pointer-events-none">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                    </button>
                    <button type="button" wire:click="moveDown({{ $c->id }})" @if ($loop->last) disabled @endif
                            class="inline-flex items-center justify-center w-6 h-5 rounded text-ink-soft/60 hover:text-ink hover:bg-bg disabled:opacity-30 disabled:pointer-events-none">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                </div>

                {{-- Nume editabil inline (Enter sau părăsirea câmpului = salvează) --}}
                <input type="text" wire:model="names.{{ $c->id }}" wire:change="rename({{ $c->id }})"
                       x-on:keydown.enter.prevent="$el.blur()" maxlength="120" aria-label="Denumire categorie"
                       class="flex-1 min-w-0 rounded-lg border border-transparent hover:border-border bg-transparent px-2.5 py-1 text-sm font-medium focus:outline-none focus:bg-white focus:ring-2 focus:ring-primary/40 focus:border-primary {{ $c->is_active ? 'text-ink' : 'text-ink-soft' }}">

                {{-- Nr. produse (în dreapta, înainte de acțiuni) --}}
                <span class="shrink-0 text-xs text-ink-soft/70 whitespace-nowrap">{{ $c->items_count }} {{ $c->items_count === 1 ? 'produs' : 'produse' }}</span>

                {{-- Acțiuni --}}
                <div class="flex items-center gap-2 shrink-0">
                    <x-btn variant="info" size="icon" outline
                           tooltip="{{ $c->is_active ? 'Ascunde din meniu' : 'Afișează în meniu' }}"
                           wire:click="toggleActive({{ $c->id }})">
                        @if ($c->is_active)
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                        @else
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                        @endif
                    </x-btn>

                    @if ($c->items_count > 0)
                        <x-btn variant="danger" size="icon" outline disabled
                               tooltip="Nu poți șterge: are produse"
                               class="opacity-40 cursor-not-allowed">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </x-btn>
                    @else
                        <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                               x-on:click="ask({{ $c->id }}, '{{ addslashes($c->name) }}')">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </x-btn>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-ink-soft">
                Nicio categorie încă. Adaug-o din formularul de produs, cu butonul „+” de lângă categorie.
            </div>
        @endforelse
    </div>

    {{-- Confirmare ștergere --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Șterge categorie</h3>
            <p class="mt-2 text-sm text-ink-soft">Sigur vrei să ștergi categoria „<span x-text="confirmName"></span>”? Acțiunea nu poate fi anulată.</p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="confirmOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="danger" x-on:click="run()">Șterge</x-btn>
            </div>
        </div>
    </div>
</div>
