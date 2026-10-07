{{-- DXA: adaugat (runda 51b). Pagina „Încarcă credite”: suma, bonusul, Renunță (stânga) / Confirmă (dreapta). Variabile: $presets, $tiers, $quote, $quoteError, $min, $max. --}}
@php
    use App\Support\PartyPublic;
    $pct = fn ($n) => rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',').'%';
@endphp
<div>
    <section class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Portofel</div>
        <h1 class="pa-h1">Încarcă credite</h1>
        <div class="pa-soft" style="font-size: .9rem">1 credit = 1 leu. Între {{ PartyPublic::lei($min) }} și {{ PartyPublic::lei($max) }}.</div>
    </section>

    <section class="pa-section">
        <div class="pa-glass pa-pad pa-stack" style="gap: 1rem">
            @if ($presets)
                <div style="display: flex; flex-wrap: wrap; gap: .5rem">
                    @foreach ($presets as $n)
                        <button type="button" wire:key="tp-{{ $n }}" wire:click="setAmount({{ $n }})" class="pa-btn pa-btn-ghost pa-btn-sm {{ (string) $n === trim($amount) ? 'on' : '' }}" style="flex: 1; min-width: 4.5rem" data-topup-preset="{{ $n }}">{{ $n }} lei</button>
                    @endforeach
                </div>
            @endif
            <div>
                <label for="pa-topup" class="pa-label">Altă sumă (lei)</label>
                <input type="text" id="pa-topup" inputmode="decimal" autocomplete="off" wire:model.live.debounce.300ms="amount" wire:keydown.enter.prevent="confirm" placeholder="ex. 75" class="pa-input">
            </div>

            @if ($tiers)
                <div class="pa-soft" style="font-size: .82rem" data-bonus-tiers>
                    Bonus la încărcare: {{ collect($tiers)->map(fn ($t) => 'de la '.PartyPublic::lei($t['min']).' → +'.$pct($t['percent']))->implode(' · ') }}
                </div>
            @endif

            @if ($quote)
                <div class="pa-stack" style="gap: .4rem" data-topup-quote>
                    <div class="pa-between" style="font-size: .92rem"><span>Plătești</span><span>{{ PartyPublic::lei($quote->to_pay) }}</span></div>
                    @if ($quote->bonus > 0)
                        <div class="pa-between" style="font-size: .92rem"><span>Bonus ({{ $pct($quote->percent) }})</span><span>+{{ PartyPublic::lei($quote->bonus) }}</span></div>
                    @endif
                    <div class="pa-between pa-line" style="padding-top: .5rem"><b>Primești</b><b class="pa-price" style="font-size: 1.3rem">{{ PartyPublic::lei($quote->credited) }}</b></div>
                </div>
                @if ($quote->next)
                    <div class="pa-soft" style="font-size: .82rem" data-next-tier>Încă {{ PartyPublic::lei($quote->next['missing']) }} și primești +{{ $pct($quote->next['percent']) }} bonus.</div>
                @endif
            @elseif ($quoteError)
                <p class="pa-err">{{ $quoteError }}</p>
            @endif

            @if ($error)
                <div class="pa-alert pa-alert-err" role="alert">{{ $error }}</div>
            @endif

            <div style="display: flex; gap: .5rem">
                <a href="{{ route('app.wallet') }}" wire:navigate class="pa-btn pa-btn-ghost" style="flex: 1; text-align: center" data-topup-cancel>Renunță</a>
                <button type="button" class="pa-btn" style="flex: 1" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" @if (! $quote) disabled @endif data-topup-confirm>Confirmă</button>
            </div>
        </div>
    </section>
</div>
