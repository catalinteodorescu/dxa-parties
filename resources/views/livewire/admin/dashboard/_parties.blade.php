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

        {{-- DXA: adaugat (Petreceri - dashboard, statistici agregate). Reutilizeaza PartiesOverview, zero calcul nou. --}}
        <x-stat-card :value="$partiesTotals->count" label="Total petreceri" hint="{{ $partiesTotals->basic }} simple · {{ $partiesTotals->festival }} festival" accent="purple" :href="route('admin.parties.overview')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/></svg></x-slot:icon>
        </x-stat-card>

        <x-stat-card :value="$money($partiesSummary->revenue).' lei'" label="Venit bar" hint="all-time" accent="success" :href="route('admin.bar.stats')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/><path d="M21 12h-4a2 2 0 0 0 0 4h4"/></svg></x-slot:icon>
        </x-stat-card>

        <x-stat-card :value="$money($partiesSummary->profit).' lei'" label="Profit bar" hint="all-time" accent="success" :href="route('admin.bar.stats')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 7 13.5 15.5 8.5 10.5 2 17"/><path d="M16 7h6v6"/></svg></x-slot:icon>
        </x-stat-card>

        <x-stat-card :value="$partiesSummary->entries_total" label="Intrări totale" :hint="$partiesSummary->entries_avg === null ? 'all-time' : 'medie '.$partiesSummary->entries_avg.' / petrecere'" accent="info" :href="route('admin.reception.stats')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg></x-slot:icon>
        </x-stat-card>

        <x-stat-card :value="$money($partiesSummary->entries_revenue).' lei'" label="Venit intrare" hint="all-time" accent="warning" :href="route('admin.reception.stats')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg></x-slot:icon>
        </x-stat-card>

        <x-action-card label="Statistici" accent="primary" :href="route('admin.parties.overview')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg></x-slot:icon>
        </x-action-card>

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
