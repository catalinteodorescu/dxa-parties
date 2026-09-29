<!DOCTYPE html>
<html lang="ro">
<head>
    @include('layouts.partials-reception-head')
</head>
<body class="bg-primary text-white font-sans antialiased">
    {{-- DXA: adaugat (PWA Recepție). Layout pentru login: o singură coloană centrată, logo deasupra. --}}
    <div class="min-h-screen flex items-center justify-center p-5">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                <img src="{{ \App\Support\ReceptionApp::logoUrl() }}" alt="" class="h-14 w-auto">
                <div class="text-lg font-semibold tracking-wide">{{ \App\Support\ReceptionApp::name() }}</div>
                <p class="text-sm text-white/80">Intră cu contul tău.</p>
            </div>
            {{ $slot }}
        </div>
    </div>
    @include('layouts.partials-reception-loader')

    @livewireScripts
</body>
</html>
