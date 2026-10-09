{{-- DXA: adaugat (runda 51). Pagina de plată a unei încărcări de credite (Stripe sau, fără el, loc rezervat). Variabile: $topup (CreditTopup), $online (bool: Stripe activ). --}}
@php use App\Support\PartyPublic; @endphp
<div>
    <section class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Plata cu cardul</div>
        <div class="pa-glass pa-pad pa-stack" style="gap: .6rem" data-topup-summary>
            <div class="pa-between" style="font-size: .95rem"><span>Plătești</span><b>{{ PartyPublic::lei($topup->toPay()) }}</b></div>
            @if ((float) $topup->bonus > 0)
                <div class="pa-between" style="font-size: .95rem"><span>Bonus</span><b>+{{ PartyPublic::lei((float) $topup->bonus) }}</b></div>
            @endif
            <div class="pa-between pa-line" style="padding-top: .6rem"><b>Primești</b><b class="pa-price" style="font-size: 1.5rem">{{ PartyPublic::lei($topup->credited()) }}</b></div>
        </div>
    </section>

    <section class="pa-section">
        @if ($topup->isPending() && $online && $returning)
            <div class="pa-glass pa-pad pa-stack" style="gap: .5rem; text-align: center" wire:poll.2s="check" data-topup-confirming>
                <div style="font-weight: 800">Se confirmă plata…</div>
                <div class="pa-soft" style="font-size: .9rem">Creditele apar în câteva clipe. Nu închide pagina.</div>
            </div>
        @elseif ($topup->isPending() && $online)
            @if ($error !== '')
                <div class="pa-glass pa-pad" style="text-align: center; font-weight: 700; margin-bottom: .75rem" data-topup-error>{{ $error }}</div>
            @endif
            <div class="pa-soft" style="font-size: .88rem; text-align: center; margin-bottom: .75rem" data-topup-secure>Plata se face în siguranță pe pagina Stripe. Nu păstrăm datele cardului.</div>
        @elseif ($topup->isPending())
            <div class="pa-glass pa-pad pa-stack" style="gap: .5rem; text-align: center" data-topup-soon>
                <div style="font-weight: 800">Plata cu cardul urmează în curând</div>
                <div class="pa-soft" style="font-size: .9rem">Încărcarea ta e pregătită, dar nu poți plăti încă din aplicație și nu s-a retras nimic. Până atunci poți încărca credite la Recepție, arătând codul tău QR.</div>
            </div>
        @else
            <div class="pa-glass pa-pad" style="text-align: center; font-weight: 700" data-topup-closed>
                {{ ['expired' => 'Încărcarea a expirat.', 'cancelled' => 'Încărcarea a fost anulată.', 'failed' => 'Plata nu a reușit.'][$topup->status] ?? 'Încărcarea nu mai este activă.' }} Nu s-a retras nimic.
            </div>
        @endif
        <div style="display: flex; gap: .5rem; margin-top: .75rem">
            @if ($topup->isPending() && ! $returning)
                <button type="button" class="pa-btn pa-btn-ghost" style="flex: 1" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel" data-topup-cancel>Renunță</button>
            @endif
            @if ($topup->isPending() && $online && ! $returning)
                <button type="button" class="pa-btn" style="flex: 1" wire:click="pay" wire:loading.attr="disabled" wire:target="pay" data-topup-pay>Plătește cu cardul</button>
            @else
                <a href="{{ route('app.wallet') }}" wire:navigate class="pa-btn" style="flex: 1; text-align: center">Înapoi la portofel</a>
            @endif
        </div>
    </section>
</div>
