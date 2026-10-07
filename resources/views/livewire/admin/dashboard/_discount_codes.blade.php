{{-- ============ Secțiune: Coduri de reducere ============ --}}
{{-- DXA: adaugat (Coduri de reducere - dashboard). Reduceri 30 zile, codurile petrecerii curente, top promotori, noi vs. reveniți. --}}
@php
    $dd = $discountDash;
    $lei = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $dp = $dd->period;
    $ps = $dd->party_stats;
    $pTop = $dd->promoters;
@endphp
<section class="mt-8 pt-8 border-t border-border">
    <div class="flex items-center gap-2.5 mb-3">
        <svg class="w-5 h-5 text-ink-soft shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.41 0l8.59-8.59a1 1 0 0 0 0-1.41z"/><path d="M7 7h.01"/></svg>
        <h3 class="font-semibold text-ink">Coduri de reducere</h3>
        <a href="{{ route('admin.promoters.index') }}" wire:navigate class="ml-1 text-sm text-primary hover:underline">Promotori</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        <x-stat-card :value="$dp->tickets > 0 ? $lei($dp->discount) : '—'" label="Reduceri acordate ({{ $dd->days }} zile)"
            :hint="$dp->tickets > 0 ? $dp->tickets.' '.($dp->tickets === 1 ? 'bilet' : 'bilete').' cu cod'.($dd->share_pct !== null ? ' · '.str_replace('.', ',', (string) $dd->share_pct).'% din biletele online' : '') : 'niciun bilet cu cod'"
            accent="warning" :href="route('admin.parties.overview')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.41 0l8.59-8.59a1 1 0 0 0 0-1.41z"/><path d="M7 7h.01"/></svg></x-slot:icon>
        </x-stat-card>

        @if ($dd->party && $ps && $ps->has_data)
            <x-stat-card :value="number_format($ps->totals->tickets, 0, ',', '.')" label="Bilete cu cod · {{ \Illuminate\Support\Str::limit($dd->party->name, 18) }}"
                :hint="$lei($ps->totals->discount).' reduceri'.($dd->top_code ? ' · top: '.$dd->top_code->code.' ('.$dd->top_code->tickets.')' : '')"
                accent="primary" :href="route('admin.parties.stats', $dd->party)">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg></x-slot:icon>
            </x-stat-card>
        @else
            <x-stat-card value="—" :label="$dd->party ? 'Coduri · '.\Illuminate\Support\Str::limit($dd->party->name, 18) : 'Coduri petrecere curentă'"
                :hint="$dd->party ? 'niciun cod sau nicio folosire încă' : 'nicio petrecere viitoare'" accent="neutral"
                :href="$dd->party ? route('admin.parties.stats', $dd->party) : null">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg></x-slot:icon>
            </x-stat-card>
        @endif

        @if ($pTop->isNotEmpty())
            <x-stat-card :value="$pTop[0]->promoter" label="Top promotor (în total)"
                :hint="$pTop[0]->tickets.' bilete · '.$pTop[0]->participants.' pers.'.($pTop->count() > 1 ? ' · 2. '.$pTop[1]->promoter.($pTop->count() > 2 ? ' · 3. '.$pTop[2]->promoter : '') : '')"
                accent="purple" :href="route('admin.promoters.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M18 2H6v7a6 6 0 0 0 12 0z"/></svg></x-slot:icon>
            </x-stat-card>
        @else
            <x-stat-card value="—" label="Top promotor (în total)" hint="niciun promotor cu bilete" accent="neutral" :href="route('admin.promoters.index')">
                <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M18 2H6v7a6 6 0 0 0 12 0z"/></svg></x-slot:icon>
            </x-stat-card>
        @endif

        <x-stat-card :value="$dp->tickets > 0 ? number_format($dp->new, 0, ',', '.').' noi' : '—'" label="Noi vs. reveniți ({{ $dd->days }} zile)"
            :hint="$dp->tickets > 0 ? $dp->returning.' reveniți · '.$dp->anonymous.' anonime' : 'nicio persoană cu cod'"
            accent="success" :href="route('admin.promoters.index')">
            <x-slot:icon><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg></x-slot:icon>
        </x-stat-card>
    </div>
</section>
