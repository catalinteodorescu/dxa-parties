{{-- DXA: adaugat (Aplicația participanților). Un anunț (în carusel sau în listă). Variabilă: $announcement. --}}
<div class="pa-glass pa-stack" style="overflow: hidden; gap: 0">
    @if ($announcement->imageUrl())
        <img src="{{ $announcement->imageUrl() }}" alt="" loading="lazy" style="width: 100%; max-height: 12rem; object-fit: cover; display: block">
    @endif
    <div class="pa-pad pa-stack" style="gap: .5rem">
        <div style="font-weight: 800; font-size: 1.05rem">{{ $announcement->title }}</div>
        @if ($announcement->body)
            <div class="pa-soft pa-prose" style="font-size: .92rem">{{ \Illuminate\Support\Str::limit($announcement->body, 220) }}</div>
        @endif
        @if ($announcement->url)
            <div><a href="{{ $announcement->url }}" target="_blank" rel="noopener" class="pa-btn pa-btn-ghost pa-btn-sm">{{ $announcement->url_label ?: 'Vezi mai mult' }}</a></div>
        @endif
    </div>
</div>
