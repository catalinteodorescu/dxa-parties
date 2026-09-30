<div>
    @include('livewire.participant._flash')

    <div class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Seara următoare</div>
        <h1 class="pa-h1">Hai la dans,<br>hai la petrecere.</h1>
    </div>

    @if ($carousel->isNotEmpty())
        <div class="pa-section" style="margin-top: 1.1rem" x-data="{ i: 0 }">
            <div class="pa-carousel" x-ref="track" @scroll.passive="i = Math.round($refs.track.scrollLeft / $refs.track.clientWidth)">
                @foreach ($carousel as $item)
                    @if ($item['kind'] === 'party')
                        @include('livewire.participant._party-hero', ['party' => $item['model']])
                    @else
                        @include('livewire.participant._announcement-card', ['announcement' => $item['model']])
                    @endif
                @endforeach
            </div>
            @if ($carousel->count() > 1)
                <div class="pa-dots" aria-hidden="true">
                    @foreach ($carousel as $n => $item)
                        <span :class="{ 'on': i === {{ $n }} }"></span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if ($announcements->isNotEmpty())
        <section class="pa-section">
            <h2 class="pa-h2">Anunțuri</h2>
            @foreach ($announcements as $announcement)
                @include('livewire.participant._announcement-card', ['announcement' => $announcement])
            @endforeach
        </section>
    @endif

    <section class="pa-section">
        <div class="pa-between">
            <h2 class="pa-h2">Petreceri următoare</h2>
            <a href="{{ route('app.parties') }}" wire:navigate class="pa-link" style="font-size: .85rem">Toate</a>
        </div>
        @forelse ($parties as $party)
            @include('livewire.participant._party-row', ['party' => $party])
        @empty
            <div class="pa-glass pa-pad pa-soft" style="text-align: center">Nu sunt petreceri programate acum. Revino curând!</div>
        @endforelse
    </section>
</div>
