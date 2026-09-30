{{-- DXA: adaugat (Aplicația participanților - runda 12c). Cardul de fidelitate ca un card fizic: gradient, luciu, cercuri de ștampile
     și „intrare gratis" la final. Variabile: $me, $card, $stamps (ștampilele valide, în ordine). Doar afișare. --}}
@php
    $required = $card->stamps_required;
    $count = $stamps->count();
    $complete = $count >= $required;
@endphp
<div class="pa-lcard" role="group" aria-label="Card de fidelitate: {{ $count }} din {{ $required }} ștampile">
    <div class="pa-lcard-top">
        <div>
            <div class="pa-lcard-brand">{{ \App\Support\Branding::name() }}</div>
            <div class="pa-lcard-kicker">Card de fidelitate</div>
        </div>
        <div class="pa-lcard-count"><b>{{ $count }}</b><small>/ {{ $required }}</small></div>
    </div>

    <div class="pa-lcard-stamps">
        @for ($i = 0; $i < $required; $i++)
            @php($s = $stamps->get($i))
            <div class="pa-lstamp {{ $s ? 'on' : '' }}" title="{{ $s ? $s->stamped_at->format('d.m.Y') : 'necompletat' }}">
                @if ($s)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                @else
                    <span>{{ $i + 1 }}</span>
                @endif
            </div>
        @endfor
        <div class="pa-lstamp gift {{ $complete ? 'ready' : '' }}" title="Intrare gratis">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7Z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7Z"/></svg>
        </div>
    </div>

    <div class="pa-lcard-bottom">
        <div>
            <div class="pa-lcard-name">{{ $me->name }}</div>
            <div class="pa-lcard-note">
                @if ($complete)
                    Următoarea intrare e gratis! 🎉
                @else
                    Mai ai {{ $required - $count }} {{ $required - $count === 1 ? 'ștampilă' : 'ștampile' }} până la intrarea gratis
                @endif
            </div>
        </div>
        <div class="pa-lcard-no">#{{ str_pad((string) $card->id, 4, '0', STR_PAD_LEFT) }}</div>
    </div>
</div>
