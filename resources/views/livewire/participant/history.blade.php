<div>
    <section class="pa-section" style="margin-top: .5rem">
        <a href="{{ $back }}" wire:navigate class="pa-link" style="font-size: .9rem">← Înapoi</a>
        <h1 class="pa-h1" style="font-size: 1.6rem">{{ $title }}</h1>
    </section>

    <section class="pa-section">
        <div class="pa-glass pa-pad pa-stack">
            @forelse ($rows as $r)
                <div wire:key="h-{{ $kind }}-{{ $r->id }}">
                    @if ($kind === 'tickets')
                        @include('livewire.participant._ticket-row', ['t' => $r])
                    @elseif ($kind === 'credits')
                        @include('livewire.participant._credit-row', ['t' => $r])
                    @elseif ($kind === 'entries')
                        @include('livewire.participant._entry-row', ['e' => $r])
                    @else
                        @include('livewire.participant._bar-row', ['s' => $r])
                    @endif
                </div>
            @empty
                <div class="pa-soft" style="font-size: .9rem">Nimic de arătat încă.</div>
            @endforelse
            @if ($hasMore)
                <button type="button" class="pa-link" style="padding-top: .25rem" wire:click="more" wire:loading.attr="disabled">Arată mai multe</button>
            @endif
        </div>
    </section>
</div>
