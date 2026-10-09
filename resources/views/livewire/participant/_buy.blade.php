{{-- DXA: adaugat (Aplicația participanților - runda 14). Cumpărarea de bilete: buton + dialog centrat (comandă) sau, pentru
     vizitatorii neconectați, dialog de logare / înregistrare. Variabile: $party, $me, $options, $maxQty, $quote, $quoteError, $combos (combo-urile tipului ales), $count (biletele din comandă), $creditBalance (soldul, doar dacă acoperă totalul).
     Componenta părinte: Livewire\Participant\PartyShow. „Confirmă” = plata cu cardul prin Stripe când e activ (runda 65), altfel comanda de plătit la intrare; runda 50: „Plătesc cu credite”, cu confirmare, doar dacă soldul acoperă totalul. --}}
@php
    use App\Support\PartyPublic;
    $soldOut = $maxQty < 1;
    $hasMany = count($options) > 1;
    $grouped = $quote ? collect($quote->lines)->groupBy(fn ($l) => ($l['free'] ? 'f' : 'p').number_format($l['price'], 2, '.', ''))->map(fn ($g) => ['n' => $g->count(), 'price' => (float) $g->first()['price'], 'free' => (bool) $g->first()['free']])->values() : collect();
    $comboDef = $combo !== '' ? collect($combos)->firstWhere('key', $combo) : null;
