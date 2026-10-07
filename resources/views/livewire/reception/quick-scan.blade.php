{{--
    DXA: adaugat (runda 47 - Scanner Recepție). Cartela „Scanează” de pe ecranul principal + panoul cu camera.
    Ecran plin, camera rămâne deschisă între scanări (coadă la intrare). Rezultatul colorează tot ecranul (verde = intrat, roșu = refuzat),
    sub cameră; scanarea nu se oprește cât e afișat, iar cercul alb îl șterge. Fără nume pe acest ecran. QR personal cu mai multe bilete = alegere (selecția stă în ecran, nu pe server).
    Aceeași bibliotecă și același mod de pornire ca în reception/_scanner.blade.php (local, HTTPS pentru cameră). Mesajele vin din ReceptionScan.
--}}
<div x-data="{
        open: false, ready: false, camErr: '', busy: false, scanner: null, fb: null, choice: null, sel: [], count: 0, last: '', lastAt: 0,
        lib: '{{ asset('vendor/html5-qrcode.min.js') }}',
        async load() {
            if (window.Html5Qrcode) return;
            await new Promise((res, rej) => { const s = document.createElement('script'); s.src = this.lib; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
        },
        async start() {
            this.open = true; this.ready = false; this.camErr = ''; this.busy = false; this.fb = null; this.choice = null; this.sel = []; this.count = 0; this.last = '';
            try {
                await this.load();
                await this.$nextTick();
                this.scanner = new Html5Qrcode($refs.reader.id);
                await this.scanner.start({ facingMode: 'environment' }, { fps: 10, aspectRatio: 1, qrbox: (w, h) => { const m = Math.floor(Math.min(w, h) * 0.7); return { width: m, height: m }; } }, (text) => this.onScan(text), () => {});
                this.ready = true;
            } catch (e) {
                this.camErr = window.Html5Qrcode
                    ? 'Nu pot porni camera. Permite accesul la cameră în setările telefonului și ale browserului.'
                    : 'Nu pot încărca scannerul. Reîncarcă pagina.';
            }
        },
        async onScan(text) {
            // busy = cerere în curs sau alegere de bilete deschisă; un rezultat (și o eroare) nu blochează scanarea următoare.
            if (this.busy) return;
            if (text === this.last && Date.now() - this.lastAt < 6000) return;
            this.busy = true; this.last = text; this.lastAt = Date.now();
            this.handle(await $wire.scan(text));
        },
        async enterSelected() {
            if (! this.choice || this.sel.length === 0) return;
            const participant = this.choice.participant;
            this.last = '';
            this.handle(await $wire.enterSelected(this.sel.map(Number), participant));
        },
        cancelChoice() {
            this.choice = null; this.sel = []; this.last = ''; this.busy = false;
        },
        dismiss() {
            this.fb = null;
        },
        async handle(r) {
            if (r.status === 'open') {
                await this.stop();
                Livewire.navigate(r.url);
                return;
            }
            if (r.status === 'choose') {
                this.fb = null;
                this.choice = { title: r.message, participant: r.participant, tickets: r.tickets };
                this.sel = r.tickets.map((t) => t.id);
                return;
            }
            this.choice = null; this.sel = [];
            const ok = r.status === 'entered';
            this.fb = { ok: ok, msg: r.message };
            if (ok) { this.count++; if (navigator.vibrate) navigator.vibrate(60); }
            this.busy = false;
        },
        async stop() {
            this.open = false; this.fb = null; this.choice = null;
            try { if (this.scanner) { await this.scanner.stop(); this.scanner.clear(); } } catch (e) {}
            this.scanner = null; this.ready = false;
        }
    }" x-on:livewire:navigating.window="stop()">

    {{-- Cartela de pe ecranul principal: contur în culoarea temei (altfel s-ar confunda cu petrecerea selectată, care e plină) --}}
    <button type="button" x-on:click="start()" data-quick-scan class="w-full flex items-center gap-4 rounded-2xl border-primary bg-surface px-4 py-4 text-left hover:bg-bg" style="border-width: 2px; border-style: solid">
        <span class="w-11 h-11 rounded-xl bg-primary text-white flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/></svg>
        </span>
        <span class="flex-1"><span class="block text-base font-semibold text-ink">Scanează</span><span class="block text-xs text-ink-soft">Bilet sau QR personal · intrare directă</span></span>
        <span class="text-primary">›</span>
    </button>

    {{-- Ecran plin; fundalul devine verde / roșu cât timp e afișat un rezultat --}}
    <div x-show="open" x-cloak style="display: none" class="fixed inset-0 z-[70] flex flex-col bg-surface"
         :style="fb ? 'background:' + (fb.ok ? '#16a34a' : '#dc2626') : ''">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <h3 class="text-base font-semibold" :class="fb ? 'text-white' : 'text-ink'">Scanează</h3>
            <div class="flex items-center gap-3">
                <span x-show="count > 0" x-cloak style="display: none" class="text-sm font-medium" :class="fb ? 'text-white/90' : 'text-ink-soft'" x-text="count + (count === 1 ? ' intrare' : ' intrări')"></span>
                <button type="button" x-on:click="stop()" aria-label="Închide" class="inline-flex items-center justify-center rounded-full border-2 transition-colors" style="width: 40px; height: 40px"
                        :class="fb ? 'border-white text-white hover:bg-white/20' : 'border-ink-soft text-ink hover:bg-bg'">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-4 pb-6">
            <div class="mx-auto w-full max-w-sm space-y-4">
                <div wire:ignore class="relative aspect-square w-full overflow-hidden rounded-xl bg-bg">
                    <div x-show="! ready && ! camErr" class="absolute inset-0 flex items-center justify-center text-sm text-ink-soft">Se pornește camera…</div>
                    <div id="qr-quick-{{ $this->getId() }}" x-ref="reader" class="absolute inset-0 h-full w-full [&>div]:!h-full [&>div]:!min-h-0 [&_video]:!h-full [&_video]:!w-full [&_video]:object-cover"></div>
                </div>

                <p x-show="camErr" x-cloak style="display: none" x-text="camErr" class="text-sm font-medium text-danger"></p>

                {{-- Rezultatul scanării, sub cameră: cerc alb mare cu ✓ verde / × roșu (îl atingi ca să ștergi mesajul), mesajul centrat dedesubt --}}
                <div x-show="fb" x-cloak style="display: none" data-scan-feedback class="flex flex-col items-center gap-3 text-center">
                    <button type="button" x-on:click="dismiss()" aria-label="Șterge mesajul" data-scan-dismiss
                            class="inline-flex items-center justify-center rounded-full bg-white shadow-sm" style="width: 72px; height: 72px">
                        <svg x-show="fb && fb.ok" class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        <svg x-show="fb && ! fb.ok" class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                    <p class="text-lg font-semibold leading-snug text-white" x-text="fb ? fb.msg : ''"></p>
                </div>

                {{-- Mai multe bilete pe același QR: alegi cele care intră acum --}}
                <div x-show="choice" x-cloak style="display: none" class="rounded-xl border border-border bg-surface p-4" data-scan-choice>
                    <p class="text-sm font-medium text-ink" x-text="choice ? choice.title : ''"></p>
                    <div class="mt-3 space-y-2.5">
                        <template x-for="t in (choice ? choice.tickets : [])" :key="t.id">
                            <div class="flex items-center gap-2">
                                <x-checkbox x-model="sel" x-bind:value="t.id" class="flex-1 min-w-0"><span class="block truncate" x-text="t.label"></span></x-checkbox>
                                <span x-show="t.due > 0" class="shrink-0 text-xs text-ink-soft" x-text="Number(t.due).toLocaleString('ro-RO', { minimumFractionDigits: 2 }) + ' lei'"></span>
                            </div>
                        </template>
                    </div>
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" x-on:click="cancelChoice()" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Renunță</button>
                        <x-btn type="button" x-on:click="enterSelected()" x-bind:disabled="sel.length === 0"><span x-text="'Intră (' + sel.length + ')'"></span></x-btn>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
