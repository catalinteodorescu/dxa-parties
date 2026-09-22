<div
    x-data="{
        confirmOpen: false,
        confirmTitle: '',
        confirmMessage: '',
        confirmMethod: '',
        confirmArgs: [],
        askConfirm(title, message, method, args) {
            this.confirmTitle = title;
            this.confirmMessage = message;
            this.confirmMethod = method;
            this.confirmArgs = args;
            this.confirmOpen = true;
        },
        runConfirm() {
            this.$wire.call(this.confirmMethod, ...this.confirmArgs);
            this.confirmOpen = false;
        },
    }"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-ink">Bar — Categorii</h2>
            <p class="mt-1 text-sm text-ink-soft">Nomenclatorul de categorii pentru produsele din bar. Trage-le de mâner ca să le reordonezi.</p>
        </div>
        <x-btn variant="primary" wire:click="openCreate" class="self-start">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Categorie nouă
        </x-btn>
    </div>

    <x-flash class="mb-4" />

    {{-- Listă: antet de coloane slim (doar desktop) + fiecare categorie = card propriu, spațiat --}}
    <div>
        <div class="hidden md:grid grid-cols-[2.5rem_1fr_7rem_7rem_11rem] gap-3 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
            <span>Ordine</span>
            <span>Denumire</span>
            <span>Articole</span>
            <span>Stare</span>
            <span class="text-right">Acțiuni</span>
        </div>

        {{-- wire:sort.ghost: elementul tras se mută în listă pe măsură ce treci peste celelalte, iar locul lui
             (clasa .sortable-ghost, vezi app.css) rămâne vizibil ca placeholder până la eliberare. --}}
        <div class="space-y-3" wire:sort.ghost="reorder">
        @forelse ($categories as $c)
            <div wire:key="cat-{{ $c->id }}" wire:sort:item="{{ $c->id }}"
                 class="rounded-2xl border border-border bg-surface p-3 md:px-4 md:py-2.5 md:grid md:grid-cols-[2.5rem_1fr_7rem_7rem_11rem] md:items-center md:gap-3">

                {{-- Maner drag + up/down (mobil) --}}
                <div class="flex items-center gap-1 md:block">
                    <span wire:sort:handle class="hidden md:flex items-center justify-center w-6 h-6 text-ink-soft/40 cursor-grab active:cursor-grabbing" title="Trage ca să reordonezi">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.3"/><circle cx="15" cy="6" r="1.3"/><circle cx="9" cy="12" r="1.3"/><circle cx="15" cy="12" r="1.3"/><circle cx="9" cy="18" r="1.3"/><circle cx="15" cy="18" r="1.3"/></svg>
                    </span>

                    <div class="flex md:hidden gap-0.5">
                        <button type="button" wire:click="moveUp({{ $c->id }})"
                                class="inline-flex items-center justify-center w-7 h-7 rounded text-ink-soft/60 hover:text-ink hover:bg-bg disabled:opacity-30 disabled:pointer-events-none"
                                @if ($loop->first) disabled @endif>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
                        </button>
                        <button type="button" wire:click="moveDown({{ $c->id }})"
                                class="inline-flex items-center justify-center w-7 h-7 rounded text-ink-soft/60 hover:text-ink hover:bg-bg disabled:opacity-30 disabled:pointer-events-none"
                                @if ($loop->last) disabled @endif>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Denumire --}}
                <div class="mt-2 md:mt-0 min-w-0">
                    <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Denumire</span>
                    <span class="font-medium text-ink truncate">{{ $c->name }}</span>
                </div>

                {{-- Articole (contor) --}}
                <div class="mt-2 md:mt-0">
                    <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Articole</span>
                    <span class="text-sm text-ink-soft">{{ $c->items_count }}</span>
                </div>

                {{-- Stare --}}
                <div class="mt-2 md:mt-0">
                    <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Stare</span>
                    @if ($c->is_active)
                        <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-primary-soft text-primary">Vizibilă</span>
                    @else
                        <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-surface border border-border text-ink-soft">Ascunsă</span>
                    @endif
                </div>

                {{-- Acțiuni --}}
                <div class="mt-3 md:mt-0 flex items-center gap-2 md:justify-end">
                    <x-btn variant="info" size="icon" outline
                           tooltip="{{ $c->is_active ? 'Ascunde din meniu' : 'Afișează în meniu' }}"
                           wire:click="toggleActive({{ $c->id }})">
                        @if ($c->is_active)
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20C5 20 1 12 1 12a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <path d="M1 1l22 22"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                            </svg>
                        @else
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        @endif
                    </x-btn>

                    <x-btn variant="warning" size="icon" outline tooltip="Editează" wire:click="openEdit({{ $c->id }})">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                        </svg>
                    </x-btn>

                    <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                           x-on:click="askConfirm('Șterge categorie', 'Sigur vrei să ștergi categoria „{{ addslashes($c->name) }}”? Acțiunea nu poate fi anulată.', 'delete', [{{ $c->id }}])">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                        </svg>
                    </x-btn>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
                Nicio categorie încă. Apasă „Categorie nouă" ca să adaugi prima.
            </div>
        @endforelse
        </div>
    </div>

    {{-- Modal: creare/editare categorie --}}
    <div x-show="$wire.modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" wire:click="closeModal"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">{{ $editingId ? 'Editează categoria' : 'Categorie nouă' }}</h3>

            <div class="mt-4">
                <label class="block text-sm font-medium text-ink mb-1">Denumire</label>
                <input type="text" wire:model="name" placeholder="ex. Cocktailuri"
                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <label class="mt-4 flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="is_active"
                       class="w-4 h-4 rounded border-border text-primary focus:ring-primary/40">
                <span class="text-sm text-ink">Vizibilă în meniu</span>
            </label>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" wire:click="closeModal" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="primary" wire:click="save">Salvează</x-btn>
            </div>
        </div>
    </div>

    {{-- Modal de confirmare ștergere --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink" x-text="confirmTitle"></h3>
            <p class="mt-2 text-sm text-ink-soft" x-text="confirmMessage"></p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="confirmOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="danger" x-on:click="runConfirm()">Șterge</x-btn>
            </div>
        </div>
    </div>
</div>
