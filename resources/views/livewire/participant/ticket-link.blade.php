{{-- DXA: adaugat (runda 39). Pagina publică a unui bilet primit prin SMS. Variabile: $ticket, $state (ok|account|gone). --}}
<div class="pa-stack" style="gap: 1.25rem">
    @if ($state === 'ok')
        <div style="text-align: center">
            <h1 class="pa-h1">{{ $ticket->holder ? 'Ai primit un bilet' : 'Biletul tău' }}</h1>
            <p class="pa-soft" style="margin: .4rem 0 0">{{ $ticket->holder ? 'Arată codul de mai jos la intrare sau, mai bine, fă-ți cont în aplicație și îl vei avea mereu la îndemână.' : 'Arată codul de mai jos la intrare. Salvează pagina sau fă o captură de ecran: oricine are linkul poate folosi biletul.' }}</p>
        </div>

        @include('livewire.participant._ticket-card', ['t' => $ticket, 'public' => true])

        @if ($ticket->holder)
        <div class="pa-glass pa-pad pa-stack" style="gap: .75rem">
            <div class="pa-label" style="margin: 0">Cum ai biletul în aplicație</div>
            <ol class="pa-soft" style="margin: 0; padding-left: 1.2rem; font-size: .92rem; line-height: 1.5">
                <li>Instalează aplicația: din meniul browserului alege „Adaugă pe ecranul principal”.</li>
                <li>Creează un cont cu numărul <strong>{{ $ticket->holder?->phone }}</strong> (primești un cod prin SMS).</li>
                <li>Biletul apare automat la „Bilete”.</li>
            </ol>
            <a href="{{ route('app.register', ['telefon' => $ticket->holder?->phone]) }}" wire:navigate class="pa-btn pa-btn-block">Creează cont</a>
        </div>
        @endif
    @elseif ($state === 'account')
        <div class="pa-glass pa-pad pa-stack" style="text-align: center">
            <h1 class="pa-h1">Biletul e în contul tău</h1>
            <p class="pa-soft" style="margin: 0">Numărul acesta are deja cont. Intră în cont și găsești biletul la „Bilete”.</p>
            <a href="{{ route('app.login') }}" wire:navigate class="pa-btn pa-btn-block">Intră în cont</a>
        </div>
    @else
        <div class="pa-glass pa-pad pa-stack" style="text-align: center">
            <h1 class="pa-h1">Linkul nu mai este valabil</h1>
            <p class="pa-soft" style="margin: 0">Biletul a fost folosit, anulat sau trimis altcuiva. Dacă ai cont, intră în el și vezi biletele tale.</p>
            <a href="{{ route('app.login') }}" wire:navigate class="pa-btn pa-btn-block">Intră în cont</a>
        </div>
    @endif
</div>
