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
            'live'     => ['Acum',        'bg-primary-soft text-primary'],
            'upcoming' => ['Viitoare',    'bg-warning/10 text-warning'],
            'past'     => ['Trecută',     'bg-ink/5 text-ink-soft'],
            'inactive' => ['Dezactivată', 'bg-surface border border-border text-ink-soft'],
            'draft'    => ['Ciornă',      'bg-info-soft text-info'],
        ];
        $zile = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'];
        $hasFilters = $search !== '' || $state !== 'all' || $kind !== 'all' || $audience !== 'all';
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-ink">Petreceri</h2>
            <p class="mt-1 text-sm text-ink-soft">Petreceri simple (periodice) și festivaluri pe mai multe zile.</p>
        </div>
        <x-btn variant="primary" :href="route('admin.parties.create')" wire:navigate class="self-start">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Petrecere nouă
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
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Caută după denumire…"
                       class="w-full rounded-lg border border-border bg-white pl-9 pr-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>
            <x-select wire:model="state" live class="sm:w-40"
                      :options="['all' => 'Toate stările', 'live' => 'Acum', 'upcoming' => 'Viitoare', 'past' => 'Trecute', 'inactive' => 'Dezactivate', 'draft' => 'Ciorne']" />
            <x-select wire:model="kind" live class="sm:w-40"
                      :options="['all' => 'Orice tip', 'basic' => 'Simplă', 'festival' => 'Festival']" />
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
        @forelse ($parties as $p)
            @php
                [$stateLabel, $stateClasses] = $states[$p->state()];

                // Etichete dată / oră.
                if ($p->isFestival()) {
                    $dateLabel = $p->start_date?->format('d.m.Y');
                    if ($p->end_date && $p->start_date && $p->end_date->format('Y-m-d') !== $p->start_date->format('Y-m-d')) {
                        $dateLabel = $p->start_date->format('d.m').'–'.$p->end_date->format('d.m.Y');
                    }
                    $timeLabel = null;
                } else {
                    $ref = $p->starts_at ?? $p->start_date;
                    $dow = $p->starts_at ? $zile[$p->starts_at->dayOfWeek].' ' : '';
                    $dateLabel = $ref ? $dow.$ref->format('d.m.Y') : '—';
                    $timeLabel = $p->starts_at
                        ? $p->starts_at->format('H:i').($p->ends_at ? '–'.$p->ends_at->format('H:i') : '')
                        : null;
                }

                // Etichetă preț.
                $price = $p->currentPrice();
                if ($price === null) {
                    $priceLabel = null;
                } elseif ((float) $price === 0.0) {
                    $priceLabel = 'Gratuit';
                } elseif ((float) $price == floor((float) $price)) {
                    $priceLabel = number_format($price, 0, ',', '.').' lei';
                } else {
                    $priceLabel = number_format($price, 2, ',', '.').' lei';
                }

                if ($priceLabel && $priceLabel !== 'Gratuit' && $p->hasMultipleTicketTypes()) {
                    $priceLabel = 'de la '.$priceLabel;
                }

                $guestCount = count($p->guests ?? []);
                $statViews = $p->statViews();
                $statOpens = $p->statOpens();
            @endphp
            <div wire:key="party-{{ $p->id }}"
                 class="rounded-2xl border border-border bg-surface p-3 md:p-4 flex flex-col gap-3 md:flex-row md:items-center md:gap-4">

                {{-- Imagine --}}
                <div class="shrink-0">
                    @if ($p->imageUrl())
                        <img src="{{ $p->imageUrl() }}" alt="" class="w-full h-40 md:w-20 md:h-20 object-cover rounded-xl border border-border">
                    @else
                        <div class="w-full h-40 md:w-20 md:h-20 rounded-xl border border-border bg-bg flex items-center justify-center text-ink-soft/40">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/></svg>
                        </div>
                    @endif
                </div>

                {{-- Detalii --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('admin.parties.show', $p) }}" wire:navigate class="font-semibold text-ink truncate hover:text-primary">{{ $p->name }}</a>
                        <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $stateClasses }}">{{ $stateLabel }}</span>
                        <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $p->isFestival() ? 'bg-purple/10 text-purple' : 'bg-bg text-ink-soft' }}">
                            {{ $p->isFestival() ? 'Festival' : 'Simplă' }}
                        </span>
                    </div>

                    {{-- Dată / oră / locație --}}
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-soft">
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v3"/><path d="M16 2v3"/><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/></svg>
                            {{ $dateLabel }}
                        </span>
                        @if ($timeLabel)
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                {{ $timeLabel }}
                            </span>
                        @endif
                        @if ($p->location_name)
                            <span class="inline-flex items-center gap-1 min-w-0">
                                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                                <span class="truncate">{{ $p->location_name }}</span>
                            </span>
                        @endif
                    </div>

                    {{-- Etichete: preț, audiență, plasare, invitați --}}
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        @if ($priceLabel)
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $priceLabel === 'Gratuit' ? 'bg-primary-soft text-primary' : 'bg-bg text-ink-soft' }}">{{ $priceLabel }}</span>
                        @endif
                        <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">{{ $p->audience === 'auth' ? 'Doar logați' : 'Toți' }}</span>
                        @if ($p->in_carousel)
                            <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">Carusel</span>
                        @endif
                        @if ($guestCount > 0)
                            <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">
                                {{ $guestCount }} {{ $guestCount === 1 ? 'invitat' : 'invitați' }}
                            </span>
                        @endif
                    </div>

                    {{-- Contoare reale (runda 40) --}}
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft/70">
                        <span class="inline-flex items-center gap-1" title="Afișări în aplicație">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            {{ number_format($statViews, 0, ',', '.') }}
                        </span>
                        <span class="inline-flex items-center gap-1" title="Deschideri ale paginii petrecerii">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4.1 12 6"/><path d="m5.1 8-2.9-.8"/><path d="m6 12-1.9 2"/><path d="M7.2 2.2 8 5.1"/><path d="M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z"/></svg>
                            {{ number_format($statOpens, 0, ',', '.') }}
                        </span>
                        <span class="inline-flex items-center gap-1" title="Interesați (au adăugat petrecerea la favorite)" data-interested-count>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                            {{ number_format($p->interests_count, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Acțiuni --}}
                <div class="flex items-center gap-2 flex-wrap md:flex-nowrap md:shrink-0">
                    @if ($p->isDraft())
                        <x-btn variant="primary" size="icon" outline tooltip="Publică" wire:click="publish({{ $p->id }})">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/></svg>
                        </x-btn>
                    @else
                        <x-btn variant="info" size="icon" outline
                               tooltip="{{ $p->is_active ? 'Ascunde din app' : 'Afișează în app' }}"
                               wire:click="toggleActive({{ $p->id }})">
                            @if ($p->is_active)
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                            @else
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            @endif
                        </x-btn>
                    @endif

                    <x-btn variant="neutral" size="icon" outline tooltip="Vezi" :href="route('admin.parties.show', $p)" wire:navigate>
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    </x-btn>

                    <x-btn variant="success" size="icon" outline tooltip="Statistici" :href="route('admin.parties.stats', $p)" wire:navigate>
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                    </x-btn>

                    <x-btn variant="warning" size="icon" outline tooltip="Editează" :href="route('admin.parties.edit', $p)" wire:navigate>
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                    </x-btn>

                    <x-btn variant="purple" size="icon" outline tooltip="Duplică" wire:click="duplicate({{ $p->id }})">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                    </x-btn>

                    <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                           x-on:click="askConfirm('Șterge petrecerea', 'Sigur vrei să ștergi „{{ addslashes($p->name) }}”? Acțiunea nu poate fi anulată.', 'delete', [{{ $p->id }}])">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </x-btn>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
                @if ($hasFilters)
                    Nicio petrecere care să corespundă filtrelor.
                @else
                    Nicio petrecere încă. Apasă „Petrecere nouă" ca să creezi prima.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $parties->onEachSide(1)->links('pagination.dxa') }}
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
