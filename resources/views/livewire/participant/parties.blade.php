<div>
    <div class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Program</div>
        <h1 class="pa-h1">Petreceri</h1>
    </div>

    <section class="pa-section" style="margin-top: 1.1rem">
        <h2 class="pa-h2">Următoare</h2>
        @forelse ($parties as $party)
            @include('livewire.participant._party-row', ['party' => $party])
        @empty
            <div class="pa-glass pa-pad pa-soft" style="text-align: center">Nu sunt petreceri programate acum. Revino curând!</div>
        @endforelse
        @if ($hasMore)
            @include('livewire.participant._lazy-sentinel', ['limit' => $limit])
        @endif
    </section>

    @if ($past->isNotEmpty())
        <section class="pa-section" data-past-parties>
            <h2 class="pa-h2">Trecute</h2>
            @foreach ($past as $party)
                <div style="opacity: .75" wire:key="past-{{ $party->id }}">@include('livewire.participant._party-row', ['party' => $party])</div>
            @endforeach
            @if ($hasMorePast)
                <div wire:key="morepast-{{ $pastLimit }}" wire:intersect="morePast" class="pa-soft" style="text-align: center; font-size: .8rem; padding: .5rem 0">Se încarcă…</div>
            @endif
        </section>
    @endif
</div>
