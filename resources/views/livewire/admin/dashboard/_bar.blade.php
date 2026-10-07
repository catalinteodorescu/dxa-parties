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
