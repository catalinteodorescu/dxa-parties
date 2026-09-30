{{-- DXA: adaugat (Aplicația participanților). Head comun al layout-urilor aplicației (manifest la rădăcină, temă întunecată, service worker). --}}
@php $pwa = \App\Support\ParticipantApp::class; @endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#120810">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit($pwa::name(), 12, '') }}">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>{{ $title ?? $pwa::name() }} — {{ \App\Support\Branding::name() }}</title>

<link rel="manifest" href="{{ route('app.manifest') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}" sizes="32x32">
<link rel="apple-touch-icon" href="{{ $pwa::iconUrl(180) }}">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@include('layouts.partials-participant-styles')
@livewireStyles
