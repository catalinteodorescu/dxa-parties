{{-- DXA: adaugat (Aplicația participanților). Rând de petrecere: data, nume, ora/locul și prețul „de la”. Variabilă: $party. --}}
@php
    use App\Support\PartyPublic;
    $price = PartyPublic::priceLabel($party);
@endphp
<a href="{{ route('app.party', $party) }}" wire:navigate class="pa-a pa-glass" style="display: flex; align-items: center; gap: .85rem; padding: .75rem .9rem .75rem .75rem">
    <div class="pa-date"><b>{{ PartyPublic::dayNumber($party) }}</b><small>{{ PartyPublic::monthShort($party) }}</small></div>
    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .15rem">
        <span style="font-weight: 800; font-size: 1rem">{{ $party->name }}</span>
        <span class="pa-soft" style="font-size: .85rem">{{ trim(PartyPublic::timeLabel($party).($party->location_name ? ' · '.$party->location_name : ''), ' ·') }}</span>
    </div>
    @if ($price)
        <span class="pa-price" style="font-size: .95rem; text-align: right">{{ $price }}</span>
    @endif
</a>
