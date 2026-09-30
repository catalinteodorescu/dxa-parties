<!DOCTYPE html>
<html lang="ro">
<head>
    @include('layouts.partials-participant-head')
</head>
<body class="pa-body">
    {{-- DXA: adaugat (Aplicația participanților). Layout pentru login / înregistrare / resetare: o coloană centrată, logo deasupra. --}}
    <div class="pa-guest">
        <a href="{{ route('app.home') }}" wire:navigate class="logo pa-a" aria-label="{{ \App\Support\Branding::name() }}">
            <img src="{{ \App\Support\Branding::logoUrl('on_color') }}" alt="{{ \App\Support\Branding::name() }}">
        </a>
        {{ $slot }}
        <div style="text-align: center"><a href="{{ route('app.home') }}" wire:navigate class="pa-link pa-soft">← Înapoi la petreceri</a></div>
    </div>

    @include('layouts.partials-participant-toast')

    @livewireScripts
    @include('layouts.partials-participant-sw')
</body>
</html>
