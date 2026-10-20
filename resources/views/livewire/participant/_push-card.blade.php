{{-- DXA: adaugat (runda 70), simplificat în runda 91. Panoul „Activează notificările” (în Cont și în pagina Notificări): doar activare; după activare dispare (data-hide-on). Dezactivarea se face din setările telefonului. Fără wire:ignore starea s-ar pierde la fiecare reîmprospătare Livewire. --}}
@if (\App\Services\Push\Notifier::enabled())
    <div wire:ignore class="pa-glass pa-pad pa-stack" style="gap: .6rem; display: none" data-push-card data-hide-on="on"
         x-init="appPushInit($el)"
         data-key="{{ \App\Services\Push\Vapid::publicKey() }}" data-csrf="{{ csrf_token() }}"
         data-subscribe="{{ route('app.push.subscribe', [], false) }}" data-unsubscribe="{{ route('app.push.unsubscribe', [], false) }}" data-test="{{ route('app.push.test', [], false) }}">
        <div class="pa-label" style="margin: 0">Activează notificările</div>
        <div class="pa-soft" style="font-size: .85rem">Află de petreceri noi, de prețuri care cresc, de biletele tale și de anunțuri, direct pe telefon.</div>
        <div class="pa-soft" style="font-size: .85rem" data-msg></div>
        <button type="button" class="pa-btn pa-btn-block" data-act="enable" data-show="off" style="display: none">Activează notificările</button>
    </div>
@endif
