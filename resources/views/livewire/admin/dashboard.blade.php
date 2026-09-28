<div>
    @php
        $states = [
            'live'      => ['Activ',      'bg-primary-soft text-primary'],
            'scheduled' => ['Programat',  'bg-warning/10 text-warning'],
            'expired'   => ['Expirat',    'bg-ink/5 text-ink-soft'],
            'inactive'  => ['Dezactivat', 'bg-surface border border-border text-ink-soft'],
            'draft'     => ['Ciornă',     'bg-info-soft text-info'],
        ];
        // DXA: adaugat (Petreceri) — stari pentru pill-urile din widget
        $partyStates = [
            'live'     => ['Acum',        'bg-primary-soft text-primary'],
            'upcoming' => ['Viitoare',    'bg-warning/10 text-warning'],
            'past'     => ['Trecută',     'bg-ink/5 text-ink-soft'],
            'inactive' => ['Dezactivată', 'bg-surface border border-border text-ink-soft'],
            'draft'    => ['Ciornă',      'bg-info-soft text-info'],
        ];

        // DXA: adaugat (Bar - raportari) — stari pentru pill-ul din widget-ul de raportari
        $reportStates = [
            'draft'     => ['Draft',      'bg-primary-soft text-primary'],
            'finalized' => ['Finalizat',  'bg-info-soft text-info'],
        ];
        $money = fn ($n) => number_format((float) $n, 2, ',', '.');
        // DXA: adaugat (Bar - stocuri, alertă stoc negativ persistent) — cantitate fara zerouri de prisos.
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
    @endphp

    <div>
        <h2 class="text-lg font-semibold text-ink">Bun venit, {{ $currentAdmin->name }}.</h2>
        <p class="mt-1 text-sm text-ink-soft">Privire de ansamblu asupra activității.</p>
    </div>

    {{-- ============ Secțiune modul: Anunțuri ============ --}}
    <section class="mt-8 pt-8 border-t border-border">
        <div class="flex items-center gap-2.5 mb-3">
            <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"/><path d="M8 6v8"/></svg>
            <h3 class="font-semibold text-ink">Anunțuri</h3>
            <a href="{{ route('admin.announcements.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Vezi toate</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            <x-stat-card :value="$liveCount" label="Active acum" hint="publicate, în interval" accent="primary" :href="route('admin.announcements.index', ['state' => 'live'])">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg></x-slot:icon>
            </x-stat-card>

            <x-stat-card :value="$scheduledCount" label="Programate" hint="încă neîncepute" accent="warning" :href="route('admin.announcements.index', ['state' => 'scheduled'])">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></x-slot:icon>
            </x-stat-card>

            <x-stat-card :value="$draftCount" label="Ciorne" hint="nepublicate" accent="info" :href="route('admin.announcements.index', ['state' => 'draft'])">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/></svg></x-slot:icon>
            </x-stat-card>

            @if ($latest)
                <x-stat-card :value="number_format($latest->demoViews(), 0, ',', '.')" label="Afișări (demo)" :hint="$latest->title" accent="purple" :href="route('admin.announcements.edit', $latest)">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/></svg></x-slot:icon>
                </x-stat-card>
            @else
                <x-stat-card value="—" label="Afișări (demo)" hint="niciun anunț" accent="purple">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/></svg></x-slot:icon>
                </x-stat-card>
            @endif

            <x-action-card label="Anunț nou" accent="primary" :href="route('admin.announcements.create')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
            </x-action-card>
        </div>

        {{-- Widget: ultimele anunțuri (până la 4), lat cât 2 carduri --}}
        <div class="mt-3 grid grid-cols-1 lg:grid-cols-6 gap-3">
            <div class="lg:col-span-2 rounded-xl border border-border bg-surface p-4">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft/80 mb-1">Ultimele anunțuri</div>
                @forelse ($latestList as $a)
                    @php [$sl, $sc] = $states[$a->state()]; @endphp
                    <a href="{{ route('admin.announcements.edit', $a) }}" wire:navigate
                       class="group flex items-center justify-between gap-3 py-2 border-b border-border last:border-0">
                        <span class="text-sm text-ink group-hover:text-primary truncate">{{ $a->title }}</span>
                        <span class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-ink-soft whitespace-nowrap">
                                @if ($a->starts_at && $a->ends_at)
                                    {{ $a->starts_at->format('d.m.y') }}–{{ $a->ends_at->format('d.m.y') }}
                                @elseif ($a->starts_at)
                                    din {{ $a->starts_at->format('d.m.y') }}
                                @else
                                    —
                                @endif
                            </span>
                            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $sc }}">{{ $sl }}</span>
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-ink-soft mt-1">Niciun anunț încă.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ============ Secțiune modul: Petreceri ============ --}}
    {{-- DXA: adaugat (Petreceri) — secțiune completă --}}
    <section class="mt-8 pt-8 border-t border-border">
        <div class="flex items-center gap-2.5 mb-3">
            <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/></svg>
            <h3 class="font-semibold text-ink">Petreceri</h3>
            <a href="{{ route('admin.parties.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Vezi toate</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            @if ($nextParty)
                <x-stat-card :value="$nextParty->starts_at?->format('d.m') ?? '—'" label="Următoarea" :hint="$nextParty->name" accent="primary" :href="route('admin.parties.edit', $nextParty)">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/></svg></x-slot:icon>
                </x-stat-card>
            @else
                <x-stat-card value="—" label="Următoarea" hint="nicio petrecere" accent="primary" :href="route('admin.parties.create')">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/></svg></x-slot:icon>
                </x-stat-card>
            @endif

            <x-stat-card :value="$partyUpcomingCount" label="Viitoare" hint="programate" accent="warning" :href="route('admin.parties.index', ['state' => 'upcoming'])">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></x-slot:icon>
            </x-stat-card>

            <x-stat-card :value="$partyDraftCount" label="Ciorne" hint="nepublicate" accent="info" :href="route('admin.parties.index', ['state' => 'draft'])">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/></svg></x-slot:icon>
            </x-stat-card>

            <x-action-card label="Petrecere nouă" accent="primary" :href="route('admin.parties.create')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
            </x-action-card>
        </div>

        {{-- Widget: următoarele petreceri (până la 4), lat cât 2 carduri --}}
        <div class="mt-3 grid grid-cols-1 lg:grid-cols-6 gap-3">
            <div class="lg:col-span-2 rounded-xl border border-border bg-surface p-4">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft/80 mb-1">Următoarele petreceri</div>
                @forelse ($partiesUpcomingList as $p)
                    @php [$sl, $sc] = $partyStates[$p->state()]; @endphp
                    <a href="{{ route('admin.parties.edit', $p) }}" wire:navigate
                       class="group flex items-center justify-between gap-3 py-2 border-b border-border last:border-0">
                        <span class="text-sm text-ink group-hover:text-primary truncate">{{ $p->name }}</span>
                        <span class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-ink-soft whitespace-nowrap">
                                @if ($p->isFestival() && $p->end_date && $p->start_date && $p->end_date->ne($p->start_date))
                                    {{ $p->start_date->format('d.m') }}–{{ $p->end_date->format('d.m.y') }}
                                @elseif ($p->starts_at)
                                    {{ $p->starts_at->format('d.m.y') }}
                                @else
                                    —
                                @endif
                            </span>
                            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $sc }}">{{ $sl }}</span>
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-ink-soft mt-1">Nicio petrecere viitoare.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ============ Secțiune modul: Bar ============ --}}
    {{-- DXA: adaugat (Bar) — Produse, stocuri, necesare, raportări. --}}
    <section class="mt-8 pt-8 border-t border-border">
        <div class="flex items-center gap-2.5 mb-3">
            <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12 4.207 4.207A.707.707 0 0 1 4.707 3h14.586a.707.707 0 0 1 .5 1.207z"/><path d="M12 12v10"/><path d="M7 22h10"/></svg>
            <h3 class="font-semibold text-ink">Bar</h3>
            <a href="{{ route('admin.menu-items.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Vezi toate</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            {{-- DXA: adaugat (Meniu bar - produse) --}}
            <x-stat-card :value="$menuItemsActiveCount" label="Articole meniu active" hint="din {{ $menuItemsTotalCount }} total" accent="info" :href="route('admin.menu-items.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v16"/><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"/></svg></x-slot:icon>
            </x-stat-card>

            {{-- DXA: adaugat (Bar - stocuri) --}}
            <x-stat-card :value="$stockItemsLowCount" label="Sub stoc minim" hint="produse de stoc" accent="{{ $stockItemsLowCount > 0 ? 'danger' : 'neutral' }}" :href="route('admin.stock-items.index', ['sub_min' => 1])">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></x-slot:icon>
            </x-stat-card>

            {{-- DXA: adaugat (Bar - stocuri, alertă stoc negativ persistent) --}}
            <x-stat-card :value="$negativeStockItems->count()" label="Stoc negativ" hint="{{ $negativeStockItems->isNotEmpty() ? 'de peste '.$negativeStockDaysThreshold.' zile' : 'nimic persistent' }}" accent="{{ $negativeStockItems->isNotEmpty() ? 'danger' : 'neutral' }}" :href="route('admin.stock-items.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg></x-slot:icon>
            </x-stat-card>

            {{-- DXA: adaugat (Bar - vanzari) --}}
            <x-stat-card :value="$money($unreportedSalesTotal).' lei'" label="Vânzări neraportate" :hint="$openSalesGroupsCount.' '.($openSalesGroupsCount === 1 ? 'sesiune deschisă' : 'sesiuni deschise')" accent="{{ $unreportedSalesTotal > 0 ? 'info' : 'neutral' }}" :href="route('admin.sales.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17V7"/><path d="M16 8h-6a2 2 0 0 0 0 4h4a2 2 0 0 1 0 4H8"/><path d="M4 3a1 1 0 0 1 1-1 1.3 1.3 0 0 1 .7.2l.933.6a1.3 1.3 0 0 0 1.4 0l.934-.6a1.3 1.3 0 0 1 1.4 0l.933.6a1.3 1.3 0 0 0 1.4 0l.933-.6a1.3 1.3 0 0 1 1.4 0l.934.6a1.3 1.3 0 0 0 1.4 0l.933-.6A1.3 1.3 0 0 1 19 2a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1 1.3 1.3 0 0 1-.7-.2l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.934.6a1.3 1.3 0 0 1-1.4 0l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-1.4 0l-.934-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-.7.2 1 1 0 0 1-1-1z"/></svg></x-slot:icon>
            </x-stat-card>

            {{-- DXA: adaugat (Bar - necesare) --}}
            <x-stat-card :value="$requisitionsOpenCount" label="Necesare deschise" :hint="$requisitionStale ? 'cel mai vechi, uitat de câteva zile' : 'așteaptă recepție'" accent="{{ $requisitionStale ? 'danger' : ($requisitionsOpenCount > 0 ? 'warning' : 'neutral') }}" :href="route('admin.stock-requisitions.index', ['state' => 'open'])">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg></x-slot:icon>
            </x-stat-card>

            {{-- DXA: adaugat (Bar - raportari) --}}
            @if ($lastReport)
                <x-stat-card :value="$lastReport->date->format('d.m.y')" label="Ultima raportare" :hint="'nr. '.$lastReport->id.' · '.($lastReportStale ? 'draft neatins de câteva zile' : ($lastReport->isDraft() ? 'draft, în lucru' : 'finalizată'))" accent="{{ $lastReportStale ? 'warning' : ($lastReport->isDraft() ? 'primary' : 'info') }}" :href="route('admin.stock-reports.edit', $lastReport)">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M8 18v-1"/><path d="M12 18v-6"/><path d="M16 18v-3"/></svg></x-slot:icon>
                </x-stat-card>
            @else
                <x-stat-card value="—" label="Ultima raportare" hint="nicio raportare încă" accent="neutral">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M8 18v-1"/><path d="M12 18v-6"/><path d="M16 18v-3"/></svg></x-slot:icon>
                </x-stat-card>
            @endif

            <x-stat-card :value="$money($profitLast30Days).' lei'" label="Profit (30 zile)" hint="raportări finalizate" accent="{{ $profitLast30Days > 0 ? 'success' : ($profitLast30Days < 0 ? 'danger' : 'neutral') }}" :href="route('admin.stock-reports.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></x-slot:icon>
            </x-stat-card>

            <x-action-card label="Produs nou" accent="primary" :href="route('admin.menu-items.create')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
            </x-action-card>

            {{-- DXA: adaugat (Bar - necesare) --}}
            <x-action-card label="Necesar nou" accent="primary" :href="route('admin.stock-requisitions.create')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
            </x-action-card>

            {{-- DXA: adaugat (Bar - raportari) --}}
            <x-action-card label="Raportare nouă" accent="primary" :href="route('admin.stock-reports.create')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
            </x-action-card>
        </div>

        {{-- DXA: adaugat (Bar - raportari / stocuri) — Widget-uri: ultimele raportări +, daca exista, stoc negativ persistent --}}
        <div class="mt-3 grid grid-cols-1 lg:grid-cols-6 gap-3">
            <div class="lg:col-span-2 rounded-xl border border-border bg-surface p-4">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft/80 mb-1">Ultimele raportări</div>
                @forelse ($lastReportsList as $r)
                    @php [$sl, $sc] = $reportStates[$r->status] ?? ['—', 'bg-ink/5 text-ink-soft']; @endphp
                    <a href="{{ route('admin.stock-reports.edit', $r) }}" wire:navigate
                       class="group flex items-center justify-between gap-3 py-2 border-b border-border last:border-0">
                        <span class="text-sm text-ink group-hover:text-primary truncate">
                            Nr. {{ $r->number() }}
                            @if ($r->party) <span class="text-ink-soft/60 font-normal">· {{ $r->party->name }}</span> @endif
                        </span>
                        <span class="flex items-center gap-2 shrink-0">
                            @if ($r->isFinalized())
                                @php $p = $r->totalProfit(); @endphp
                                <span class="text-xs whitespace-nowrap {{ $p > 0 ? 'text-success' : ($p < 0 ? 'text-danger' : 'text-ink-soft') }}">{{ $money($p) }} lei</span>
                            @endif
                            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $sc }}">{{ $sl }}</span>
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-ink-soft mt-1">Nicio raportare încă.</p>
                @endforelse
            </div>

            {{-- DXA: adaugat (Bar - stocuri, alertă stoc negativ persistent) — apare doar cand exista ceva de semnalat. --}}
            @if ($negativeStockItems->isNotEmpty())
                <div class="lg:col-span-2 rounded-xl border border-danger/30 bg-surface p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-danger/80 mb-1">Stoc negativ persistent</div>
                    @foreach ($negativeStockItems as $row)
                        @php
                            $si = $row['item'];
                            $days = $row['since']->diffInDays(now());
                        @endphp
                        <a href="{{ route('admin.stock-items.index', ['q' => $si->name]) }}" wire:navigate
                           class="group flex items-center justify-between gap-3 py-2 border-b border-border last:border-0">
                            <span class="text-sm text-ink group-hover:text-primary truncate">{{ $si->name }}</span>
                            <span class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-danger whitespace-nowrap">{{ $fmt($si->stock_qty) }} {{ $si->unit }}</span>
                                <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 bg-danger/10 text-danger">de {{ $days }} {{ $days === 1 ? 'zi' : 'zile' }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- DXA: adaugat (Bar - numaratoare de final de seara) — ultima numaratoare: cash / tokeni / inventar, numarat vs asteptat. --}}
            @php
                $signedMoney = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).$money(abs($n));
                $diffPill = fn ($n) => abs($n) < 0.005 ? 'bg-success-soft text-success' : ($n < 0 ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning');
            @endphp
            <div class="lg:col-span-2 rounded-xl border {{ $lastCount && ! $lastCount->clean ? 'border-warning/40' : 'border-border' }} bg-surface p-4">
                <div class="flex items-center justify-between gap-3 mb-1">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft/80">Ultimul inventar</div>
                    @if ($lastCountedReport)
                        <a href="{{ route('admin.stock-reports.edit', $lastCountedReport) }}" wire:navigate class="text-xs text-primary hover:underline whitespace-nowrap">
                            Nr. {{ $lastCountedReport->number() }}@if ($lastCountedReport->party) · {{ \Illuminate\Support\Str::limit($lastCountedReport->party->name, 18) }}@endif
                        </a>
                    @endif
                </div>

                @if ($lastCount)
                    <div class="flex items-center justify-between gap-3 py-2 border-b border-border">
                        <span class="text-sm text-ink">Cash</span>
                        @if ($lastCount->cash_diff === null)
                            <span class="text-xs text-ink-soft/60">nenumărat</span>
                        @else
                            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $diffPill($lastCount->cash_diff) }}">{{ abs($lastCount->cash_diff) < 0.005 ? 'corect' : $signedMoney($lastCount->cash_diff).' lei' }}</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-3 py-2 border-b border-border">
                        <span class="text-sm text-ink">Tokeni</span>
                        @if ($lastCount->tokens_diff === null)
                            <span class="text-xs text-ink-soft/60">nenumărat</span>
                        @else
                            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $diffPill($lastCount->tokens_diff) }}">{{ $lastCount->tokens_diff === 0 ? 'corect' : ($lastCount->tokens_diff > 0 ? '+' : '−').abs($lastCount->tokens_diff).' tk' }}</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-3 py-2">
                        <span class="text-sm text-ink">Inventar</span>
                        @if ($lastCount->counted_items === 0)
                            <span class="text-xs text-ink-soft/60">nenumărat</span>
                        @elseif ($lastCount->diff_items === 0)
                            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 bg-success-soft text-success">fără diferențe</span>
                        @else
                            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $lastCount->inventory_value < 0 ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning' }}">{{ $lastCount->diff_items }} {{ $lastCount->diff_items === 1 ? 'produs' : 'produse' }} · {{ $signedMoney($lastCount->inventory_value) }} lei</span>
                        @endif
                    </div>

                    @if ($countTotals30->reports > 0)
                        <p class="mt-2 pt-2 border-t border-border text-[11px] text-ink-soft/70">
                            30 zile: {{ $countTotals30->reports }} {{ $countTotals30->reports === 1 ? 'inventar' : 'inventare' }}, {{ $countTotals30->dirty }} cu diferențe
                            · cash {{ $signedMoney($countTotals30->cash) }} lei · inventar {{ $signedMoney($countTotals30->inventory) }} lei
                        </p>
                    @endif
                @else
                    <p class="mt-1 text-sm text-ink-soft">Niciun inventar încă. Îl completezi în Raportare, la final de seară.</p>
                @endif
            </div>
        </div>

        {{-- DXA: adaugat (Bar - dashboard, widget comparație ultimele 2 petreceri) — apare doar cand exista cel putin 2 petreceri cu raportare finalizata; sub 2 nu are ce compara. Petrecerile sunt RANDURI (una sub alta), metricile sunt COLOANE, ca valorile sa se urmareasca vertical (nu side-by-side pe coloane per petrecere). --}}
        @if ($lastTwoPartiesComparison->count() === 2)
            @php
                [$cmpA, $cmpB] = $lastTwoPartiesComparison->values();
                $higherProfitPartyId = $cmpA->profit === $cmpB->profit
                    ? null
                    : ($cmpA->profit > $cmpB->profit ? $cmpA->party->id : $cmpB->party->id);
            @endphp
            <div class="mt-3 grid grid-cols-1 lg:grid-cols-6 gap-3">
                <div class="lg:col-span-3 rounded-xl border border-border bg-surface p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft/80">Comparație — ultimele 2 petreceri</div>
                        <a href="{{ route('admin.stock-reports.index') }}" wire:navigate class="text-xs text-primary hover:underline">Vezi raportările</a>
                    </div>

                    <div class="hidden sm:grid grid-cols-[1fr_6rem_6rem_6rem_5rem] gap-2 text-[10px] font-semibold uppercase tracking-wide text-ink-soft/60 pb-1.5 border-b border-border">
                        <span>Petrecere</span>
                        <span class="text-right">Vânzări</span>
                        <span class="text-right">Cost</span>
                        <span class="text-right">Profit</span>
                        <span class="text-right">Marjă</span>
                    </div>

                    <div class="divide-y divide-border">
                        @foreach ([$cmpA, $cmpB] as $cmp)
                            <div class="py-2 sm:grid sm:grid-cols-[1fr_6rem_6rem_6rem_5rem] sm:gap-2 sm:items-center">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.parties.edit', $cmp->party) }}" wire:navigate class="text-sm font-medium text-ink hover:text-primary truncate block">{{ $cmp->party->name }}</a>
                                    <span class="text-xs text-ink-soft/70">{{ $cmp->party->starts_at?->format('d.m.Y') ?? '—' }}</span>
                                </div>

                                <div class="mt-1 sm:mt-0 sm:text-right text-sm text-ink">
                                    <span class="sm:hidden text-[11px] uppercase text-ink-soft/50">Vânzări: </span>{{ $money($cmp->revenue) }} lei
                                </div>

                                <div class="mt-0.5 sm:mt-0 sm:text-right text-sm text-ink-soft">
                                    <span class="sm:hidden text-[11px] uppercase text-ink-soft/50">Cost: </span>{{ $money($cmp->cost) }} lei
                                </div>

                                <div class="mt-0.5 sm:mt-0 sm:text-right text-sm font-semibold {{ $cmp->profit > 0 ? 'text-success' : ($cmp->profit < 0 ? 'text-danger' : 'text-ink') }}">
                                    <span class="sm:hidden text-[11px] uppercase text-ink-soft/50 font-normal">Profit: </span>{{ $money($cmp->profit) }} lei
                                    @if ($higherProfitPartyId === $cmp->party->id)
                                        <span class="ml-0.5 text-success">▲</span>
                                    @endif
                                </div>

                                <div class="mt-0.5 sm:mt-0 sm:text-right text-sm text-ink-soft">
                                    <span class="sm:hidden text-[11px] uppercase text-ink-soft/50">Marjă: </span>{{ $cmp->margin !== null ? number_format($cmp->margin, 1, ',', '.').'%' : '—' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- ============ Secțiune modul: Recepție ============ --}}
    {{-- DXA: adaugat (Recepție - raportări) — indicatori din $receptionCards (lista se poate extinde din Dashboard.php). --}}
    <section class="mt-8 pt-8 border-t border-border">
        <div class="flex items-center gap-2.5 mb-3">
            <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/></svg>
            <h3 class="font-semibold text-ink">Recepție</h3>
            <a href="{{ route('admin.reception.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Deschide</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            @foreach ($receptionCards as $card)
                <x-stat-card :value="$card['value']" :label="$card['label']" :hint="$card['hint']" accent="{{ $card['accent'] }}" :href="$card['href']" wire:key="reception-card-{{ $loop->index }}">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/></svg></x-slot:icon>
                </x-stat-card>
            @endforeach
        </div>
    </section>

    {{-- ============ Secțiune modul: Participanți ============ --}}
    {{-- DXA: adaugat (Participanți - dashboard). Tablou scurt: participanți, tokeni în circulație, carduri de
         fidelitate active (doar cât fidelitatea e activă global) — fiecare cu link direct la pagina lui. --}}
    <section class="mt-8 pt-8 border-t border-border">
        <div class="flex items-center gap-2.5 mb-3">
            <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
            <h3 class="font-semibold text-ink">Participanți</h3>
            <a href="{{ route('admin.participants.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Vezi toți</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            <x-stat-card :value="number_format($participantsTotal, 0, ',', '.')" label="Participanți" hint="identificați" accent="primary" :href="route('admin.participants.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></x-slot:icon>
            </x-stat-card>

            <x-stat-card :value="number_format($participantsNewThisMonth, 0, ',', '.')" label="Noi luna asta" hint="participanți" accent="info" :href="route('admin.participants.stats')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
            </x-stat-card>

            <x-stat-card :value="number_format($tokensCirculation, 0, ',', '.')" label="Tokeni în circulație" hint="vânduți − încasați" accent="warning" :href="route('admin.reception.tokens')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg></x-slot:icon>
            </x-stat-card>

            @if ($loyaltyOn)
                <x-stat-card :value="number_format($loyaltyActiveCards, 0, ',', '.')" label="Carduri active" hint="fidelitate" accent="purple" :href="route('admin.loyalty.cards')">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></x-slot:icon>
                </x-stat-card>
            @endif

            <x-action-card label="Statistici" accent="neutral" :href="route('admin.participants.stats')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg></x-slot:icon>
            </x-action-card>
        </div>
    </section>

    {{-- ============ Secțiune modul: Administratori (doar superadmin) ============ --}}
    @if ($currentAdmin->isSuperAdmin())
        <section class="mt-8 pt-8 border-t border-border">
            <div class="flex items-center gap-2.5 mb-3">
                <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/></svg>
                <h3 class="font-semibold text-ink">Administratori</h3>
                <a href="{{ route('admin.users.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Vezi toți</a>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
                <x-stat-card :value="$adminsActive" label="Activi" hint="din {{ $adminsTotal }} în total" accent="neutral" :href="route('admin.users.index')">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></x-slot:icon>
                </x-stat-card>

                <x-action-card label="Admin nou" accent="primary" :href="route('admin.users.create')">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
                </x-action-card>

                <x-action-card label="Jurnal" accent="neutral" :href="route('admin.logs.index')">
                    <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></x-slot:icon>
                </x-action-card>
            </div>
        </section>
    @endif

    {{-- Modulul viitor (Credite): o <section> nouă cu „mt-8 pt-8 border-t border-border",
         antet (iconiță simplă + titlu + „Vezi toate") și grid pe 6 coloane cu
         x-stat-card / x-action-card / widget-uri. --}}
</div>
