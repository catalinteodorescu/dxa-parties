<div>
    <div class="pa-section" style="margin-top: .5rem">
        <a href="{{ route('app.home') }}" wire:navigate class="pa-link" style="font-size: .9rem">← Acasă</a>
        <h1 class="pa-h1" style="font-size: 1.6rem">Anunțuri</h1>
    </div>

    <section class="pa-section" style="margin-top: 1.1rem">
        @forelse ($announcements as $announcement)
            <div wire:key="an-{{ $announcement->id }}">@include('livewire.participant._announcement-row', ['announcement' => $announcement])</div>
        @empty
            <div class="pa-glass pa-pad pa-soft" style="text-align: center">Niciun anunț acum.</div>
        @endforelse
        @if ($hasMore)
            @include('livewire.participant._lazy-sentinel', ['limit' => $limit])
        @endif
    </section>
</div>
