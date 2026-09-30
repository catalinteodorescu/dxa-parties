<div>
    <section class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Bilete</div>
        <h1 class="pa-h1">Biletele mele</h1>
    </section>

    <section class="pa-section">
        @if ($valid->isEmpty())
            <div class="pa-glass pa-pad pa-stack" style="text-align: center">
                <div style="font-weight: 800">Nu ai bilete valabile</div>
                <div class="pa-soft" style="font-size: .9rem">Cumpără un bilet de pe pagina unei petreceri.</div>
                <a href="{{ route('app.parties') }}" wire:navigate class="pa-btn pa-btn-sm" style="align-self: center">Vezi petrecerile</a>
            </div>
        @else
            {{-- Carusel: un bilet pe ecran, cu glisare; punctele arată poziția. --}}
            <div x-data="{ i: 0 }" class="pa-stack" style="gap: .6rem" data-tickets-carousel>
                <div style="display: flex; gap: .75rem; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; -webkit-overflow-scrolling: touch"
                     @scroll.passive="i = Math.round($el.scrollLeft / $el.clientWidth)">
                    @foreach ($valid as $t)
                        <div style="flex: 0 0 100%; scroll-snap-align: center; box-sizing: border-box" wire:key="vt-{{ $t->id }}">
                            @include('livewire.participant._ticket-card', ['t' => $t])
                        </div>
                    @endforeach
                </div>
                @if ($valid->count() > 1)
                    <div style="display: flex; justify-content: center; gap: .4rem" aria-hidden="true">
                        @foreach ($valid as $k => $t)
                            <span :style="i === {{ $k }} ? 'background: var(--pa-amber); width: 1.3rem' : 'background: var(--pa-line); width: .5rem'" style="height: .5rem; border-radius: .25rem; transition: all .2s"></span>
                        @endforeach
                    </div>
                    <div class="pa-soft" style="text-align: center; font-size: .8rem">{{ $valid->count() }} bilete valabile · glisează</div>
                @endif
            </div>
        @endif
    </section>

    @if ($recent->isNotEmpty())
        <section class="pa-section">
            <div class="pa-glass pa-pad pa-stack">
                <div class="pa-label" style="margin: 0">Ultimele bilete</div>
                @foreach ($recent as $t)
                    <div wire:key="rt-{{ $t->id }}">@include('livewire.participant._ticket-row', ['t' => $t])</div>
                @endforeach
                @if ($hasMore)
                    <a href="{{ route('app.tickets.all') }}" wire:navigate class="pa-link" style="text-align: center; padding-top: .25rem">Vezi mai mult</a>
                @endif
            </div>
        </section>
    @endif
</div>
