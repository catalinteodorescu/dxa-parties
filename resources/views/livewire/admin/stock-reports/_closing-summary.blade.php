{{-- Rezumatul numărătorii de final de seară a unei raportări FINALIZATE (doar citire).
     Variabile: $closing (StockReport::closingSummary), $report, $finalCounts (StockReportCount cu stockItem), $fmt, $money --}}
@php
    $signedMoney = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).$money(abs($n));
    $badge = fn ($n) => abs($n) < 0.005 ? 'bg-success-soft text-success' : ($n < 0 ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning');
    $diffRows = $finalCounts->filter(fn ($c) => $c->hasDifference());
@endphp

<div class="mt-6">
    <div class="flex flex-wrap items-center gap-2 mb-2">
        <h3 class="text-sm font-semibold text-ink">Inventar</h3>
        @if ($closing->clean)
            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 bg-success-soft text-success">fără diferențe</span>
        @else
            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 bg-warning/10 text-warning">cu diferențe</span>
        @endif
        @if ($report->stock_aligned)
            <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 bg-info-soft text-info">stoc aliniat la numărat</span>
        @endif
    </div>

    @if ($report->counted_cash !== null || $report->counted_tokens !== null)
        <div class="grid gap-3 sm:grid-cols-2 mb-3">
            @if ($report->counted_cash !== null)
                <div class="rounded-xl border border-border bg-surface px-4 py-3">
                    <span class="block text-[11px] uppercase tracking-wide text-ink-soft/60 mb-1">Cash</span>
                    <div class="text-sm text-ink-soft">Așteptat: <span class="text-ink">{{ $money($report->expected_cash ?? 0) }} lei</span>
                        @if ($report->opening_float !== null && (float) $report->opening_float > 0)
                            <span class="text-xs text-ink-soft/70">(cu fond de casă {{ $money($report->opening_float) }} lei)</span>
                        @endif
                    </div>
                    <div class="text-sm text-ink-soft">Numărat: <span class="text-ink">{{ $money($report->counted_cash) }} lei</span></div>
                    @if ($closing->cash_diff !== null)
                        <span class="mt-1.5 inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $badge($closing->cash_diff) }}">
                            {{ abs($closing->cash_diff) < 0.005 ? 'corect' : ($closing->cash_diff < 0 ? 'lipsă ' : 'surplus ').$money(abs($closing->cash_diff)).' lei' }}
                        </span>
                    @endif
                </div>
            @endif

            @if ($report->counted_tokens !== null)
                <div class="rounded-xl border border-border bg-surface px-4 py-3">
                    <span class="block text-[11px] uppercase tracking-wide text-ink-soft/60 mb-1">Tokeni</span>
                    <div class="text-sm text-ink-soft">Așteptat: <span class="text-ink">{{ $report->expected_tokens ?? 0 }} tk</span></div>
                    <div class="text-sm text-ink-soft">Numărat: <span class="text-ink">{{ $report->counted_tokens }} tk</span></div>
                    @if ($closing->tokens_diff !== null)
                        <span class="mt-1.5 inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $badge($closing->tokens_diff) }}">
                            {{ $closing->tokens_diff === 0 ? 'corect' : ($closing->tokens_diff < 0 ? 'lipsă ' : 'surplus ').abs($closing->tokens_diff).' tk' }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($closing->counted_items > 0)
        <div class="rounded-xl border border-border bg-surface px-4 py-3">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <span class="text-sm font-medium text-ink">Inventar</span>
                <span class="text-xs text-ink-soft">
                    {{ $closing->counted_items }} {{ $closing->counted_items === 1 ? 'produs numărat' : 'produse numărate' }},
                    {{ $closing->diff_items }} cu diferențe
                    @if ($closing->diff_items > 0)
                        · <span class="font-medium {{ $closing->inventory_value < 0 ? 'text-danger' : 'text-warning' }}">{{ $signedMoney($closing->inventory_value) }} lei</span>
                        @if ($closing->inventory_cost_unknown) <span class="italic">(fără produsele cu cost necunoscut)</span> @endif
                    @endif
                </span>
            </div>

            @if ($diffRows->isNotEmpty())
                <div class="mt-2 divide-y divide-border/60">
                    @foreach ($diffRows as $c)
                        <div class="py-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                            <span class="text-sm text-ink min-w-0">{{ $c->stockItem?->name ?? '—' }}</span>
                            <span class="text-sm text-ink-soft shrink-0 text-right">
                                {{ $fmt($c->expected_qty) }} → <span class="text-ink">{{ $fmt($c->counted_qty) }}</span> {{ $c->stockItem?->unit }}
                                <span class="ml-1 inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $badge((float) $c->diff_qty) }}">
                                    {{ ((float) $c->diff_qty < 0 ? 'lipsă ' : 'surplus ').$fmt(abs((float) $c->diff_qty)) }}
                                </span>
                                @if ($c->diffValue() !== null)
                                    <span class="block text-xs text-ink-soft/70">≈ {{ $signedMoney($c->diffValue()) }} lei</span>
                                @else
                                    <span class="block text-xs text-ink-soft/70 italic">cost necunoscut</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($finalCounts->count() > $diffRows->count())
                <details class="mt-2">
                    <summary class="cursor-pointer text-xs text-primary hover:underline">Produsele numărate fără diferențe ({{ $finalCounts->count() - $diffRows->count() }})</summary>
                    <div class="mt-1 divide-y divide-border/60">
                        @foreach ($finalCounts->reject(fn ($c) => $c->hasDifference()) as $c)
                            <div class="py-1.5 flex items-center justify-between gap-3 text-sm">
                                <span class="text-ink min-w-0 truncate">{{ $c->stockItem?->name ?? '—' }}</span>
                                <span class="text-ink-soft shrink-0">{{ $fmt($c->counted_qty) }} {{ $c->stockItem?->unit }}</span>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    @endif
</div>
