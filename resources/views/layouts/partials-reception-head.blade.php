{{-- DXA: adaugat (PWA Recepție). Head comun al layout-urilor aplicației de recepție (manifest, teme, service worker). --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="{{ \App\Support\ReceptionApp::primary() }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit(\App\Support\ReceptionApp::name(), 12, '') }}">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>{{ \App\Support\ReceptionApp::name() }} — {{ \App\Support\Branding::name() }}</title>

<link rel="manifest" href="{{ route('receptie.manifest') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}" sizes="32x32">
<link rel="apple-touch-icon" href="{{ \App\Support\ReceptionApp::iconUrl(180) }}">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Tema aplicației de recepție (independentă de tema panoului admin) --}}
<style id="dxa-theme">:root{ {{ \App\Support\ReceptionApp::inlineStyle() }} }</style>
@livewireStyles
