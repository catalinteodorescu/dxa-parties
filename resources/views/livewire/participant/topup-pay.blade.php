{{-- DXA: adaugat (runda 51). Pagina de plată a unei încărcări de credite (loc rezervat până la Stripe). Variabilă: $topup (CreditTopup). --}}
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
        @if ($topup->isPending())
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
            @if ($topup->isPending())
                <button type="button" class="pa-btn pa-btn-ghost" style="flex: 1" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel" data-topup-cancel>Renunță</button>
            @endif
            <a href="{{ route('app.wallet') }}" wire:navigate class="pa-btn" style="flex: 1; text-align: center">Înapoi la portofel</a>
        </div>
    </section>
</div>
