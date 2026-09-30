<div>
    <div class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Program</div>
        <h1 class="pa-h1">Petreceri</h1>
    </div>

    <section class="pa-section" style="margin-top: 1.1rem">
        @forelse ($parties as $party)
            @include('livewire.participant._party-row', ['party' => $party])
        @empty
            <div class="pa-glass pa-pad pa-soft" style="text-align: center">Nu sunt petreceri programate acum. Revino curând!</div>
        @endforelse
    </section>
</div>
