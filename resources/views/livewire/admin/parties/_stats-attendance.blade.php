{{--
    DXA: adaugat (Recepție - statistici). „Participanți & intrări”: defalcarea intrărilor petrecerii (toate cele neanulate,
    indiferent de raportări). Se include din stats.blade.php; folosește $stats, $cmp, $entryAxis, $card, $money, $methodLabels.
    $headline = true când petrecerea n-are raportări finalizate (atunci KPI-urile principale lipsesc și rezumatul apare aici).
--}}
@php
    $att = $stats->attendance;
    $cmpAtt = $cmp?->attendance;
    $fmtInt = fn ($n) => number_format((float) $n, 0, ',', '.');
    $vs = fn ($mine, $theirs, $format) => $cmpAtt ? '<span class="block text-[11px] text-ink-soft">vs '.e($format($theirs)).'</span>' : '';
    $maxCount = 1;
    foreach ($entryAxis as $h) {
        $maxCount = max($maxCount, $att->hours[$h]['count'] ?? 0, $cmpAtt?->hours[$h]['count'] ?? 0);
    }
@endphp

<div class="{{ $card }} mb-4">
    <h3 class="text-sm font-semibold text-ink">Participanți &amp; intrări</h3>
    <p class="mt-1 text-xs text-ink-soft">Toate intrările neanulate din Recepție; se numără imediat, fără să aștepte raportări.</p>

    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
        @if ($headline ?? false)
            <div class="rounded-xl bg-bg px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Participanți</div>
                <div class="text-lg font-semibold text-ink">{{ $fmtInt($att->count) }}</div>
                {!! $vs($att->count, $cmpAtt?->count, $fmtInt) !!}
            </div>
            <div class="rounded-xl bg-bg px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Venit intrări</div>
                <div class="text-lg font-semibold text-ink">{{ $money($att->revenue) }}</div>
                {!! $vs($att->revenue, $cmpAtt?->revenue, $money) !!}
            </div>
        @endif
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Plătite</div>
            <div class="text-lg font-semibold text-ink">{{ $fmtInt($att->paid) }}</div>
            {!! $vs($att->paid, $cmpAtt?->paid, $fmtInt) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Gratuite</div>
            <div class="text-lg font-semibold text-ink">{{ $fmtInt($att->free) }}</div>
            {!! $vs($att->free, $cmpAtt?->free, $fmtInt) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">În toleranță</div>
            <div class="text-lg font-semibold text-ink">{{ $fmtInt($att->grace) }}</div>
            {!! $vs($att->grace, $cmpAtt?->grace, $fmtInt) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Identificați</div>
            <div class="text-lg font-semibold text-ink">{{ $fmtInt($att->identified) }}</div>
            {!! $vs($att->identified, $cmpAtt?->identified, $fmtInt) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Participanți unici</div>
            <div class="text-lg font-semibold text-ink">{{ $fmtInt($att->unique_participants) }}</div>
            {!! $vs($att->unique_participants, $cmpAtt?->unique_participants, $fmtInt) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Preț suprascris</div>
            <div class="text-lg font-semibold text-ink">{{ $fmtInt($att->overridden) }}</div>
            {!! $vs($att->overridden, $cmpAtt?->overridden, $fmtInt) !!}
        </div>
    </div>

    @if ($att->payments)
        <div class="mt-4">
            <div class="text-xs font-medium text-ink-soft mb-1.5">Încasat la intrare, pe metodă</div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink">
                @foreach ($att->payments as $method => $amount)
                    <span><span class="text-ink-soft">{{ $methodLabels[$method] ?? $method }}</span> {{ $money($amount) }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if ($entryAxis !== [])
        <div class="mt-4">
            <div class="text-xs font-medium text-ink-soft mb-1.5">Intrări pe ore</div>
            <div class="space-y-1.5">
                @foreach ($entryAxis as $h)
                    @php
                        $c = $att->hours[$h]['count'] ?? 0;
                        $cc = $cmpAtt?->hours[$h]['count'] ?? 0;
                    @endphp
                    <div class="flex items-center gap-3 text-xs">
                        <span class="w-11 shrink-0 text-ink-soft">{{ sprintf('%02d:00', $h) }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="h-2.5 rounded-full bg-primary" style="width: {{ $c > 0 ? max(2, round($c / $maxCount * 100)) : 0 }}%"></div>
                            @if ($cmpAtt)
                                <div class="mt-0.5 h-1.5 rounded-full bg-ink-soft/40" style="width: {{ $cc > 0 ? max(2, round($cc / $maxCount * 100)) : 0 }}%"></div>
                            @endif
                        </div>
                        <span class="w-16 shrink-0 text-right text-ink">{{ $fmtInt($c) }}@if ($cmpAtt) <span class="text-ink-soft">/ {{ $fmtInt($cc) }}</span>@endif</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
