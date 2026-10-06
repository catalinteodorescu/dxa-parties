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
                    <div class="pa-dots" style="margin-top: 0" aria-hidden="true">
                        @foreach ($valid as $k => $t)
                            <span :class="{ 'on': i === {{ $k }} }"></span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </section>

    @if ($recent->isNotEmpty() || $past->isNotEmpty())
        <section class="pa-section">
            <div class="pa-glass pa-pad pa-stack">
                <div class="pa-label" style="margin: 0">Ultimele bilete</div>
                @foreach ($past as $t)
                    <div wire:key="pt-{{ $t->id }}">@include('livewire.participant._ticket-row', ['t' => $t])</div>
                @endforeach
                @foreach ($recent as $t)
                    <div wire:key="rt-{{ $t->id }}">@include('livewire.participant._ticket-row', ['t' => $t])</div>
                @endforeach
                @if ($hasMore)
                    @include('livewire.participant._lazy-sentinel', ['limit' => $limit])
                @endif
            </div>
        </section>
    @endif

    {{-- DXA: adaugat (runda 39). „Trimite biletul”: dialog centrat în doi pași (telefon → confirmare). --}}
    @if ($sendTicket)
        <div class="pa-modal-bg" wire:key="send-dialog" wire:click.self="cancelSend" wire:keydown.escape.window="cancelSend" role="dialog" aria-modal="true" aria-label="Trimite biletul" data-send-dialog>
            <div class="pa-modal pa-stack" style="gap: 1rem; text-align: center">
                <h2 class="pa-h2" style="margin: 0">Trimite biletul</h2>
                <p class="pa-soft" style="margin: 0; font-size: .92rem">{{ $sendTicket->party?->name }} · {{ $sendTicket->ticket_type }}</p>

                @if ($sendError)
                    <div class="pa-alert pa-alert-err" role="alert">{{ $sendError }}</div>
                @endif

                @if ($sendStep === 'form')
                    <div style="text-align: left">
                        <label for="send-phone" class="pa-label">Telefonul persoanei care primește biletul</label>
                        <input type="tel" id="send-phone" wire:model="sendPhone" wire:keydown.enter="checkSend" inputmode="tel" autocomplete="off" placeholder="07XXXXXXXX" class="pa-input">
                    </div>
                    <button type="button" class="pa-btn pa-btn-block" wire:click="checkSend">Continuă</button>
                @else
                    <p style="margin: 0; font-size: .95rem">
                        @if ($sendInfo['has_account'] ?? false)
                            Biletul va ajunge în contul lui <strong>{{ $sendInfo['name'] }}</strong> ({{ $sendInfo['phone'] }}).
                        @else
                            Numărul <strong>{{ $sendInfo['phone'] ?? '' }}</strong> nu are cont. Îi trimitem un SMS cu un link către bilet și îl îndrumăm să-și facă cont.
                        @endif
                    </p>
                    <p class="pa-soft" style="margin: 0; font-size: .85rem">Biletul va ieși din contul tău și nu poți anula trimiterea.</p>
                    <button type="button" class="pa-btn pa-btn-block" wire:click="confirmSend" wire:loading.attr="disabled">Trimite biletul</button>
                    <button type="button" class="pa-btn pa-btn-ghost pa-btn-block" wire:click="backSend">Înapoi</button>
                @endif

                <button type="button" class="pa-link" style="font-size: .85rem" wire:click="cancelSend">Renunț</button>
            </div>
        </div>
    @endif
</div>
