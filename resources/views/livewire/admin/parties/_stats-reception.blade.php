{{--
    DXA: adaugat (Recepție - raportări, statistici). „Casă recepție”: raportările de recepție FINALIZATE ale petrecerii,
    adunate (cash așteptat vs numărat, diferență, totaluri așteptate pe metodă). Se include din stats.blade.php; folosește
    $stats, $cmp, $card, $money, $signed. Blocul „Participanți & intrări” rămâne pe toate intrările neanulate.
--}}
@php
    $rc = $stats->reception;
    $cmpRc = $cmp?->reception;
    $vsRc = fn ($theirs, $format) => $cmp ? '<span class="block text-[11px] text-ink-soft">vs '.e($theirs === null ? '—' : $format($theirs)).'</span>' : '';
    $rcDiffOk = abs($rc->cash_diff) < 0.005;
@endphp

<div class="{{ $card }} mb-4">
    <h3 class="text-sm font-semibold text-ink">Casă recepție</h3>
    <p class="mt-1 text-xs text-ink-soft">
        Doar raportările de recepție finalizate ({{ $rc->reports }}); valorile sunt cele înghețate la închiderea casei.
        Venitul din intrări nu intră în profitul barului.
    </p>

    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Cash așteptat</div>
            <div class="text-lg font-semibold text-ink">{{ $money($rc->expected_cash) }}</div>
            {!! $vsRc($cmpRc?->expected_cash, $money) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Cash numărat</div>
            <div class="text-lg font-semibold text-ink">{{ $money($rc->counted_cash) }}</div>
            {!! $vsRc($cmpRc?->counted_cash, $money) !!}
        </div>
        <div class="rounded-xl px-3 py-2.5 {{ $rcDiffOk ? 'bg-success-soft' : 'bg-warning/10' }}">
            <div class="text-[11px] text-ink-soft">Diferență casă recepție</div>
            <div class="text-lg font-semibold {{ $rcDiffOk ? 'text-success' : 'text-warning' }}">{{ $signed($rc->cash_diff, ' lei') }}</div>
            {!! $vsRc($cmpRc?->cash_diff, fn ($n) => $signed($n, ' lei')) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Predat / scos</div>
            <div class="text-lg font-semibold text-ink">{{ $money($rc->handed_over) }}</div>
            {!! $vsRc($cmpRc?->handed_over, $money) !!}
        </div>
    </div>

    <p class="mt-3 text-xs text-ink-soft">
        Cash încasat: intrări {{ $money($rc->cash_entries) }} · tokeni {{ $money($rc->cash_tokens) }}.
    </p>

    @if ($rc->methods)
        <p class="mt-1 text-xs text-ink-soft">
            Alte metode (totaluri așteptate):
            @foreach ($rc->methods as $m)
                {{ $m['label'] }} {{ $money($m['amount']) }}@if (! $loop->last) · @endif
            @endforeach
        </p>
    @endif
</div>
