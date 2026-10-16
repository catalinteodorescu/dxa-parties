{{-- DXA: adaugat (runda 77). Popup-ul „Activează notificările” din Recepție și Bar. Apare după login (flag în sesiune, pus de login) și revine la
     fiecare login până telefonul e activat; „Mai târziu” îl ascunde doar pentru sesiunea curentă. Tipurile de notificări le stabilește adminul
     (Setări › Aplicație recepție / bar), nu cine lucrează. Prop: app = reception | bar. --}}
@props(['app'])
@if (session('staff_push_prompt.'.$app) && \App\Services\Push\StaffNotifier::enabled())
    @php($r = $app === 'bar' ? 'bar' : 'receptie')
    <div wire:ignore x-data="{ show: false, st: '' }" x-cloak
         x-on:push-state="st = $event.detail; show = (st !== 'on'); if (st === 'on') { fetch($refs.root.dataset.prompt, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': $refs.root.dataset.csrf, 'Accept': 'application/json' } }).catch(() => {}); }"
         x-init="$nextTick(() => dxaPushInit($refs.root))">
        <div x-show="show" class="fixed inset-0 z-[70] flex items-center justify-center p-4 text-ink" data-staff-push-prompt>
            <div class="absolute inset-0 bg-ink/40"></div>
            <div x-ref="root" class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6 space-y-3" data-push-card
                 data-key="{{ \App\Services\Push\Vapid::publicKey() }}" data-csrf="{{ csrf_token() }}"
                 data-subscribe="{{ route($r.'.push.subscribe', [], false) }}" data-unsubscribe="{{ route($r.'.push.unsubscribe', [], false) }}" data-test="{{ route($r.'.push.test', [], false) }}"
                 data-prompt="{{ route($r.'.push.prompt', [], false) }}">
                <h3 class="text-base font-semibold text-ink">Activează notificările</h3>
                <p class="text-sm text-ink-soft">Primești pe acest telefon alertele pentru munca de la {{ $app === 'bar' ? 'bar' : 'recepție' }}: deschiderea, raportările și ce se întâmplă în timpul petrecerii, chiar și cu aplicația închisă.</p>
                <p class="text-sm text-ink-soft" data-msg></p>
                <div class="flex items-center justify-between gap-3 pt-1">
                    <button type="button" class="text-sm font-medium text-ink-soft hover:text-ink px-1 py-2"
                            @click="show = false; fetch($refs.root.dataset.prompt, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': $refs.root.dataset.csrf, 'Accept': 'application/json' } }).catch(() => {})" data-push-later>Mai târziu</button>
                    <button type="button" data-act="enable" data-show="off" style="display: none"
                            class="rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2 transition-colors">Activează</button>
                </div>
            </div>
        </div>
    </div>
@endif
