{{--
    DXA: adaugat (Scanare QR). Buton „Scanează” + popup cu camera, în pickerul de participanți din PWA (Intrare / Tokeni / Credite).
    Citește QR-ul personal sau al biletului, cheamă scanParticipant() și, la succes, își arată rezultatul în verde și se închide singur.
    Rezultatul (verde = adăugat, roșu = refuzat) colorează tot popup-ul; sub cameră: cerc alb cu ✓ / × și mesajul centrat. Fără nume.
    Eroarea nu oprește scanarea; cercul alb o șterge. Biblioteca (public/vendor/html5-qrcode.min.js) se încarcă local (HTTPS cerut pentru cameră).
    Nu înregistrează nimic.
--}}
<div x-data="{
        open: false, ready: false, camErr: '', busy: false, scanner: null, fb: null, last: '', lastAt: 0, timer: null,
        lib: '{{ asset('vendor/html5-qrcode.min.js') }}',
        async load() {
            if (window.Html5Qrcode) return;
            await new Promise((res, rej) => { const s = document.createElement('script'); s.src = this.lib; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
        },
        async start() {
            this.open = true; this.ready = false; this.camErr = ''; this.busy = false; this.fb = null; this.last = '';
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
            if (this.busy) return;
            if (text === this.last && Date.now() - this.lastAt < 3000) return;
            this.busy = true; this.last = text; this.lastAt = Date.now();
            const r = await $wire.scanParticipant(text);
            this.busy = false;
            if (r.ok) {
                this.fb = { ok: true, msg: 'Adăugat' };
                if (navigator.vibrate) navigator.vibrate(60);
                this.timer = setTimeout(() => this.stop(), 1100);
                return;
            }
            this.fb = { ok: false, msg: r.message };
        },
        dismiss() {
            this.fb = null;
        },
        async stop() {
            clearTimeout(this.timer);
            this.open = false; this.fb = null;
            try { if (this.scanner) { await this.scanner.stop(); this.scanner.clear(); } } catch (e) {}
            this.scanner = null; this.ready = false;
        }
    }" class="shrink-0">
    <x-btn variant="neutral" size="icon" outline tooltip="Scanează cod QR" x-on:click="start()" data-scan-button>
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/></svg>
    </x-btn>

    <div x-show="open" x-cloak style="display: none" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/60" x-on:click="stop()"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-surface border border-border shadow-lg p-5 transition-colors"
             :style="fb ? 'background:' + (fb.ok ? '#16a34a' : '#dc2626') + '; border-color: transparent' : ''">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold" :class="fb ? 'text-white' : 'text-ink'">Scanează codul QR</h3>
                <button type="button" x-on:click="stop()" aria-label="Închide" class="inline-flex items-center justify-center rounded-full border-2 transition-colors" style="width: 40px; height: 40px"
                        :class="fb ? 'border-white text-white hover:bg-white/20' : 'border-ink-soft text-ink hover:bg-bg'">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div wire:ignore class="relative mt-3 aspect-square w-full overflow-hidden rounded-xl bg-bg">
                <div x-show="! ready && ! camErr" class="absolute inset-0 flex items-center justify-center text-sm text-ink-soft">Se pornește camera…</div>
                <div id="qr-reader-{{ $this->getId() }}" x-ref="reader" class="absolute inset-0 h-full w-full [&>div]:!h-full [&>div]:!min-h-0 [&_video]:!h-full [&_video]:!w-full [&_video]:object-cover"></div>
            </div>

            <p x-show="camErr" x-cloak style="display: none" x-text="camErr" class="mt-3 text-sm font-medium text-danger"></p>

            <div x-show="fb" x-cloak style="display: none" data-scan-feedback class="mt-4 flex flex-col items-center gap-3 text-center">
                <button type="button" x-on:click="dismiss()" aria-label="Șterge mesajul" data-scan-dismiss
                        class="inline-flex items-center justify-center rounded-full bg-white shadow-sm" style="width: 64px; height: 64px">
                    <svg x-show="fb && fb.ok" class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    <svg x-show="fb && ! fb.ok" class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
                <p class="text-base font-semibold leading-snug text-white" x-text="fb ? fb.msg : ''"></p>
            </div>
        </div>
    </div>
</div>
