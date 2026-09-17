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
    @php
        $categoryOptions = ['all' => 'Toate categoriile'] + $categories->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->is_active ? '' : ' (ascunsă)')])->all();
        $hasFilters = $search !== '' || $category !== 'all' || $state !== 'all';
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-ink">Bar — Produse</h2>
            <p class="mt-1 text-sm text-ink-soft">Produsele afișate în meniul barului din app.</p>
        </div>
        <x-btn variant="primary" :href="route('admin.menu-items.create')" wire:navigate class="self-start">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Produs nou
        </x-btn>
    </div>

    {{-- Filtre + căutare --}}
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <div class="relative sm:flex-1 sm:min-w-48">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Caută după nume…"
                   class="w-full rounded-lg border border-border bg-white pl-9 pr-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
        </div>
        <x-select wire:model="category" live class="sm:w-48" :options="$categoryOptions" />
        <x-select wire:model="state" live class="sm:w-40"
                  :options="['all' => 'Toate stările', 'active' => 'Vizibile', 'inactive' => 'Ascunse']" />
        @if ($hasFilters)
            <button type="button" wire:click="clearFilters" class="text-sm text-ink-soft hover:text-ink px-2 py-2 self-start">
                Resetează
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-primary-soft text-primary text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-3">
        @php $lastCategoryId = null; @endphp
        @forelse ($items as $item)
            @if ($item->menu_category_id !== $lastCategoryId)
                @php $lastCategoryId = $item->menu_category_id; @endphp
                <h3 class="pt-2 first:pt-0 text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
                    {{ $item->category->name }}
                    @if (! $item->category->is_active)
                        <span class="normal-case font-normal text-ink-soft/50">(categorie ascunsă)</span>
                    @endif
                </h3>
            @endif

            <div wire:key="item-{{ $item->id }}"
                 class="rounded-2xl border border-border bg-surface p-3 md:p-4 flex flex-col gap-3 md:flex-row md:items-center md:gap-4">

                {{-- Imagine --}}
                <div class="shrink-0">
                    @if ($item->imageUrl())
                        <img src="{{ $item->imageUrl() }}" alt="" class="w-full h-40 md:w-20 md:h-20 object-cover rounded-xl border border-border">
                    @else
                        <div class="w-full h-40 md:w-20 md:h-20 rounded-xl border border-border bg-bg flex items-center justify-center text-ink-soft/40">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                            </svg>
                        </div>
                    @endif
                </div>

                {{-- Detalii --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-semibold text-ink truncate">{{ $item->name }}</h3>
                        @if ($item->is_active)
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-primary-soft text-primary">Vizibil</span>
                        @else
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-surface border border-border text-ink-soft">Ascuns</span>
                        @endif
                    </div>

                    @if ($item->description)
                        <p class="mt-0.5 text-sm text-ink-soft line-clamp-2 md:line-clamp-1">{{ $item->description }}</p>
                    @endif

                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        @if ($item->quantity)
                            <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">{{ $item->quantity }}</span>
                        @endif
                        <span class="inline-flex items-center rounded-full bg-bg text-ink font-medium text-xs px-2 py-0.5">
                            @if ($usesTokens)
                                {{ number_format($item->tokenPrice(), 1, ',', '.') }} tk
                                <span class="text-ink-soft/60 font-normal ml-1">· {{ number_format((float) $item->price, 2, ',', '.') }} lei</span>
                            @else
                                {{ number_format((float) $item->price, 2, ',', '.') }} lei
                            @endif
                        </span>
                    </div>
                </div>

                {{-- Acțiuni --}}
                <div class="flex items-center gap-2 flex-wrap md:flex-nowrap md:shrink-0">
                    <x-btn variant="info" size="icon" outline
                           tooltip="{{ $item->is_active ? 'Ascunde din meniu' : 'Afișează în meniu' }}"
                           wire:click="toggleActive({{ $item->id }})">
                        @if ($item->is_active)
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

                    <x-btn variant="warning" size="icon" outline tooltip="Editează" :href="route('admin.menu-items.edit', $item)" wire:navigate>
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                        </svg>
                    </x-btn>

                    <x-btn variant="purple" size="icon" outline tooltip="Duplică" wire:click="duplicate({{ $item->id }})">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="12" height="12" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </x-btn>

                    <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                           x-on:click="askConfirm('Șterge produs', 'Sigur vrei să ștergi produsul „{{ addslashes($item->name) }}”? Acțiunea nu poate fi anulată.', 'delete', [{{ $item->id }}])">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                        </svg>
                    </x-btn>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
                @if ($hasFilters)
                    Niciun produs care să corespundă filtrelor.
                @else
                    Niciun produs încă. Apasă „Produs nou" ca să adaugi primul.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $items->onEachSide(1)->links('pagination.dxa') }}
    </div>

    {{-- Modal de confirmare --}}
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
