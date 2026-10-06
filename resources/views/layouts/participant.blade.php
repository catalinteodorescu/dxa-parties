<!DOCTYPE html>
<html lang="ro">
<head>
    @include('layouts.partials-participant-head')
</head>
<body class="pa-body">
    {{-- DXA: adaugat (Aplicația participanților). Shell mobile-first: antet cu logo + acces cont, conținut într-o coloană, navigare jos. --}}
    @php
        $me = auth('participant')->user();
        $tabs = [
            ['app.home', 'Acasă', ['app.home'], '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>'],
            ['app.parties', 'Petreceri', ['app.parties', 'app.party'], '<path d="M5.8 11.3 2 22l10.7-3.8"/><path d="M4 3h.01M22 8h.01M15 2h.01M22 20h.01"/><path d="m22 2-2.2.7a2.9 2.9 0 0 0-1.9 3.2L18 7"/><path d="M11 13c1.9 1.9 3.9 3.2 6 2.9M8 8.5c-.3 2.4 1 4.5 2.9 6.4"/>'],
            ['qr', 'Cod QR', [], '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>'],
            ['app.tickets', 'Bilete', ['app.tickets', 'app.tickets.all'], '<path d="M3 9a2 2 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a2 2 0 0 1 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z"/><path d="M13 5v2M13 11v2M13 17v2"/>'],
            ['app.wallet', 'Portofel', ['app.wallet', 'app.wallet.all'], '<path d="M19 7V5a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>'],
        ];
    @endphp
    <div class="pa-shell">
        <header class="pa-top">
            <a href="{{ route('app.home') }}" wire:navigate class="pa-a" aria-label="{{ \App\Support\ParticipantApp::name() }}">
                <img src="{{ \App\Support\ParticipantApp::logoUrl() }}" alt="{{ \App\Support\ParticipantApp::name() }}">
            </a>
            @if ($me)
                <a href="{{ route('app.account') }}" wire:navigate class="pa-a" aria-label="Contul meu ({{ $me->name }})">
                    @include('livewire.participant._avatar', ['who' => $me, 'size' => 'sm'])
                </a>
            @else
                <a href="{{ route('app.login') }}" wire:navigate class="pa-btn pa-btn-sm">Intră în cont</a>
            @endif
        </header>

        <main>
            {{ $slot }}
        </main>

        @include('layouts.partials-participant-footer')
    </div>

    <nav class="pa-nav" aria-label="Navigare principală">
        @foreach ($tabs as [$route, $label, $group, $icon])
            @if ($route === 'qr')
                <button type="button" x-data @click="$dispatch('open-qr')" aria-haspopup="dialog">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                    <span>{{ $label }}</span>
                </button>
            @else
                <a href="{{ route($route) }}" wire:navigate class="{{ request()->routeIs(...$group) ? 'on' : '' }}" @if (request()->routeIs(...$group)) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                    <span>{{ $label }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    {{-- Popup mic cu codul personal (din meniu). Biblioteca QR e servită local; codul se desenează la prima deschidere. --}}
    <div x-data="{
            open: false, drawn: false, ok: true,
            async draw() {
                if (this.drawn || ! this.$refs.qr) return;
                try {
                    if (! window.qrcode) await new Promise((res, rej) => { const s = document.createElement('script'); s.src = @js(asset('vendor/qrcode-generator.js')); s.onload = res; s.onerror = rej; document.head.appendChild(s); });
                    const q = qrcode(0, 'M'); q.addData(this.$refs.qr.dataset.qr); q.make();
                    this.$refs.qr.innerHTML = q.createSvgTag({ cellSize: 6, margin: 0, scalable: true });
                    this.drawn = true;
                } catch (e) { this.ok = false; }
            },
        }"
         @open-qr.window="open = true; $nextTick(() => draw())" @keydown.escape.window="open = false">
        <div x-show="open" x-cloak class="pa-modal-bg" @click.self="open = false" role="dialog" aria-modal="true" aria-label="Codul tău personal">
            <div class="pa-modal pa-stack" style="align-items: center; text-align: center; gap: .9rem">
                @if ($me)
                    <div class="pa-between" style="width: 100%">
                        <h2 class="pa-h2" style="margin: 0">Codul tău personal</h2>
                        <button type="button" class="pa-link" @click="open = false" aria-label="Închide">✕</button>
                    </div>
                    <div wire:ignore>
                        <div x-ref="qr" data-qr="{{ $me->qrPayload() }}" style="width: 220px; height: 220px; background: #fff; padding: 12px; border-radius: 1rem"></div>
                    </div>
                    <p x-show="! ok" x-cloak class="pa-err">Nu am putut desena codul. Reîncarcă pagina.</p>
                    <div class="pa-soft" style="font-size: .85rem">{{ $me->name }} · arată-l la intrare sau la bar.</div>
                @else
                    <h2 class="pa-h2" style="margin: 0">Codul tău personal</h2>
                    <div class="pa-soft" style="font-size: .9rem">Intră în cont ca să-ți vezi codul QR.</div>
                    <a href="{{ route('app.login') }}" wire:navigate class="pa-btn pa-btn-block" @click="open = false">Intră în cont</a>
                    <button type="button" class="pa-link" @click="open = false">Închide</button>
                @endif
            </div>
        </div>
    </div>

    @include('layouts.partials-participant-toast')

    @include('layouts.partials-participant-track')
    @livewireScripts
    @include('layouts.partials-participant-sw')
</body>
</html>
