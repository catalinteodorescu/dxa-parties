{{-- DXA: adaugat (runda 16b). Rând de anunț (duce la pagina anunțului) ca rândul de petrecere: imaginea în stânga; fără imagine, un placeholder (runda 29). Variabilă: $announcement. --}}
<a href="{{ route('app.announcement', $announcement) }}" wire:navigate class="pa-a pa-glass" x-data x-track="'a:{{ $announcement->id }}'" style="display: flex; align-items: center; gap: .85rem; padding: .75rem .9rem .75rem .75rem">
    @if ($announcement->imageUrl())
        <img src="{{ $announcement->imageUrl() }}" alt="" loading="lazy" style="width: 3.5rem; height: 4rem; flex: none; border-radius: 1rem; object-fit: cover; display: block">
    @else
        <span class="pa-ph" style="width: 3.5rem; height: 4rem; flex: none; border-radius: 1rem"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg></span>
    @endif
    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .15rem">
        <span style="font-weight: 800; font-size: 1rem">{{ $announcement->title }}</span>
        @include('livewire.participant._announcement-date', ['announcement' => $announcement])
        @if ($announcement->body)
            <span class="pa-soft" style="font-size: .85rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden">{{ $announcement->body }}</span>
        @endif
    </div>
</a>
