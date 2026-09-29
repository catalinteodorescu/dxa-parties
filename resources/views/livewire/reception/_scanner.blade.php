{{--
    DXA: adaugat (Scanare QR). Buton „Scanează” + panou cu camera, în pickerul de participanți din PWA.
    Citește QR-ul personal (Participant::qrPayload()), cheamă scanParticipant() și închide panoul dacă participantul a fost ales.
    Biblioteca de scanare se încarcă la prima deschidere (cere internet + HTTPS pentru cameră). Nu înregistrează nimic.
--}}
<div x-data="{
        open: false, ready: false, msg: '', busy: false, scanner: null, lib: 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js',
        async load() {
            if (window.Html5Qrcode) return;
            await new Promise((res, rej) => { const s = document.createElement('script'); s.src = this.lib; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
        },
        async start() {
            this.open = true; this.ready = false; this.msg = ''; this.busy = false;
            try {
                await this.load();
                await this.$nextTick();
                this.scanner = new Html5Qrcode($refs.reader.id);
                await this.scanner.start({ facingMode: 'environment' }, { fps: 10, aspectRatio: 1, qrbox: (w, h) => { const m = Math.floor(Math.min(w, h) * 0.7); return { width: m, height: m }; } }, (text) => this.onScan(text), () => {});
                this.ready = true;
            } catch (e) {
                const why = String((e && (e.name ? e.name + ': ' : '') + (e.message || e)) || '').slice(0, 140);
                this.msg = window.Html5Qrcode
                    ? 'Nu pot porni camera. Permite accesul la cameră în setările telefonului și ale browserului. (' + why + ') [' + location.protocol + ' secure=' + window.isSecureContext + ' mediaDevices=' + !!navigator.mediaDevices + ']'
                    : 'Nu pot încărca scannerul. Verifică conexiunea la internet.';
            }
        },
        async onScan(text) {
            if (this.busy) return;
            this.busy = true;
            const r = await $wire.scanParticipant(text);
            if (r.ok) { this.stop(); return; }
            this.msg = r.message;
            setTimeout(() => { this.busy = false; }, 2000);
        },
        async stop() {
            this.open = false;
            try { if (this.scanner) { await this.scanner.stop(); this.scanner.clear(); } } catch (e) {}
            this.scanner = null; this.ready = false;
        }
    }" class="shrink-0">
    <x-btn variant="neutral" size="icon" outline tooltip="Scanează cod QR" x-on:click="start()" data-scan-button>
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/></svg>
    </x-btn>

    <div x-show="open" x-cloak style="display: none" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/60" x-on:click="stop()"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-surface border border-border shadow-lg p-5">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-ink">Scanează codul QR</h3>
                <button type="button" x-on:click="stop()" aria-label="Închide" class="h-7 w-7 inline-flex items-center justify-center rounded-lg text-ink-soft hover:bg-bg">×</button>
            </div>
            <div wire:ignore class="relative mt-3 aspect-square w-full overflow-hidden rounded-xl bg-bg">
                <div x-show="! ready && ! msg" class="absolute inset-0 flex items-center justify-center text-sm text-ink-soft">Se pornește camera…</div>
                <div id="qr-reader-{{ $this->getId() }}" x-ref="reader" class="absolute inset-0 h-full w-full [&>div]:!h-full [&>div]:!min-h-0 [&_video]:!h-full [&_video]:!w-full [&_video]:object-cover"></div>
            </div>
            <p x-show="msg" x-text="msg" class="mt-3 text-sm font-medium text-danger"></p>
            <div class="mt-4 flex justify-end">
                <button type="button" x-on:click="stop()" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Închide</button>
            </div>
        </div>
    </div>
</div>
