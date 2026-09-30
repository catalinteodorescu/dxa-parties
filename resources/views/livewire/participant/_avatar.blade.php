{{-- DXA: adaugat (runda 12). Cerc cu poza participantului sau, fără poză, cu inițialele. Variabile: $who (Participant), $size (sm|lg). --}}
@php $cls = 'pa-avatar pa-avatar-'.($size ?? 'sm'); @endphp
@if ($who->hasAvatar())
    <img src="{{ $who->avatarUrl() }}" alt="" class="{{ $cls }}">
@else
    <span class="{{ $cls }}" aria-hidden="true">{{ $who->initials() }}</span>
@endif
