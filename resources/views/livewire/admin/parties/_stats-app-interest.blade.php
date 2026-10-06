{{--
    DXA: adaugat (runda 40). „Interes în aplicație” pe petrecere: afișări → deschideri → click „Cumpără” → interesați (inima) → vânzări.
    Se include din stats.blade.php înaintea „Vânzări online”; folosește $appInterest (App\Services\ContentStats::partyFunnel) și $onlineSales.
--}}
@php
    $a = $appInterest;
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $tile = 'rounded-xl border border-border px-3.5 py-2.5';
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
    $pct = fn ($part, $whole) => $whole > 0 ? number_format($part / $whole * 100, 1, ',', '.').'%' : '—';
    $buyers = $onlineSales->totals->buyers ?? 0;
@endphp

<div class="mb-4" data-app-interest>
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Interes în aplicație</h3>
        <p class="mt-1 text-xs text-ink-soft">Cifrele se adună din momentul în care contoarele au fost activate. „Unici” se numără pe zi (aceeași persoană în zile diferite apare de mai multe ori); boții și adminii nu se numără.</p>

        <div class="mt-3 grid grid-cols-2 sm:grid-cols-5 gap-2">
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Afișări</div>
                <div class="text-lg font-semibold text-ink">{{ $int($a['impression']) }}</div>
                <div class="text-[11px] text-ink-soft">{{ $int($a['impression_u']) }} unici</div>
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Deschideri</div>
                <div class="text-lg font-semibold text-ink">{{ $int($a['open']) }}</div>
                <div class="text-[11px] text-ink-soft">{{ $pct($a['open'], $a['impression']) }} din afișări · {{ $int($a['open_u']) }} unici</div>
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Click „Cumpără bilete”</div>
                <div class="text-lg font-semibold text-ink">{{ $int($a['action']) }}</div>
                <div class="text-[11px] text-ink-soft">{{ $pct($a['action'], $a['open']) }} din deschideri</div>
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Interesați (inimă)</div>
                <div class="text-lg font-semibold text-ink" data-interested>{{ $int($a['interested']) }}</div>
                <div class="text-[11px] text-ink-soft">{{ $int($a['interested_bought']) }} au și bilet ({{ $pct($a['interested_bought'], $a['interested']) }})</div>
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Cumpărători online</div>
                <div class="text-lg font-semibold text-ink">{{ $int($buyers) }}</div>
                <div class="text-[11px] text-ink-soft">{{ $pct($buyers, $a['open_u']) }} din deschiderile unice</div>
            </div>
        </div>
    </div>
</div>
