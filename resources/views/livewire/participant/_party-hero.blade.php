{{-- DXA: adaugat (Aplicația participanților). Cardul mare din carusel pentru o petrecere. Variabilă: $party. --}}
@php
    use App\Support\PartyPublic;
    $price = PartyPublic::priceLabel($party);
    $image = PartyPublic::imageUrl($party);
@endphp
<a href="{{ route('app.party', $party) }}" wire:navigate class="pa-hero">
    @if ($image) <img src="{{ $image }}" alt="" loading="lazy"> @endif
    <div class="pa-hero-top">
        <span class="pa-chip" style="background: rgba(18,8,16,.55)">{{ PartyPublic::dateLabel($party) }}</span>
        @if ($party->state() === 'live') <span class="pa-chip pa-chip-amber">ACUM</span> @endif
    </div>
    <div class="pa-hero-in">
        <div style="font-size: 1.9rem; line-height: 1.05; font-weight: 800">{{ $party->name }}</div>
        <div style="font-size: .85rem; font-weight: 600; color: #EFE3DE">{{ trim(PartyPublic::timeLabel($party).($party->location_name ? ' · '.$party->location_name : ''), ' ·') }}</div>
        @if ($price)
            <div class="pa-row" style="justify-content: space-between; padding: .6rem .7rem .6rem 1rem; border-radius: 1.4rem; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22)">
                <span style="font-size: 1.15rem; font-weight: 800">{{ $price }}</span>
                <span class="pa-btn pa-btn-sm" style="background: #fff; color: #2a1208; box-shadow: none">Vezi petrecerea</span>
            </div>
        @endif
    </div>
</a>
