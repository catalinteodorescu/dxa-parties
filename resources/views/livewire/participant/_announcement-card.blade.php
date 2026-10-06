{{-- DXA: adaugat (Aplicația participanților). Un anunț (în carusel sau în listă). Variabilă: $announcement. --}}
<div class="pa-glass pa-stack" style="overflow: hidden; gap: 0" x-data x-track="'a:{{ $announcement->id }}'">
    @if ($announcement->imageUrl())
        <img src="{{ $announcement->imageUrl() }}" alt="" loading="lazy" style="width: 100%; max-height: 12rem; object-fit: cover; display: block">
    @else
        <div class="pa-ph" style="width: 100%; height: 5.5rem"><svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg></div>
    @endif
    <div class="pa-pad pa-stack" style="gap: .5rem">
        <div style="font-weight: 800; font-size: 1.05rem">{{ $announcement->title }}</div>
        @include('livewire.participant._announcement-date', ['announcement' => $announcement])
        @if ($announcement->body)
            <div class="pa-soft" style="font-size: .92rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden">{{ $announcement->body }}</div>
        @endif
        <div><a href="{{ route('app.announcement', $announcement) }}" wire:navigate class="pa-btn pa-btn-ghost pa-btn-sm">Citește tot</a></div>
    </div>
</div>
