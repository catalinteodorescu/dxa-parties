<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\Branding::name() }} — Indisponibil</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="preload" href="{{ asset('fonts/manrope-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/manrope.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-theme-style />
</head>
<body class="bg-bg text-ink font-sans antialiased">
    {{-- DXA: adaugat (runda 80). Funcția sau aplicația nu face parte din planul instalării. Fără detalii despre plan: pagina o văd și participanții. --}}
    <div class="min-h-screen flex items-center justify-center p-5">
        <div class="w-full max-w-sm bg-surface border border-border rounded-2xl p-6 shadow-sm text-center" data-app-unavailable>
            <h1 class="text-xl font-semibold text-ink">{{ $whole ? 'Aplicația nu este disponibilă' : 'Această secțiune nu este disponibilă' }}</h1>
            <p class="mt-2 text-sm text-ink-soft">
                {{ $whole ? 'Aplicația nu este activată momentan. Te rugăm să revii mai târziu sau să ne contactezi.' : 'Secțiunea nu este activată momentan.' }}
            </p>
            @unless ($whole)
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="mt-5 inline-block rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">Înapoi</a>
            @endunless
        </div>
    </div>
</body>
</html>
