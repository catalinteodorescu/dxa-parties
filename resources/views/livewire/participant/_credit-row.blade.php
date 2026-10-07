{{-- DXA: adaugat (runda 16b). Rând din portofel: încărcare / plată / refund / ajustare, cu sumă cu semn. Variabilă: $t (CreditTransaction). --}}
@php $amount = (float) $t->amount; $label = \App\Models\CreditTransaction::TYPE_LABELS[$t->type] ?? $t->type; @endphp
<div class="pa-between pa-line" style="padding-top: .5rem; font-size: .9rem">
    <div style="min-width: 0">
        <div style="font-weight: 700">{{ $label }}@if ($t->source && $t->type === 'load' && $t->source !== \App\Models\CreditTransaction::SOURCE_APP) · {{ \App\Models\CreditTransaction::SOURCE_LABELS[$t->source] ?? '' }} @endif</div>
        <div class="pa-soft" style="font-size: .8rem">{{ $t->occurred_at?->format('d.m.Y H:i') }}@if ($t->note) · {{ $t->note }} @endif@if ((float) $t->bonus > 0) · din care bonus {{ number_format((float) $t->bonus, 2, ',', '.') }} lei @endif</div>
    </div>
    <div style="font-weight: 800; white-space: nowrap; {{ $amount > 0 ? 'color: var(--pa-amber)' : '' }}">{{ $amount > 0 ? '+' : '' }}{{ number_format($amount, 2, ',', '.') }}</div>
</div>
