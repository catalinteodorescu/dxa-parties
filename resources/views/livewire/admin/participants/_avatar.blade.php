{{-- DXA: adaugat (runda 12). Cerc mic înaintea numelui în lista de participanți: poza, sau inițialele dacă nu are. Variabilă: $p. --}}
@if ($p->hasAvatar())
    <img src="{{ $p->adminAvatarUrl() }}" alt="" loading="lazy" style="width: 2.25rem; height: 2.25rem; border-radius: 9999px; object-fit: cover; flex: none">
@else
    <span aria-hidden="true" style="width: 2.25rem; height: 2.25rem; border-radius: 9999px; flex: none; display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; color: #fff; background: linear-gradient(135deg, #DD6441, #E0489A); {{ $p->isAnonymized() ? 'opacity: .5;' : '' }}">{{ $p->isAnonymized() ? '—' : $p->initials() }}</span>
@endif
