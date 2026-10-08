{{-- DXA: runda 60. Popup (nu banner): se închide doar din X, nu la click pe fundal; reapare la următoarea intrare în aplicație (închiderea ține cât ține sesiunea browserului). --}}
<div>
    @if ($show && ! request()->routeIs('app.terms'))
        <div x-data="{
                open: true,
                init() { try { this.open = sessionStorage.getItem('dxa_terms_popup_closed') !== '1'; } catch (e) { this.open = true; } },
                close() { this.open = false; try { sessionStorage.setItem('dxa_terms_popup_closed', '1'); } catch (e) {} },
            }" x-show="open" x-cloak class="pa-modal-bg" role="dialog" aria-modal="true" aria-label="Termeni și condiții" data-terms-banner>
            <div class="pa-modal pa-stack" style="gap: .9rem">
                <div class="pa-between">
                    <h2 class="pa-h2" style="margin: 0">Termeni și condiții</h2>
                    <button type="button" class="pa-link" @click="close()" aria-label="Închide" data-terms-close>✕</button>
                </div>
                <div class="pa-soft" style="font-size: .9rem">Te rugăm să citești și să accepți <a href="{{ route('app.terms') }}" wire:navigate class="pa-link">Termenii și condițiile</a>.</div>
                <button type="button" class="pa-btn pa-btn-block" wire:click="accept" wire:loading.attr="disabled" wire:target="accept" data-terms-accept>Am citit și accept</button>
            </div>
        </div>
    @endif
</div>
