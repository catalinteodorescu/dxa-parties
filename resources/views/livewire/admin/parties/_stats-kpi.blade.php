{{-- Card KPI: valoare + (daca exista comparatie) valoarea petrecerii comparate si diferenta. Variabile: $k, $big --}}
@php
    $d = $cmp ? $delta($k->type, $k->value, $k->compare, $k->direction) : null;
    $negative = $k->key === 'profit' && $k->value !== null && $k->value < 0;
@endphp
<div class="rounded-2xl border border-border bg-surface {{ $big ? 'p-4' : 'p-3.5' }}">
    <div class="text-[11px] uppercase tracking-wide text-ink-soft/80">{{ $k->label }}</div>
    <div class="mt-1 {{ $big ? 'text-xl' : 'text-base' }} font-semibold {{ $negative ? 'text-danger' : 'text-ink' }}">{{ $fmtVal($k->type, $k->value) }}</div>
    @if ($k->key === 'cost' && $stats->cost_unknown)
        <div class="mt-0.5 text-[11px] text-warning">cost necunoscut la unele produse</div>
    @endif
    @if ($cmp)
        <div class="mt-1.5 flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-xs text-ink-soft">
            <span title="{{ $compareParty->name }}">vs {{ $fmtVal($k->type, $k->compare) }}</span>
            @if ($d)
                <span class="font-medium {{ $d['class'] }}">{{ $d['text'] }}</span>
            @endif
        </div>
    @endif
</div>
