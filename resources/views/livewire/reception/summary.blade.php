@php
    $lei = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $card = 'rounded-2xl border border-border bg-surface p-4';
    $row = 'flex items-baseline justify-between gap-3 text-sm';
@endphp
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-ink">Rezumat</h1>
            @if ($party)<p class="text-xs text-ink-soft truncate">{{ $party->name }}</p>@endif
        </div>
        @include('livewire.reception._sale-nav', ['current' => 'summary'])
    </div>

    @if (! $summary)
        <div class="{{ $card }} text-sm text-ink-soft" data-summary-empty>Nicio operațiune înregistrată încă la această petrecere.</div>
    @else
        <div class="grid grid-cols-2 gap-2.5" data-summary-totals>
            <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Intrări</div>
                <div class="text-xl font-semibold text-ink">{{ $summary->entries_count }}</div>
                @if ($summary->entries_free > 0)<div class="text-[11px] text-ink-soft">din care {{ $summary->entries_free }} gratuite</div>@endif
            </div>
            <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Încasat</div>
                <div class="text-xl font-semibold text-ink">{{ $lei($collected) }}</div>
            </div>
        </div>

        <div class="{{ $card }} space-y-1.5">
            <h2 class="text-sm font-semibold text-ink">Intrări pe tip de bilet</h2>
            @forelse ($summary->tickets as $t)
                <div class="{{ $row }}"><span class="text-ink-soft">{{ $t['name'] }} · {{ $t['count'] }}</span><span class="font-medium text-ink">{{ $lei($t['revenue']) }}</span></div>
            @empty
                <p class="text-sm text-ink-soft">Nicio intrare.</p>
            @endforelse
            @if ($summary->entries_cancelled > 0)
                <div class="{{ $row }} pt-1"><span class="text-ink-soft">Anulate</span><span class="font-medium text-ink">{{ $summary->entries_cancelled }}</span></div>
            @endif
        </div>

        <div class="{{ $card }} space-y-1.5">
            <h2 class="text-sm font-semibold text-ink">Încasări pe metodă</h2>
            @forelse ($summary->methods as $key => $amount)
                <div class="{{ $row }}"><span class="text-ink-soft">{{ \App\Support\PaymentMethods::label($key) }}</span><span class="font-medium text-ink">{{ $lei($amount) }}</span></div>
            @empty
                <p class="text-sm text-ink-soft">Nicio încasare.</p>
            @endforelse
            @if ($summary->methods)
                <div class="{{ $row }} border-t border-border pt-1.5"><span class="font-medium text-ink">Total</span><span class="font-semibold text-ink">{{ $lei($collected) }}</span></div>
            @endif
        </div>

        <div class="{{ $card }} space-y-1.5">
            <h2 class="text-sm font-semibold text-ink">Tokeni și credite</h2>
            <div class="{{ $row }}"><span class="text-ink-soft">Tokeni vânduți · {{ $summary->tokens_sold }}</span><span class="font-medium text-ink">{{ $lei($summary->tokens_amount) }}</span></div>
            <div class="{{ $row }}"><span class="text-ink-soft">Credite vândute · {{ $summary->credit_sales }}</span><span class="font-medium text-ink">{{ $lei($summary->credits_amount) }}</span></div>
            @if ($summary->token_sales_cancelled + $summary->credit_sales_cancelled > 0)
                <div class="{{ $row }}"><span class="text-ink-soft">Vânzări anulate</span><span class="font-medium text-ink">{{ $summary->token_sales_cancelled + $summary->credit_sales_cancelled }}</span></div>
            @endif
        </div>

        <p class="text-xs text-ink-soft">Doar de citit. Numărarea casei și trimiterea se fac la Raportare.</p>
    @endif
</div>
