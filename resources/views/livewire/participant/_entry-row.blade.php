{{-- DXA: adaugat (runda 16). Rând de intrare. Variabilă: $e (PartyEntry cu party). --}}
<div class="pa-between pa-line" style="padding-top: .5rem; font-size: .9rem">
    <div>
        <div style="font-weight: 700">{{ $e->party?->name ?? 'Petrecere' }}</div>
        <div class="pa-soft" style="font-size: .8rem">{{ $e->ticket_type }} · {{ $e->entered_at?->format('d.m.Y') }}</div>
    </div>
    <div style="font-weight: 800; white-space: nowrap">{{ $e->isFree() ? 'gratis' : ($e->isPaidOnline() && (float) $e->price_paid <= 0 ? number_format((float) $e->ticket->price, 2, ',', '.').' lei · online' : number_format((float) $e->price_paid, 2, ',', '.').' lei') }}</div>
</div>
