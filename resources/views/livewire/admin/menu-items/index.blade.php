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
            <h2 class="text-lg font-semibold text-ink">Bar — Meniu</h2>
            <p class="mt-1 text-sm text-ink-soft">Articolele afișate în meniul barului din app.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 self-start">
            <x-btn variant="neutral" outline :href="route('admin.menu-items.categories')" wire:navigate>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
                Categorii produse
            </x-btn>
            @permits('menu', 'edit')
            <x-btn variant="primary" :href="route('admin.menu-items.create')" wire:navigate>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                Produs nou
            </x-btn>
            @endpermits
        </div>
    </div>

    {{-- Filtre + căutare — pe ecrane mici (<md) sunt ascunse într-un toggle (deschis automat dacă există filtre active); de la md în sus sunt mereu vizibile, fără buton. --}}
    <div x-data="{ filtersOpen: {{ $hasFilters ? 'true' : 'false' }} }" class="mb-4">
        <button type="button" @click="filtersOpen = !filtersOpen"
                class="md:hidden inline-flex items-center gap-1.5 text-sm font-medium text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/></svg>
            Filtre
            @if ($hasFilters)
                <span class="inline-flex items-center rounded-full bg-primary-soft text-primary text-[10px] font-semibold px-1.5 py-0.5">active</span>
            @endif
            <svg class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div class="hidden md:block mt-2 md:mt-0" :class="filtersOpen ? 'max-md:block' : ''">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="relative sm:flex-1 sm:min-w-48">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Caută după nume…"
                       class="w-full rounded-lg border border-border bg-white pl-9 pr-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>
            <x-select wire:model="category" live class="sm:w-48" :options="$categoryOptions" />
            <x-select wire:model="state" live class="sm:w-40"
                      :options="['all' => 'Toate stările', 'active' => 'Vizibile', 'inactive' => 'Ascunse']" />
            <x-select wire:model="view" live class="sm:w-56" :options="$viewOptions" />

            @if ($hasFilters)
                <button type="button" wire:click="clearFilters" class="text-sm text-ink-soft hover:text-ink px-2 py-2 self-start">
                    Resetează
                </button>
            @endif
        </div>
        </div>
    </div>

    <x-flash class="mb-4" />

    <div class="space-y-3">
        @php $lastCategoryId = null; @endphp
        @forelse ($items as $item)
            @if ($grouped && $item->menu_category_id !== $lastCategoryId)
                @php $lastCategoryId = $item->menu_category_id; @endphp
                <h3 class="pt-2 first:pt-0 text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
                    {{ $item->category->name }}
                    @if (! $item->category->is_active)
                        <span class="normal-case font-normal text-ink-soft/50">(categorie ascunsă)</span>
                    @endif
                </h3>
            @endif

            {{-- Pe mobil (<md): grid cu text + poză inline (poza în dreapta, ~3 rânduri înălțime) și acțiunile pe rândul de dedesubt.
                 De la md: rând flex — poză (stânga) | detalii | acțiuni. --}}
            <div wire:key="item-{{ $item->id }}"
                 class="rounded-2xl border border-border bg-surface p-3 md:p-4 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-y-3 md:flex md:gap-4">

                {{-- Imagine: pe mobil doar dacă există (fără placeholder); pe desktop cu placeholder --}}
                @if ($item->imageUrl())
                    <div class="col-start-2 row-start-1 ml-3 md:ml-0 shrink-0">
                        <img src="{{ $item->imageUrl() }}" alt="" class="size-18 md:size-20 object-cover rounded-xl border border-border">
                    </div>
                @else
                    <div class="hidden md:flex shrink-0 size-20 rounded-xl border border-border bg-bg items-center justify-center text-ink-soft/40">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                    </div>
                @endif

                {{-- Detalii --}}
                <div class="col-start-1 row-start-1 min-w-0 md:flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-semibold text-ink truncate">{{ $item->name }}</h3>
                        @if ($item->is_active)
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-primary-soft text-primary">Vizibil</span>
                        @else
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-surface border border-border text-ink-soft">Ascuns</span>
                        @endif
                        @if (! $item->isAvailable())
                            <span class="inline-flex items-center gap-1 rounded-full text-xs font-medium px-2 py-0.5 bg-danger/10 text-danger" title="{{ $item->unavailabilityReason() }}">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                Indisponibil
                            </span>
                        @endif
                    </div>

                    @if ($item->description)
                        <p class="mt-0.5 text-sm text-ink-soft line-clamp-1">{{ $item->description }}</p>
                    @endif

                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        @unless ($grouped)
                            <span class="inline-flex items-center rounded-full border border-border text-ink-soft text-xs px-2 py-0.5">{{ $item->category->name }}</span>
                        @endunless
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

                        {{-- Cost din rețetă + marjă (vezi MenuItem::costPerUnit/marginAmount/marginPercent) --}}
                        @if ($item->isTracked())
                            @php
                                $cost = $item->costPerUnit();
                                $margin = $item->marginAmount();
                                $marginPct = $item->marginPercent();
                            @endphp
                            @if ($cost !== null)
                                <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">
                                    cost {{ number_format($cost, 2, ',', '.') }} lei
                                    @if ($margin !== null)
                                        <span class="mx-1 text-ink-soft/40">·</span>
                                        marjă {{ number_format($margin, 2, ',', '.') }} lei ({{ number_format($marginPct, 1, ',', '.') }}%)
                                    @endif
                                </span>
                            @else
                                <span class="text-xs text-ink-soft/60 italic">cost necunoscut</span>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Acțiuni --}}
                <div class="col-span-2 row-start-2 md:col-auto md:row-auto flex items-center gap-2 flex-wrap md:flex-nowrap md:shrink-0">
                    @permits('menu', 'edit')
                    <x-btn variant="info" size="icon" outline
                           tooltip="{{ $item->is_active ? 'Ascunde din meniu' : 'Afișează în meniu' }}"
                           wire:click="toggleActive({{ $item->id }})">
                        @if ($item->is_active)
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                        @else
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                        @endif
                    </x-btn>
                    @endpermits

                    @permits('menu', 'edit')
                    <x-btn variant="warning" size="icon" outline tooltip="Editează" :href="route('admin.menu-items.edit', $item)" wire:navigate>
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                    </x-btn>
                    @endpermits

                    @permits('menu', 'edit')
                    <x-btn variant="purple" size="icon" outline tooltip="Duplică" wire:click="duplicate({{ $item->id }})">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                    </x-btn>
                    @endpermits

                    @permits('menu', 'delete')
                    <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                           x-on:click="askConfirm('Șterge produs', 'Sigur vrei să ștergi produsul „{{ addslashes($item->name) }}”? Acțiunea nu poate fi anulată.', 'delete', [{{ $item->id }}])">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </x-btn>
                    @endpermits
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
