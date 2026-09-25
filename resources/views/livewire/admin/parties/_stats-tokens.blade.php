{{--
    DXA: adaugat (Recepție - tokeni, statistici). „Tokeni”: vânduți la recepție vs încasați la bar, pentru petrecere.
    Se include din stats.blade.php; folosește $stats, $cmp, $card, $money.
--}}
@php
    $tk = $stats->tokens;
    $cmpTk = $cmp?->tokens;
    $fmtInt = fn ($n) => number_format((float) $n, 0, ',', '.');
    $vs = fn ($theirs, $format) => $cmpTk ? '<span class="block text-[11px] text-ink-soft">vs '.e($format($theirs)).'</span>' : '';
@endphp

<div class="{{ $card }} mb-4">
    <h3 class="text-sm font-semibold text-ink">Tokeni</h3>
    <p class="mt-1 text-xs text-ink-soft">Vânduți la recepție la această petrecere, față de încasați la bar în sesiunile ei (toate vânzările finalizate, indiferent de raportări).</p>

    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Vânduți</div>
            <div class="text-lg font-semibold text-ink">{{ $fmtInt($tk->sold) }}</div>
            {!! $vs($cmpTk?->sold, $fmtInt) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Încasat din vânzare</div>
            <div class="text-lg font-semibold text-ink">{{ $money($tk->sold_amount) }}</div>
            {!! $vs($cmpTk?->sold_amount, $money) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Încasați la bar</div>
            <div class="text-lg font-semibold text-ink">{{ $fmtInt($tk->collected) }}</div>
            {!! $vs($cmpTk?->collected, $fmtInt) !!}
        </div>
        <div class="rounded-xl bg-bg px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Diferență</div>
            <div class="text-lg font-semibold text-ink">{{ ($tk->diff > 0 ? '+' : ($tk->diff < 0 ? '−' : '')).$fmtInt(abs($tk->diff)) }}</div>
            {!! $vs($cmpTk?->diff, fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).$fmtInt(abs($n))) !!}
        </div>
    </div>
    <p class="mt-2 text-[11px] leading-relaxed text-ink-soft">
        Diferența nu e neapărat o greșeală: tokenii sunt permanenți, deci rămân în circulație sau se folosesc la alte petreceri.
        Circulația totală e în <a href="{{ route('admin.reception.tokens') }}" wire:navigate class="text-primary hover:underline">Recepție › Tokeni</a>.
    </p>
</div>
