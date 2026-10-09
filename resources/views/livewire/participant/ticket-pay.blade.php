{{-- DXA: adaugat (runda 65). Plata cu cardul a unei comenzi de bilete. Variabile: $order (Order, cu party), $online (bool: Stripe activ), $returning, $error. --}}
@php use App\Support\PartyPublic; @endphp
<div>
    <section class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Plata cu cardul</div>
        <div class="pa-glass pa-pad pa-stack" style="gap: .6rem" data-order-summary>
            <div class="pa-between" style="font-size: .95rem"><span>{{ $order->party?->name }}</span><b>{{ $order->tickets_count }} {{ $order->tickets_count === 1 ? 'bilet' : 'bilete' }}</b></div>
            <div class="pa-between pa-line" style="padding-top: .6rem"><b>Total</b><b class="pa-price" style="font-size: 1.5rem">{{ PartyPublic::lei((float) $order->total) }}</b></div>
        </div>
    </section>

    <section class="pa-section">
        @if ($order->isAwaitingCard() && $online && $returning)
            <div class="pa-glass pa-pad pa-stack" style="gap: .5rem; text-align: center" wire:poll.2s="check" data-order-confirming>
                <div style="font-weight: 800">Se confirmă plata…</div>
                <div class="pa-soft" style="font-size: .9rem">Biletele apar în câteva clipe. Nu închide pagina.</div>
            </div>
        @elseif ($order->isAwaitingCard() && $online)
            @if ($error !== '')
                <div class="pa-glass pa-pad" style="text-align: center; font-weight: 700; margin-bottom: .75rem" data-order-error>{{ $error }}</div>
            @endif
            <div class="pa-soft" style="font-size: .88rem; text-align: center; margin-bottom: .75rem" data-order-reserved>Biletele sunt rezervate până la {{ $order->payment_expires_at?->timezone(config('app.timezone'))->format('H:i') }}. Plata se face în siguranță pe pagina Stripe.</div>
        @elseif ($order->isAwaitingCard())
            <div class="pa-glass pa-pad" style="text-align: center; font-weight: 700" data-order-soon>Plata cu cardul nu este disponibilă acum.</div>
        @else
            <div class="pa-glass pa-pad" style="text-align: center; font-weight: 700" data-order-closed>
                @if ($order->payment_status === \App\Models\Order::PAY_CARD_CREDITED)
                    Rezervarea expirase, iar locurile s-au epuizat între timp. Suma plătită a intrat în portofelul tău ca credite.
                @else
                    {{ $order->payment_status === \App\Models\Order::PAY_CARD_EXPIRED ? 'Rezervarea a expirat.' : 'Plata nu a reușit.' }} Nu s-a retras nimic.
                @endif
            </div>
        @endif
        <div style="display: flex; gap: .5rem; margin-top: .75rem">
            @if ($order->isAwaitingCard() && ! $returning)
                <button type="button" class="pa-btn pa-btn-ghost" style="flex: 1" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel" data-order-cancel>Renunță</button>
            @endif
            @if ($order->isAwaitingCard() && $online && ! $returning)
                <button type="button" class="pa-btn" style="flex: 1" wire:click="pay" wire:loading.attr="disabled" wire:target="pay" data-order-pay>Plătește cu cardul</button>
            @else
                <a href="{{ route('app.party', $order->party_id) }}" wire:navigate class="pa-btn" style="flex: 1; text-align: center">Înapoi la petrecere</a>
            @endif
        </div>
    </section>
</div>
