{{-- DXA: adaugat (runda 52). Confirmarea ștergerii contului. Variabilă: $summary (vezi AccountDeletion::summary). --}}
@php use App\Support\PartyPublic; @endphp
<div>
    <section class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Contul meu</div>
        <h1 class="pa-h1">Șterge contul</h1>
        <p class="pa-soft" style="margin-top: .5rem">Ștergerea nu se poate anula. Numele, telefonul, parola și poza tale dispar; intrările și consumațiile rămân în statistici, dar anonim. Cu același telefon îți poți face oricând un cont nou.</p>
    </section>

    <section class="pa-section">
        <div class="pa-glass pa-pad pa-stack" style="gap: .6rem" data-delete-summary>
            <div class="pa-label" style="margin: 0">Ce pierzi</div>
            <div class="pa-between" style="font-size: .95rem"><span>Credite în portofel</span><b data-delete-balance>{{ PartyPublic::lei($summary['balance']) }}</b></div>
            @if ($summary['balance'] > 0)
                <div style="font-size: .85rem; color: #f87171" data-delete-credit-warning>Creditele rămase se pierd și nu se rambursează. Folosește-le înainte să ștergi contul.</div>
            @endif
            <div class="pa-between" style="font-size: .95rem"><span>Bilete valabile</span><b data-delete-tickets>{{ $summary['tickets'] }}</b></div>
            @if ($summary['tickets'] > 0)
                <div class="pa-soft" style="font-size: .85rem" data-delete-ticket-warning>Biletele rămân valabile. Vei primi pe SMS, la numărul tău, un singur link către o pagină cu toate biletele tale. Linkul este biletele: oricine îl are îl poate folosi, așa că nu-l da mai departe. Numărul tău nu rămâne legat de bilete, iar un cont nou nu le va mai prelua.</div>
            @endif
            @if ($summary['loyalty_stamps'] !== null)
                <div class="pa-between" style="font-size: .95rem"><span>Card de fidelitate</span><b data-delete-loyalty>{{ $summary['loyalty_stamps'] }} / {{ $summary['loyalty_required'] }} ștampile</b></div>
            @endif
        </div>
    </section>

    <section class="pa-section">
        <form wire:submit="delete" class="pa-glass pa-pad pa-stack" style="gap: .85rem" novalidate>
            @if ($error)
                <div class="pa-alert pa-alert-err" role="alert" data-delete-error>{{ $error }}</div>
            @endif
            <div>
                <label for="pa-del-pass" class="pa-label">Parola ta, ca să confirmi</label>
                <input type="password" id="pa-del-pass" wire:model="password" autocomplete="current-password" class="pa-input">
            </div>
            <label class="pa-check">
                <input type="checkbox" wire:model.live="understood" data-delete-understood>
                <span class="pa-check-box" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                <span>Am înțeles că ștergerea contului nu se poate anula.</span>
            </label>
            <div style="display: flex; gap: .5rem">
                <a href="{{ route('app.account') }}" wire:navigate class="pa-btn pa-btn-ghost" style="flex: 1; text-align: center" data-delete-cancel>Renunță</a>
                <button type="submit" class="pa-btn" style="flex: 1; background: #dc2626; border-color: #dc2626; color: #fff" @disabled(! $understood) wire:loading.attr="disabled" wire:target="delete" data-delete-confirm>Șterge contul</button>
            </div>
        </form>
    </section>
</div>
