{{-- Runda 91. Pagina „Notificări” din aplicația participanților. Variabile: $items, $groups (pe zile), $hasMore, $limit, $unread, $pushEnabled. --}}
<div>
    <div class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Ale tale</div>
        <h1 class="pa-h1">Notificări</h1>
    </div>

    @if ($pushEnabled)
        <div style="margin-top: .9rem">@include('livewire.participant._push-card')</div>
    @endif

    @if ($items->isEmpty())
        <div class="pa-glass pa-pad pa-soft" style="text-align: center; margin-top: 1rem" data-notifications-empty>
            Nu ai nicio notificare încă. Aici apar petrecerile noi, biletele tale și anunțurile.
        </div>
    @else
        <div class="pa-between" style="margin-top: 1rem; min-height: 2.75rem">
            <span class="pa-soft" style="font-size: .85rem" data-unread-count>{{ $unread > 0 ? $unread.($unread === 1 ? ' necitită' : ' necitite') : 'Toate citite' }}</span>
            @if ($unread > 0)
                <button type="button" class="pa-link" style="min-height: 2.75rem; padding: 0 .25rem" wire:click="markAll" data-mark-all>Marchează toate ca citite</button>
            @endif
        </div>

        @foreach ($groups as $day => $rows)
            <section class="pa-section" style="margin-top: .6rem" wire:key="day-{{ $day }}">
                <h2 class="pa-h2">{{ $day }}</h2>
                @foreach ($rows as $n)
                    <button type="button" wire:key="n-{{ $n->id }}" wire:click="open({{ $n->id }})" data-notification="{{ $n->id }}" data-unread="{{ $n->read_at ? '0' : '1' }}"
                            class="pa-glass" style="display: flex; gap: .75rem; width: 100%; text-align: left; padding: .9rem 1rem; margin-bottom: .5rem; color: inherit; font: inherit; cursor: pointer; border: 1px solid {{ $n->read_at ? 'var(--pa-line)' : 'rgba(255,179,107,.55)' }}">
                        <span aria-hidden="true" style="flex: none; width: .6rem; height: .6rem; margin-top: .45rem; border-radius: 50%; background: {{ $n->read_at ? 'transparent' : 'var(--pa-amber)' }}"></span>
                        <span style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .2rem">
                            <span style="display: flex; justify-content: space-between; gap: .6rem">
                                <b style="font-weight: 800; line-height: 1.3">{{ $n->title }}</b>
                                <small class="pa-soft" style="flex: none">{{ $n->created_at->format('H:i') }}</small>
                            </span>
                            <span class="pa-soft" style="font-size: .9rem; line-height: 1.4">{{ $n->body }}</span>
                            <span class="pa-soft" style="font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase">{{ $n->kindLabel() }}</span>
                        </span>
                    </button>
                @endforeach
            </section>
        @endforeach

        @if ($hasMore)
            @include('livewire.participant._lazy-sentinel', ['limit' => $limit])
        @endif
    @endif
</div>
