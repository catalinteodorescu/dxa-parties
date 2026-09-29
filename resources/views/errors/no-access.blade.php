<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\Branding::name() }} — Fără acces</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-theme-style />
</head>
<body class="bg-bg text-ink font-sans antialiased">
    {{-- DXA: adaugat (Utilizatori - acces pe aplicație). Afișată (403) când contul nu are bifa aplicației cerute. --}}
    <div class="min-h-screen flex items-center justify-center p-5">
        <div class="w-full max-w-sm bg-surface border border-border rounded-2xl p-6 shadow-sm">
            <h1 class="text-xl font-semibold text-ink">Nu ai acces la această aplicație</h1>
            <p class="mt-2 text-sm text-ink-soft">
                Contul {{ $admin->name ?: $admin->phone }} nu are acces la „{{ \App\Models\Admin::APP_LABELS[$app] ?? $app }}”.
                Dacă ai nevoie de acces, cere-l unui superadmin.
            </p>

            <div class="mt-5 space-y-2">
                @if ($app !== \App\Models\Admin::APP_ADMIN && $admin->canAccess(\App\Models\Admin::APP_ADMIN))
                    <a href="{{ route('admin.dashboard') }}" class="block w-full text-center rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">Mergi la panoul admin</a>
                @endif
                @if ($app !== \App\Models\Admin::APP_RECEPTION && $admin->canAccess(\App\Models\Admin::APP_RECEPTION))
                    <a href="{{ route('receptie.home') }}" class="block w-full text-center rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">Mergi la aplicația de recepție</a>
                @endif

                @if ($app !== \App\Models\Admin::APP_BAR && $admin->canAccess(\App\Models\Admin::APP_BAR))
                    <a href="{{ route('bar.home') }}" class="block w-full text-center rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">Mergi la aplicația de bar</a>
                @endif

                <form action="{{ route('session.logout') }}" method="POST">
                    @csrf
                    <input type="hidden" name="to" value="{{ match ($app) { \App\Models\Admin::APP_RECEPTION => 'receptie', \App\Models\Admin::APP_BAR => 'bar', default => 'admin' } }}">
                    <button type="submit" class="w-full rounded-lg border border-border bg-surface hover:bg-bg text-ink text-sm font-medium px-4 py-2.5 transition-colors">Deconectează-te</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