@endphp
<div x-data="{ buy: false, auth: false, cr: false }" @keydown.escape.window="buy = false; auth = false; cr = false" id="bilete">
    @if ($soldOut)
        <div class="pa-glass pa-pad" style="text-align: center; font-weight: 800">Biletele online au fost epuizate.</div>
    @else
        <button type="button" class="pa-btn pa-btn-block" data-track-action="p:{{ $party->id }}" @click="{{ $me ? 'buy = true' : 'auth = true' }}">Cumpără bilete</button>
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

                @if ($combos)
                    <div class="pa-stack" style="gap: .5rem">
                        <div style="font-weight: 800">Alege tipul de bilet</div>
                        <button type="button" wire:click="selectCombo('')" class="pa-tier {{ $combo === '' ? 'on' : '' }}" style="cursor: pointer; text-align: left; font: inherit; color: inherit; width: 100%">
                            <div style="flex: 1; font-weight: 800">Bilete individuale</div>
                        </button>
                        @foreach ($combos as $c)
                            <button type="button" wire:key="combo-{{ $c['key'] }}" wire:click="selectCombo('{{ $c['key'] }}')" class="pa-tier {{ $combo === $c['key'] ? 'on' : '' }}" style="cursor: pointer; text-align: left; font: inherit; color: inherit; width: 100%">
                                <div style="flex: 1; min-width: 0">
                                    <div style="font-weight: 800">Combo {{ $c['key'] }}</div>
                                    <div class="pa-soft" style="font-size: .8rem">Plătești {{ $c['buy'] }}, primești {{ $c['size'] }} bilete ({{ $c['free'] }} gratis)</div>
                                    @if ($c['note']) <div style="font-size: .78rem; font-weight: 700; color: var(--pa-amber, inherit)">{{ $c['note'] }}</div> @endif
                                </div>
                                <span class="pa-chip pa-chip-amber">{{ $c['size'] }} bilete</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="pa-between">
                    <div style="font-weight: 800">{{ $comboDef ? 'Număr de seturi' : 'Număr de bilete' }}@if ($comboDef) <span class="pa-soft" style="font-weight: 600; font-size: .8rem">· {{ $count }} bilete</span> @endif</div>
                    <div style="display: flex; align-items: center; gap: .9rem">
                        <button type="button" class="pa-btn pa-btn-ghost pa-btn-sm" style="min-width: 2.75rem; padding: 0" wire:click="dec" aria-label="Mai puține" @if ($qty <= 1) disabled @endif>−</button>
                        <b style="font-size: 1.3rem; min-width: 1.5rem; text-align: center">{{ $qty }}</b>
                        <button type="button" class="pa-btn pa-btn-ghost pa-btn-sm" style="min-width: 2.75rem; padding: 0" wire:click="inc" aria-label="Mai multe" @if ($qty >= $maxQty) disabled @endif>+</button>
                    </div>
                </div>

                @if ($qty >= $maxQty && $capReason)
                    <div class="pa-soft" style="font-size: .82rem; color: #ffd9a0" data-qty-cap>{{ $capReason }}</div>
                @endif

                <div class="pa-soft" style="font-size: .82rem">Primul bilet e pe numele tău.@if ($count > 1) Celelalte rămân în contul tău, libere: le poți trimite altcuiva din Bilete. @endif</div>

                {{-- Cod de reducere: buton care deschide formularul (deschis din start dacă e aplicat sau are eroare) --}}
                <div x-data="{ o: {{ $appliedCode || $codeError ? 'true' : 'false' }} }">
                    <button type="button" class="pa-link" style="font-size: .9rem; font-weight: 800" @click="o = ! o" :aria-expanded="o">Ai un cod de reducere? <span x-text="o ? '▲' : '▼'">▼</span></button>
                    <div x-show="o" x-cloak style="margin-top: .5rem">
                        @if ($appliedCode)
                            <div class="pa-between pa-glass" style="padding: .6rem .9rem; border-radius: 1rem">
                                <span style="font-weight: 800">{{ $appliedCode }} <span class="pa-soft" style="font-weight: 600">aplicat</span></span>
                                <button type="button" class="pa-link" wire:click="clearCode">Șterge</button>
                            </div>
                        @else
                            <label for="pa-code" class="pa-label">Cod de reducere</label>
                            <div style="display: flex; gap: .5rem">
                                <input type="text" id="pa-code" wire:model="code" wire:keydown.enter.prevent="applyCode" autocomplete="off" autocapitalize="characters" class="pa-input" style="text-transform: uppercase">
                                <button type="button" class="pa-btn pa-btn-ghost pa-btn-sm" wire:click="applyCode" wire:loading.attr="disabled" wire:target="applyCode">Aplică</button>
                            </div>
                        @endif
                        @if ($codeError) <p class="pa-err">{{ $codeError }}</p> @endif
                    </div>
                </div>

                {{-- Sumar --}}
                @if ($quote)
                    <div class="pa-glass pa-pad pa-stack" style="gap: .4rem">
                        @foreach ($grouped as $g)
                            <div class="pa-between" style="font-size: .92rem"><span>{{ $g['n'] }} × {{ $ticketName }}@if ($g['free']) <span class="pa-chip pa-chip-amber" style="height: 1.4rem">gratis</span>@endif</span><span>{{ $g['free'] ? '0 lei' : PartyPublic::lei($g['price']) }}</span></div>
                        @endforeach
                        @php
                            $paidLines = collect($quote->lines)->reject(fn ($l) => $l['free'])->values();
                            $segments = [];
                            foreach ($paidLines as $l) {
                                $k = number_format($l['list'], 2, '.', '');
                                if ($segments && $segments[count($segments) - 1]['k'] === $k) { $segments[count($segments) - 1]['n']++; } else { $segments[] = ['k' => $k, 'n' => 1, 'price' => (float) $l['list']]; }
                            }
                        @endphp
                        @if (count($segments) > 1)
                            <div class="pa-soft" style="font-size: .8rem" data-price-steps>
                                Prețul diferă în comandă: @foreach ($segments as $sg){{ $loop->first ? 'primele' : ($loop->last ? 'iar următoarele' : 'apoi') }} {{ $sg['n'] }} {{ $sg['n'] === 1 ? 'bilet' : 'bilete' }} {{ $loop->first ? 'sunt' : 'costă' }} {{ PartyPublic::lei($sg['price']) }}{{ $loop->last ? '' : ', ' }}@endforeach.
                                Oferta la preț redus are un număr limitat de bilete; ce depășește limita se vinde la prețul următor.
                            </div>
                        @endif
                        @if ($quote->discount > 0)
                            @if ($count > ($quote->code_applied ?? $count) && $quote->code_applied > 0)
                                <div class="pa-soft" style="font-size: .8rem">Codul se aplică la {{ $quote->code_applied }} din cele {{ $count }} bilete (limita codului); restul se plătesc la prețul întreg.</div>
                            @endif
                            <div class="pa-between pa-soft" style="font-size: .85rem"><span>Reducere ({{ $appliedCode }})</span><span>−{{ PartyPublic::lei($quote->discount) }}</span></div>
                        @endif
                        <div class="pa-between pa-line" style="padding-top: .5rem"><b>Total</b><b class="pa-price" style="font-size: 1.3rem">{{ PartyPublic::lei($quote->total) }}</b></div>
                    </div>
                @elseif ($quoteError)
                    <p class="pa-err">{{ $quoteError }}</p>
                @endif

                @if ($cardPay)
                    <div class="pa-soft" style="font-size: .85rem" data-card-note>Plătești cu cardul, în siguranță, pe pagina Stripe. Biletele îți sunt rezervate {{ \App\Services\TicketOrders::RESERVATION_MINUTES }} de minute.</div>
                @endif

                <div class="pa-soft" style="font-size: .8rem">Comanda nu se poate anula din aplicație.@if (\App\Services\Terms::enabled()) Prin cumpărare ești de acord cu <a href="{{ route('app.terms') }}" target="_blank" rel="noopener" class="pa-link" data-buy-terms>Termenii și condițiile</a>.@endif</div>

                @if ($creditBalance !== null && $quote)
                    {{-- Plata cu credite (runda 50): apare doar dacă soldul acoperă totalul; cere confirmare înainte de debitare. --}}
                    <div x-show="cr" x-cloak class="pa-glass pa-pad pa-stack" style="gap: .6rem" data-credit-confirm>
                        <div style="font-weight: 800">Plătești cu credite</div>
                        <div style="font-size: .92rem">Se scad <b>{{ PartyPublic::lei($quote->total) }}</b> din portofel. Îți rămân <b>{{ PartyPublic::lei($creditBalance - $quote->total) }}</b>.</div>
                        <div class="pa-soft" style="font-size: .8rem">Comanda nu se poate anula, iar creditele nu se returnează automat.</div>
                        <div style="display: flex; gap: .5rem">
                            <button type="button" class="pa-btn pa-btn-ghost" style="flex: 1" @click="cr = false">Înapoi</button>
                            <button type="button" class="pa-btn" style="flex: 1" wire:click="buyWithCredits" wire:loading.attr="disabled" wire:target="buyWithCredits" data-pay-credits>Confirmă</button>
                        </div>
                    </div>
                @endif

                {{-- x-show pe un container separat: Alpine șterge `display` inline la afișare, deci `flex` + `gap` stau pe copil. --}}
                <div @if ($creditBalance !== null) x-show="! cr" @endif>
                    <div style="display: flex; flex-direction: column; gap: .75rem">
                        @if ($creditBalance !== null)
                            <button type="button" class="pa-btn pa-btn-ghost pa-btn-block" @click="cr = true" data-credit-option>Plătesc cu credite</button>
                        @endif
                        <div style="display: flex; gap: .5rem">
                            <button type="button" class="pa-btn pa-btn-ghost" style="flex: 1" @click="buy = false">Renunță</button>
                            <button type="button" class="pa-btn" style="flex: 1" wire:click="buy" wire:loading.attr="disabled" wire:target="buy" @if (! $quote) disabled @endif @if ($cardPay) data-pay-card @endif>{{ $cardPay ? 'Plătește cu cardul' : 'Confirmă' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endauth
    @endif
</div>
