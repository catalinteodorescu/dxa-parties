@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
    $qty = fn ($n) => (float) $n == (int) $n ? (int) $n : number_format((float) $n, 1, ',', '.');
    $barPct = fn ($value, $max) => $max > 0 ? max(2, round((float) $value / $max * 100, 1)) : 0;
    $partyOptions = $parties->mapWithKeys(fn ($p) => [$p->id => $p->name])->all();
@endphp
<div class="space-y-4">
    @if ($parties->isNotEmpty())
        <x-select wire:model="party" live class="w-full sm:w-72" :options="$partyOptions" />
    @endif

    @if (! $selected || $stats->sales_count === 0)
        <div class="{{ $card }} text-sm text-ink-soft">Nicio vânzare înregistrată {{ $selected ? 'la această petrecere' : 'încă' }}.</div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="{{ $card }}"><div class="text-[11px] text-ink-soft">Bonuri</div><div class="text-2xl font-semibold text-ink">{{ $int($stats->sales_count) }}</div>@if ($stats->cancelled > 0)<p class="text-[11px] text-ink-soft">{{ $stats->cancelled }} anulate</p>@endif</div>
            <div class="{{ $card }}"><div class="text-[11px] text-ink-soft">Vândut</div><div class="text-2xl font-semibold text-ink">{{ $money($stats->revenue) }}</div><p class="text-[11px] text-ink-soft">lei</p></div>
            <div class="{{ $card }}"><div class="text-[11px] text-ink-soft">Bon mediu</div><div class="text-2xl font-semibold text-ink">{{ $money($stats->avg_ticket) }}</div><p class="text-[11px] text-ink-soft">lei</p></div>
            <div class="{{ $card }}"><div class="text-[11px] text-ink-soft">Ora de vârf</div>@php $peak = collect($stats->hours)->sortByDesc('total')->keys()->first(); @endphp<div class="text-2xl font-semibold text-ink">{{ sprintf('%02d:00', $peak) }}</div></div>
        </div>

        {{-- Pe oră --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Vânzări pe oră</h3>
            @php $maxH = max(array_column($stats->hours, 'total')); @endphp
            <div class="mt-3 space-y-1.5">
                @foreach ($stats->hours as $h => $row)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-12 shrink-0 text-ink-soft">{{ sprintf('%02d:00', $h) }}</span>
                        <div class="flex-1 h-2 rounded-full bg-bg"><div class="h-2 rounded-full bg-primary" style="width: {{ $barPct($row['total'], $maxH) }}%"></div></div>
                        <span class="w-40 shrink-0 text-right text-ink">{{ $money($row['total']) }} lei <span class="text-xs text-ink-soft">· {{ $row['count'] }} bonuri</span></span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Top produse --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Produse cele mai vândute</h3>
            @php $maxP = max(array_column($stats->top, 'qty')); @endphp
            <div class="mt-3 space-y-1.5">
                @foreach ($stats->top as $name => $row)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-40 shrink-0 truncate text-ink">{{ $name }}</span>
                        <div class="flex-1 h-2 rounded-full bg-bg"><div class="h-2 rounded-full bg-primary" style="width: {{ $barPct($row['qty'], $maxP) }}%"></div></div>
                        <span class="w-32 shrink-0 text-right text-ink">{{ $qty($row['qty']) }} buc <span class="text-xs text-ink-soft">· {{ $money($row['revenue']) }}</span></span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Produse pe oră --}}
        <div class="{{ $card }} overflow-x-auto">
            <h3 class="text-sm font-semibold text-ink">Primele produse, pe oră (bucăți)</h3>
            <table class="mt-3 w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] text-ink-soft">
                        <th class="py-1 pr-3 font-medium">Ora</th>
                        @foreach ($stats->top_names as $n)<th class="py-1 px-2 font-medium text-right">{{ $n }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stats->hours as $h => $_)
                        <tr class="border-t border-border">
                            <td class="py-1 pr-3 text-ink-soft">{{ sprintf('%02d:00', $h) }}</td>
                            @foreach ($stats->top_names as $n)
                                <td class="py-1 px-2 text-right text-ink">{{ isset($stats->by_hour[$h][$n]) ? $qty($stats->by_hour[$h][$n]) : '·' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Metode de plată --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Metode de plată</h3>
            <div class="mt-3 space-y-1 text-sm">
                @foreach ($stats->methods as $key => $m)
                    <div class="flex justify-between gap-3">
                        <span class="text-ink-soft">{{ $stats->labels[$key] ?? $key }}@if ($m['tokens'] > 0) · {{ $m['tokens'] }} tokeni @endif</span>
                        <span class="text-ink">{{ $money($m['amount']) }} lei <span class="text-xs text-ink-soft">({{ $stats->revenue > 0 ? number_format($m['amount'] / $stats->revenue * 100, 0) : 0 }}%)</span></span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
