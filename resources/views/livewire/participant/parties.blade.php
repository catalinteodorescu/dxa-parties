<div>
    <div class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Program</div>
        <h1 class="pa-h1">Petreceri</h1>
    </div>

    {{-- DXA: adaugat (runda 40). Toate | Favorite (petrecerile cu inimă). --}}
    <div class="pa-tabs" role="tablist" aria-label="Lista de petreceri">
        <button type="button" role="tab" class="{{ $onlySaved ? '' : 'on' }}" aria-selected="{{ $onlySaved ? 'false' : 'true' }}" wire:click="showView('all')" data-view="all">Toate</button>
        <button type="button" role="tab" class="{{ $onlySaved ? 'on' : '' }}" aria-selected="{{ $onlySaved ? 'true' : 'false' }}" wire:click="showView('saved')" data-view="saved">Favorite</button>
    </div>

    <section class="pa-section" style="margin-top: 1.1rem">
        <h2 class="pa-h2">Următoare</h2>
        @forelse ($parties as $party)
            @include('livewire.participant._party-row', ['party' => $party])
        @empty
            <div class="pa-glass pa-pad pa-soft" style="text-align: center" @if ($onlySaved) data-saved-empty @endif>
                @if ($onlySaved)
                    Nu ai petreceri la favorite. Apasă inima de pe o petrecere ca s-o găsești aici.
                @else
                    Nu sunt petreceri programate acum. Revino curând!
                @endif
            </div>
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
