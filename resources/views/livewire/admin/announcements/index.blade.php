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
        $states = [
            'live'      => ['Activ',      'bg-primary-soft text-primary'],
            'scheduled' => ['Programat',  'bg-warning/10 text-warning'],
            'expired'   => ['Expirat',    'bg-ink/5 text-ink-soft'],
            'inactive'  => ['Dezactivat', 'bg-surface border border-border text-ink-soft'],
            'draft'     => ['Ciornă',     'bg-info-soft text-info'],
        ];
        $hasFilters = $search !== '' || $state !== 'all' || $placement !== 'all' || $audience !== 'all';
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-ink">Anunțuri</h2>
            <p class="mt-1 text-sm text-ink-soft">Apar în app, în carusel și/sau în zona de anunțuri.</p>
        </div>
        <x-btn variant="primary" :href="route('admin.announcements.create')" wire:navigate class="self-start">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Anunț nou
        </x-btn>
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
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Caută după titlu…"
                       class="w-full rounded-lg border border-border bg-white pl-9 pr-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>
            <x-select wire:model="state" live class="sm:w-40"
                      :options="['all' => 'Toate stările', 'live' => 'Activ', 'scheduled' => 'Programat', 'expired' => 'Expirat', 'inactive' => 'Dezactivat', 'draft' => 'Ciornă']" />
            <x-select wire:model="placement" live class="sm:w-44"
                      :options="['all' => 'Orice plasare', 'carousel' => 'Carusel', 'list' => 'Zona de anunțuri']" />
            <x-select wire:model="audience" live class="sm:w-44"
                      :options="['all' => 'Orice audiență', 'public' => 'Toți', 'auth' => 'Doar logați']" />
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
        @forelse ($announcements as $a)
            @php
                [$stateLabel, $stateClasses] = $states[$a->state()];
                $statViews = $a->statViews();
                $statOpens = $a->statOpens();
                $statActions = $a->statActions();
            @endphp
            <div wire:key="ann-{{ $a->id }}"
                 class="rounded-2xl border border-border bg-surface p-3 md:p-4 flex flex-col gap-3 md:flex-row md:items-center md:gap-4">

                {{-- Imagine --}}
                <div class="shrink-0">
                    @if ($a->imageUrl())
                        <img src="{{ $a->imageUrl() }}" alt="" class="w-full h-40 md:w-20 md:h-20 object-cover rounded-xl border border-border">
                    @else
                        <div class="w-full h-40 md:w-20 md:h-20 rounded-xl border border-border bg-bg flex items-center justify-center text-ink-soft/40">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                        </div>
                    @endif
                </div>

                {{-- Detalii --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-semibold text-ink truncate">{{ $a->title }}</h3>
                        <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $stateClasses }}">{{ $stateLabel }}</span>
                    </div>

                    @if ($a->body)
                        <p class="mt-0.5 text-sm text-ink-soft line-clamp-2 md:line-clamp-1">{{ $a->body }}</p>
                    @endif

                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">{{ $a->audience === 'auth' ? 'Doar logați' : 'Toți' }}</span>
                        @if ($a->in_carousel)<span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">Carusel</span>@endif
                        @if ($a->in_list)<span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">Zona de anunțuri</span>@endif
                    </div>

                    <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft/70">
                        <span>
                            @if ($a->starts_at && $a->ends_at)
                                {{ $a->starts_at->format('d.m.Y') }} → {{ $a->ends_at->format('d.m.Y') }}
                            @elseif ($a->starts_at)
                                din {{ $a->starts_at->format('d.m.Y') }}
                            @else
                                fără interval
                            @endif
                        </span>
                        {{-- Contoare reale (runda 40) --}}
                        <span class="inline-flex items-center gap-1" title="Afișări în aplicație">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            {{ number_format($statViews, 0, ',', '.') }}
                        </span>
                        <span class="inline-flex items-center gap-1" title="Deschideri ale paginii anunțului">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4.1 12 6"/><path d="m5.1 8-2.9-.8"/><path d="m6 12-1.9 2"/><path d="M7.2 2.2 8 5.1"/><path d="M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z"/></svg>
                            {{ number_format($statOpens, 0, ',', '.') }}
                        </span>
                        @if ($a->url)
                            <span class="inline-flex items-center gap-1" title="Click-uri pe butonul cu link">↗ {{ number_format($statActions, 0, ',', '.') }}</span>
                        @endif
                    </div>
                </div>

                {{-- Acțiuni --}}
                <div class="flex items-center gap-2 flex-wrap md:flex-nowrap md:shrink-0">
                    @if ($a->isDraft())
                        <x-btn variant="primary" size="icon" outline tooltip="Publică" wire:click="publish({{ $a->id }})">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/></svg>
                        </x-btn>
                    @else
                        <x-btn variant="info" size="icon" outline
                               tooltip="{{ $a->is_active ? 'Ascunde din app' : 'Afișează în app' }}"
                               wire:click="toggleActive({{ $a->id }})">
                            @if ($a->is_active)
                                {{-- vizibil → acțiunea e „ascunde": ochi tăiat --}}
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                            @else
                                {{-- ascuns → acțiunea e „afișează": ochi deschis --}}
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            @endif
                        </x-btn>
                    @endif

                    <x-btn variant="warning" size="icon" outline tooltip="Editează" :href="route('admin.announcements.edit', $a)" wire:navigate>
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                    </x-btn>

                    <x-btn variant="purple" size="icon" outline tooltip="Duplică" wire:click="duplicate({{ $a->id }})">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                    </x-btn>

                    <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                           x-on:click="askConfirm('Șterge anunț', 'Sigur vrei să ștergi anunțul „{{ addslashes($a->title) }}”? Acțiunea nu poate fi anulată.', 'delete', [{{ $a->id }}])">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </x-btn>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
                @if ($hasFilters)
                    Niciun anunț care să corespundă filtrelor.
                @else
                    Niciun anunț încă. Apasă „Anunț nou" ca să creezi primul.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $announcements->onEachSide(1)->links('pagination.dxa') }}
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
