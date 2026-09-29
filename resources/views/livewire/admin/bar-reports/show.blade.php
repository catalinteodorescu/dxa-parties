@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $signed = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs((float) $n), 2, ',', '.');
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $open = $report->group?->isOpen() ?? false;
    $awaiting = $report->isSubmitted() && $open;
    $diff = $fig->cash_diff;
    $diffOk = $diff !== null && abs($diff) < 0.005;
    $submittedText = $report->submitted_at
        ? 'trimisă '.$report->submitted_at->format('d.m.Y H:i').($report->submitter ? ' de '.$report->submitter->name : '')
        : 'încă în lucru';
@endphp
<div class="max-w-3xl space-y-4">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <a href="{{ route('admin.bar.reports.index') }}" wire:navigate class="text-xs text-ink-soft hover:text-ink">← Raportări casă bar</a>
            <h2 class="mt-1 text-xl font-semibold text-ink">{{ $report->title() }}</h2>
            <p class="mt-1 text-sm text-ink-soft">
                {{ $report->party?->name ?? '—' }} · {{ $report->group?->title() }}
                · {{ $submittedText }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if ($report->isSubmitted() || ! $open)
                <x-btn variant="neutral" size="sm" :href="route('admin.bar.reports.pdf', $report)">PDF</x-btn>
            @endif
            @if ($awaiting)
                <x-btn variant="warning" size="sm" outline wire:click="askReopen({{ $report->id }})">Redeschide</x-btn>
            @endif
        </div>
    </div>

    @if ($reopenMessage)<x-alert type="success">{{ $reopenMessage }}</x-alert>@endif
    @if ($reopenError)<x-alert type="error">{{ $reopenError }}</x-alert>@endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="{{ $card }}"><div class="text-[11px] text-ink-soft">Cash așteptat</div><div class="text-2xl font-semibold text-ink">{{ $money($fig->expected_cash) }}</div><p class="text-[11px] text-ink-soft">lei</p></div>
        <div class="{{ $card }}"><div class="text-[11px] text-ink-soft">Cash numărat</div><div class="text-2xl font-semibold text-ink">{{ $fig->counted_cash === null ? '—' : $money($fig->counted_cash) }}</div><p class="text-[11px] text-ink-soft">lei</p></div>
        <div class="{{ $card }}"><div class="text-[11px] text-ink-soft">Diferență</div><div class="text-2xl font-semibold {{ $diff === null ? 'text-ink' : ($diffOk ? 'text-success' : 'text-warning') }}">{{ $diff === null ? '—' : ($diffOk ? 'Corectă' : $signed($diff)) }}</div></div>
    </div>

    <div class="{{ $card }}">
        <h3 class="text-sm font-semibold text-ink">Cash</h3>
        <div class="mt-3 space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-ink-soft">Fond de casă</span><span class="text-ink">{{ $money($fig->opening_float) }} lei</span></div>
            <div class="flex justify-between"><span class="text-ink-soft">+ Încasat din vânzări (cash)</span><span class="text-ink">{{ $money($fig->cash_sales) }} lei</span></div>
            <div class="flex justify-between"><span class="text-ink-soft">− Bani scoși din casă @if ($report->handed_note)<span class="text-xs">({{ $report->handed_note }})</span>@endif</span><span class="text-ink">{{ $money($fig->handed_over) }} lei</span></div>
            <div class="flex justify-between font-medium border-t border-border pt-1"><span class="text-ink-soft">= Cash așteptat</span><span class="text-ink">{{ $money($fig->expected_cash) }} lei</span></div>
        </div>
    </div>

    @if ($fig->tokens_received > 0 || $fig->counted_tokens !== null)
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Tokeni</h3>
            <div class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-ink-soft">Primiți ca plată</span><span class="text-ink">{{ $fig->tokens_received }}</span></div>
                <div class="flex justify-between"><span class="text-ink-soft">Numărați</span><span class="text-ink">{{ $fig->counted_tokens ?? '—' }}</span></div>
                @if ($fig->tokens_diff !== null)
                    <div class="flex justify-between font-medium border-t border-border pt-1"><span class="text-ink-soft">Diferență</span><span class="{{ $fig->tokens_diff === 0 ? 'text-success' : 'text-warning' }}">{{ $fig->tokens_diff === 0 ? 'corectă' : ($fig->tokens_diff > 0 ? '+' : '−').abs($fig->tokens_diff) }}</span></div>
                @endif
            </div>
        </div>
    @endif

    <div class="{{ $card }}">
        <h3 class="text-sm font-semibold text-ink">Încasări pe metodă</h3>
        <div class="mt-3 space-y-1 text-sm">
            @forelse ($fig->methods as $m)
                <div class="flex justify-between gap-3">
                    <span class="text-ink-soft">{{ $m['label'] }}@if ($m['tokens'] !== null) · {{ $m['tokens'] }} tokeni @endif @if ($m['note'])<span class="text-xs">({{ $m['note'] }})</span>@endif</span>
                    <span class="text-ink">{{ $money($m['amount']) }} lei</span>
                </div>
            @empty
                <p class="text-ink-soft">Nicio încasare.</p>
            @endforelse
            <div class="flex justify-between font-medium border-t border-border pt-1"><span class="text-ink-soft">Total ({{ $fig->sales_count }} bonuri @if ($fig->cancelled_count > 0), {{ $fig->cancelled_count }} anulate @endif)</span><span class="text-ink">{{ $money($fig->revenue) }} lei</span></div>
        </div>
    </div>

    @if ($report->note)
        <div class="{{ $card }}"><h3 class="text-sm font-semibold text-ink">Observații</h3><p class="mt-2 text-sm text-ink whitespace-pre-line">{{ $report->note }}</p></div>
    @endif

    @include('livewire.admin.bar-reports._reopen-dialog')
</div>
