{{-- DXA: adaugat (runda 16b). Rând de anunț ca rândul de petrecere: imaginea (dacă există) în stânga, fără placeholder. Variabilă: $announcement. --}}
@php $tag = $announcement->url ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($announcement->url) href="{{ $announcement->url }}" target="_blank" rel="noopener" @endif class="pa-a pa-glass" style="display: flex; align-items: center; gap: .85rem; padding: .75rem .9rem .75rem .75rem">
    @if ($announcement->imageUrl())
        <img src="{{ $announcement->imageUrl() }}" alt="" loading="lazy" style="width: 3.5rem; height: 4rem; flex: none; border-radius: 1rem; object-fit: cover; display: block">
    @endif
    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .15rem">
        <span style="font-weight: 800; font-size: 1rem">{{ $announcement->title }}</span>
        @if ($announcement->body)
            <span class="pa-soft" style="font-size: .85rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden">{{ $announcement->body }}</span>
        @endif
    </div>
</{{ $tag }}>
