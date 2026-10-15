@php
    $lei = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
    $card = 'rounded-2xl border border-border bg-surface p-4';
    $row = 'flex items-baseline justify-between gap-3 text-sm';
@endphp
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-ink">Rezumat</h1>
            @if ($party)<p class="text-xs text-ink-soft truncate">{{ $party->name }}</p>@endif
        </div>
        @include('livewire.bar._nav')
    </div>

    @if (! $summary)
        <div class="{{ $card }} text-sm text-ink-soft" data-summary-empty>Nicio vânzare înregistrată încă la această petrecere.</div>
    @else
        <div class="grid grid-cols-2 gap-2.5" data-summary-totals>
            <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Bonuri</div>
                <div class="text-xl font-semibold text-ink">{{ $summary->sales_count }}</div>
                @if ($summary->cancelled_count > 0)<div class="text-[11px] text-ink-soft">{{ $summary->cancelled_count }} anulate</div>@endif
            </div>
            <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Vândut</div>
                <div class="text-xl font-semibold text-ink">{{ $lei($summary->revenue) }}</div>
            </div>
        </div>

        <div class="{{ $card }} space-y-1.5">
            <h2 class="text-sm font-semibold text-ink">Încasat pe metodă</h2>
            @forelse ($summary->methods as $key => $m)
                <div class="{{ $row }}" @if ($key === $creditKey) data-summary-credit @endif>
                    <span class="text-ink-soft">{{ \App\Support\PaymentMethods::label($key) }}@if ($m['tokens'] !== null) · {{ $m['tokens'] }} tokeni @endif</span>
                    <span class="font-medium text-ink">{{ $lei($m['amount']) }}</span>
                </div>
            @empty
                <p class="text-sm text-ink-soft">Nicio încasare.</p>
            @endforelse
        </div>

        <div class="{{ $card }} space-y-1.5">
            <h2 class="text-sm font-semibold text-ink">Produse vândute</h2>
            @forelse ($lines as $l)
                <div class="{{ $row }}"><span class="text-ink-soft">{{ $l->menuItem->name }} × {{ $num($l->qty) }}</span><span class="font-medium text-ink">{{ $lei($l->revenue) }}</span></div>
            @empty
                <p class="text-sm text-ink-soft">Niciun produs vândut.</p>
            @endforelse
        </div>

        <div class="{{ $card }} space-y-1.5" data-summary-stock>
            <h2 class="text-sm font-semibold text-ink">Stoc consumat (estimat)</h2>
            @forelse ($stock as $s)
                <div class="{{ $row }}"><span class="text-ink-soft">{{ $s['name'] }}</span><span class="font-medium text-ink">{{ $num($s['qty']) }} {{ $s['unit'] }}</span></div>
            @empty
                <p class="text-sm text-ink-soft">Niciun produs cu rețetă vândut.</p>
            @endforelse
            <p class="pt-1 text-xs text-ink-soft">Calculat din rețetele produselor vândute; stocul real se scade la Raportarea de stoc.</p>
        </div>
    @endif
</div>
