{{-- DXA: adaugat (runda 40). Inima „la favorite”. Variabile: $party, $savedIds (din componentă), opțional $style (poziționare). --}}
@php $saved = in_array($party->id, $savedIds ?? [], true); @endphp
<button type="button" wire:click.stop="toggleInterest({{ $party->id }})" class="pa-heart {{ $saved ? 'on' : '' }}" style="{{ $style ?? '' }}"
        aria-pressed="{{ $saved ? 'true' : 'false' }}" aria-label="{{ $saved ? 'Scoate din favorite' : 'Adaugă la favorite' }}" data-heart="{{ $party->id }}">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
</button>
