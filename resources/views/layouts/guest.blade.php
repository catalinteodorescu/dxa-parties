<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Dance Xplosion Academy') }} — Autentificare</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-bg text-ink font-sans antialiased">

    <div class="min-h-screen flex">

        {{-- Panou stanga: brand (ascuns pe mobil), verde solid --}}
        <div class="hidden lg:flex lg:w-[42%] bg-primary text-white flex-col justify-between p-12 relative overflow-hidden">

            <div class="relative z-10">
                <img src="{{ asset('images/logo-xplosion-white.png') }}" alt="Xplosion Dance Academy" class="h-16 w-auto">
            </div>

            <div class="relative z-10 max-w-sm">
                <p class="text-2xl font-medium leading-snug">
                    Administrează petrecerile, anunțurile și barul școlii de dans, toate dintr-un singur loc.
                </p>
                <p class="mt-4 text-sm text-white/70">
                    Panou de administrare
                </p>
            </div>

            {{-- decor discret: arcuri concentrice, sugereaza un puls / ritm --}}
            <svg class="absolute -right-24 -bottom-24 w-96 h-96 opacity-[0.15]" viewBox="0 0 200 200" fill="none">
                <circle cx="100" cy="100" r="90" stroke="white" stroke-width="1"/>
                <circle cx="100" cy="100" r="65" stroke="white" stroke-width="1"/>
                <circle cx="100" cy="100" r="40" stroke="white" stroke-width="1"/>
            </svg>
        </div>

        {{-- Panou dreapta: formular --}}
        <div class="flex-1 flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-sm">

                <div class="lg:hidden mb-8 flex justify-center">
                    <img src="{{ asset('images/logo-xplosion.png') }}" alt="Xplosion Dance Academy" class="h-14 w-auto">
                </div>

                {{ $slot }}

            </div>
        </div>

    </div>

    @livewireScripts
</body>
</html>
