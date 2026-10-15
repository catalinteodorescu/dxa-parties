{{-- DXA: adaugat (PWA Recepție / Bar). Head comun al layout-urilor aplicațiilor PWA (manifest, teme, service worker). Variabila $pwa = clasa aplicației (ReceptionApp / BarApp), setată de layout. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
<meta name="theme-color" content="{{ $pwa::primary() }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit($pwa::name(), 12, '') }}">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>{{ $pwa::name() }} — {{ \App\Support\Branding::name() }}</title>

<link rel="manifest" href="{{ route($pwa::ROUTE_PREFIX.'.manifest') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}" sizes="32x32">
<link rel="apple-touch-icon" href="{{ $pwa::iconUrl(180) }}">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Tema aplicației de recepție (independentă de tema panoului admin) --}}
<style id="dxa-theme">:root{ {{ $pwa::inlineStyle() }} }</style>
{{-- DXA: adaugat (runda 71). Zoom blocat în Recepție / Bar: fără dublu-tap zoom, iar câmpurile de 16px nu mai declanșează zoom-ul iOS la focus. --}}
<style>
    html, body { touch-action: manipulation; }
    input:not([type="checkbox"]):not([type="radio"]):not([type="range"]), select, textarea { font-size: 16px !important; }
    textarea { resize: none; }
</style>
@livewireStyles
