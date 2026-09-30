{{-- DXA: adaugat (Aplicația participanților - runda 14). Cumpărarea de bilete: buton + dialog centrat (comandă) sau, pentru
     vizitatorii neconectați, dialog de logare / înregistrare. Variabile: $party, $me, $options, $maxQty, $quote, $quoteError.
     Componenta părinte: Livewire\Participant\PartyShow. Fără plată online încă: comanda e „de plătit la intrare”. --}}
@php
    use App\Support\PartyPublic;
    $soldOut = $maxQty < 1;
    $hasMany = count($options) > 1;
    $grouped = $quote ? collect($quote->lines)->groupBy(fn ($l) => number_format($l['price'], 2, '.', ''))->map(fn ($g, $price) => ['n' => $g->count(), 'price' => (float) $price]) : collect();
@endphp
<div x-data="{ buy: false, auth: false }" @keydown.escape.window="buy = false; auth = false" id="bilete">
    @if ($soldOut)
        <div class="pa-glass pa-pad" style="text-align: center; font-weight: 800">Biletele online au fost epuizate.</div>
    @else
        <button type="button" class="pa-btn pa-btn-block" @click="{{ $me ? 'buy = true' : 'auth = true' }}">Cumpără bilete</button>
    @endif

    @guest('participant')
        {{-- Vizitator neconectat: logare sau înregistrare, apoi se întoarce la această petrecere. --}}
        <div x-show="auth" x-cloak class="pa-modal-bg" @click.self="auth = false" role="dialog" aria-modal="true" aria-label="Intră în cont">
            <div class="pa-modal pa-stack" style="gap: 1rem; text-align: center">
                <h2 class="pa-h2" style="margin: 0">Intră în cont ca să cumperi bilete</h2>
                <p class="pa-soft" style="margin: 0; font-size: .92rem">Biletele se leagă de contul tău, iar la intrare arăți doar codul tău personal. Te întorci aici imediat după.</p>
                <button type="button" class="pa-btn pa-btn-block" wire:click="goLogin">Intră în cont</button>
                <button type="button" class="pa-btn pa-btn-ghost pa-btn-block" wire:click="goRegister">Creează un cont</button>
                <button type="button" class="pa-link" style="font-size: .85rem" @click="auth = false">Nu acum</button>
            </div>
        </div>
    @endguest

    @if (! $soldOut)
    @auth('participant')
        <div x-show="buy" x-cloak class="pa-modal-bg" @click.self="buy = false" role="dialog" aria-modal="true" aria-label="Cumpără bilete">
            <div class="pa-modal pa-stack" style="gap: 1rem">
                <div class="pa-between">
                    <h2 class="pa-h2" style="margin: 0">Cumpără bilete</h2>
                    <button type="button" class="pa-link" @click="buy = false" aria-label="Închide">✕</button>
                </div>
                <div class="pa-soft" style="font-size: .88rem">{{ $party->name }} · {{ PartyPublic::dateLabel($party) }}</div>

                @if ($buyError)
                    <div class="pa-alert pa-alert-err" role="alert">{{ $buyError }}</div>
                @endif

                @if ($hasMany)
                    <div class="pa-stack" style="gap: .5rem">
                        @foreach ($options as $o)
                            <button type="button" wire:key="opt-{{ $loop->index }}" wire:click="selectTicket('{{ addslashes($o['name']) }}')"
                                    class="pa-tier {{ $ticketName === $o['name'] ? 'on' : '' }}" style="cursor: pointer; text-align: left; font: inherit; color: inherit; width: 100%" @if ($o['available'] === 0) disabled @endif>
                                <div style="flex: 1; min-width: 0">
                                    <div style="font-weight: 800">{{ $o['name'] }}</div>
                                    @if ($o['available'] !== null)
                                        <div class="pa-soft" style="font-size: .8rem">{{ $o['available'] === 0 ? 'epuizat' : 'mai sunt '.$o['available'] }}</div>
                                    @endif
                                </div>
                                <span style="text-align: right">
                                    @if ($o['was'] !== null) <s class="pa-soft pa-was" style="display: block; font-size: .78rem; font-weight: 700">{{ PartyPublic::lei($o['was']) }}</s> @endif
                                    <span class="pa-price" style="font-size: 1.05rem">{{ PartyPublic::lei($o['price']) }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="pa-between">
                    <div style="font-weight: 800">Număr de bilete</div>
                    <div style="display: flex; align-items: center; gap: .9rem">
                        <button type="button" class="pa-btn pa-btn-ghost pa-btn-sm" style="min-width: 2.75rem; padding: 0" wire:click="dec" aria-label="Mai puține" @if ($qty <= 1) disabled @endif>−</button>
                        <b style="font-size: 1.3rem; min-width: 1.5rem; text-align: center">{{ $qty }}</b>
                        <button type="button" class="pa-btn pa-btn-ghost pa-btn-sm" style="min-width: 2.75rem; padding: 0" wire:click="inc" aria-label="Mai multe" @if ($qty >= $maxQty) disabled @endif>+</button>
                    </div>
                </div>

                <div class="pa-soft" style="font-size: .82rem">Primul bilet e pe numele tău.@if ($qty > 1) La celelalte poți scrie telefonul participantului: dacă are cont, biletul apare și în contul lui; dacă nu, rămâne fără nume. @endif</div>

                @if ($qty > 1)
                    <div class="pa-stack" style="gap: .6rem">
                        @for ($i = 0; $i < $qty - 1; $i++)
                            <div wire:key="phone-{{ $i }}">
                                <label for="ph-{{ $i }}" class="pa-label">Bilet {{ $i + 2 }} — telefon participant (opțional)</label>
                                <input type="tel" id="ph-{{ $i }}" wire:model.blur="phones.{{ $i }}" inputmode="tel" placeholder="07XXXXXXXX" class="pa-input">
                            </div>
                        @endfor
                    </div>
                @endif

                {{-- Cod de reducere --}}
                <div>
                    <label for="pa-code" class="pa-label">Cod de reducere (opțional)</label>
                    @if ($appliedCode)
                        <div class="pa-between pa-glass" style="padding: .6rem .9rem; border-radius: 1rem">
                            <span style="font-weight: 800">{{ $appliedCode }} <span class="pa-soft" style="font-weight: 600">aplicat</span></span>
                            <button type="button" class="pa-link" wire:click="clearCode">Șterge</button>
                        </div>
                    @else
                        <div style="display: flex; gap: .5rem">
                            <input type="text" id="pa-code" wire:model="code" wire:keydown.enter.prevent="applyCode" autocomplete="off" autocapitalize="characters" class="pa-input" style="text-transform: uppercase">
                            <button type="button" class="pa-btn pa-btn-ghost pa-btn-sm" wire:click="applyCode" wire:loading.attr="disabled" wire:target="applyCode">Aplică</button>
                        </div>
                    @endif
                    @if ($codeError) <p class="pa-err">{{ $codeError }}</p> @endif
                </div>

                {{-- Sumar --}}
                @if ($quote)
                    <div class="pa-glass pa-pad pa-stack" style="gap: .4rem">
                        @foreach ($grouped as $g)
                            <div class="pa-between" style="font-size: .92rem"><span>{{ $g['n'] }} × {{ $ticketName }}</span><span>{{ PartyPublic::lei($g['price']) }}</span></div>
                        @endforeach
                        @if ($quote->discount > 0)
                            <div class="pa-between pa-soft" style="font-size: .85rem"><span>Reducere ({{ $appliedCode }})</span><span>−{{ PartyPublic::lei($quote->discount) }}</span></div>
                        @endif
                        <div class="pa-between pa-line" style="padding-top: .5rem"><b>Total</b><b class="pa-price" style="font-size: 1.3rem">{{ PartyPublic::lei($quote->total) }}</b></div>
                    </div>
                @elseif ($quoteError)
                    <p class="pa-err">{{ $quoteError }}</p>
                @endif

                <div class="pa-soft" style="font-size: .8rem">Plata se face la intrare. Comanda nu se poate anula din aplicație.</div>

                <div style="display: flex; gap: .5rem">
                    <button type="button" class="pa-btn pa-btn-ghost" style="flex: 1" @click="buy = false">Renunță</button>
                    <button type="button" class="pa-btn" style="flex: 1" wire:click="buy" wire:loading.attr="disabled" wire:target="buy" @if (! $quote) disabled @endif>Confirmă</button>
                </div>
            </div>
        </div>
    @endauth
    @endif
</div>
