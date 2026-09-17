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
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Petrecere nouă
        </x-btn>
    </div>

    {{-- Filtre + căutare --}}
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <div class="relative sm:flex-1 sm:min-w-48">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
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

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-primary-soft text-primary text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

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
                $demoViews = $p->demoViews();
                $demoClicks = $p->demoClicks();
            @endphp
            <div wire:key="party-{{ $p->id }}"
                 class="rounded-2xl border border-border bg-surface p-3 md:p-4 flex flex-col gap-3 md:flex-row md:items-center md:gap-4">

                {{-- Imagine --}}
                <div class="shrink-0">
                    @if ($p->imageUrl())
                        <img src="{{ $p->imageUrl() }}" alt="" class="w-full h-40 md:w-20 md:h-20 object-cover rounded-xl border border-border">
                    @else
                        <div class="w-full h-40 md:w-20 md:h-20 rounded-xl border border-border bg-bg flex items-center justify-center text-ink-soft/40">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="2" x2="12" y2="4.5"/><circle cx="12" cy="13" r="7.5"/><line x1="12" y1="5.5" x2="12" y2="20.5"/><line x1="4.5" y1="13" x2="19.5" y2="13"/><path d="M6.2 9c3.6 1.6 8 1.6 11.6 0"/><path d="M6.2 17c3.6-1.6 8-1.6 11.6 0"/><path d="M9 5.8c-1.6 4.6-1.6 9.8 0 14.4"/><path d="M15 5.8c1.6 4.6 1.6 9.8 0 14.4"/>
                            </svg>
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
                            <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            {{ $dateLabel }}
                        </span>
                        @if ($timeLabel)
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/>
                                </svg>
                                {{ $timeLabel }}
                            </span>
                        @endif
                        @if ($p->location_name)
                            <span class="inline-flex items-center gap-1 min-w-0">
                                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                                </svg>
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

                    {{-- Stats demo --}}
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft/70">
                        <span class="inline-flex items-center gap-1" title="Afișări (demo)">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            {{ number_format($demoViews, 0, ',', '.') }}
                        </span>
                        <span class="inline-flex items-center gap-1" title="Click-uri (demo)">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3l7 7-3 1 2 5-2 1-2-5-3 2z"/></svg>
                            {{ number_format($demoClicks, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Acțiuni --}}
                <div class="flex items-center gap-2 flex-wrap md:flex-nowrap md:shrink-0">
                    @if ($p->isDraft())
                        <x-btn variant="primary" size="icon" outline tooltip="Publică" wire:click="publish({{ $p->id }})">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>
                            </svg>
                        </x-btn>
                    @else
                        <x-btn variant="info" size="icon" outline
                               tooltip="{{ $p->is_active ? 'Ascunde din app' : 'Afișează în app' }}"
                               wire:click="toggleActive({{ $p->id }})">
                            @if ($p->is_active)
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
                    @endif

                    <x-btn variant="neutral" size="icon" outline tooltip="Vezi" :href="route('admin.parties.show', $p)" wire:navigate>
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                    </x-btn>

                    <x-btn variant="warning" size="icon" outline tooltip="Editează" :href="route('admin.parties.edit', $p)" wire:navigate>
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                        </svg>
                    </x-btn>

                    <x-btn variant="purple" size="icon" outline tooltip="Duplică" wire:click="duplicate({{ $p->id }})">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="12" height="12" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </x-btn>

                    <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                           x-on:click="askConfirm('Șterge petrecerea', 'Sigur vrei să ștergi „{{ addslashes($p->name) }}”? Acțiunea nu poate fi anulată.', 'delete', [{{ $p->id }}])">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                        </svg>
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
