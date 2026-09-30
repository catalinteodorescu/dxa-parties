{{-- DXA: adaugat (runda 16). Rând de consumație la bar. Variabilă: $s (Sale cu lines.menuItem și group.party). --}}
@php
    $items = $s->lines->map(fn ($l) => rtrim(rtrim(number_format((float) $l->qty, 3, '.', ''), '0'), '.').'× '.($l->menuItem?->name ?? 'produs'))->implode(', ');
@endphp
<div class="pa-between pa-line" style="padding-top: .5rem; font-size: .9rem; align-items: flex-start">
    <div style="min-width: 0">
        <div style="font-weight: 700">{{ $s->group?->party?->name ?? 'Bar' }}</div>
        <div class="pa-soft" style="font-size: .8rem">{{ $s->sold_at?->format('d.m.Y H:i') }}@if ($items !== '') · {{ $items }} @endif</div>
    </div>
    <div style="font-weight: 800; white-space: nowrap">{{ number_format((float) $s->total, 2, ',', '.') }} lei</div>
</div>
