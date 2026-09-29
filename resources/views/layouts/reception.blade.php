<!DOCTYPE html>
<html lang="ro">
<head>
    @include('layouts.partials-reception-head')
</head>
<body class="{{ ($tinted ?? false) ? 'bg-primary text-white' : 'bg-bg text-ink' }} font-sans antialiased">
    {{-- DXA: adaugat (PWA Recepție). Layout mobile-first: antet fix cu brand + utilizator, conținut într-o coloană. --}}
    <div class="min-h-screen flex flex-col" style="padding-bottom: env(safe-area-inset-bottom)">

        <header class="sticky top-0 z-30 bg-primary text-white" style="padding-top: env(safe-area-inset-top)">
            <div class="mx-auto max-w-xl h-14 px-4 flex items-center justify-between gap-3">
                <a href="{{ route('receptie.home') }}" wire:navigate class="flex items-center gap-2 min-w-0">
                    <img src="{{ \App\Support\ReceptionApp::logoUrl() }}" alt="" class="h-8 w-auto">
                    <span class="text-sm font-semibold tracking-wide truncate">{{ \App\Support\ReceptionApp::name() }}</span>
                </a>

                <div class="flex items-center gap-2 min-w-0">
                    <span class="hidden sm:block truncate text-sm text-white/80">{{ auth('admin')->user()->name }}</span>
                    <form action="{{ route('session.logout') }}" method="POST">
                        @csrf
                        <input type="hidden" name="to" value="receptie">
                        <button type="submit" title="Deconectare" aria-label="Deconectare"
                                class="rounded-lg p-2 bg-white/15 hover:bg-white/25 transition-colors">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="flex-1 mx-auto w-full max-w-xl px-4 py-5">
            {{ $slot }}
        </main>
    </div>

    @include('layouts.partials-reception-loader')

    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ route('receptie.sw', [], false) }}', { scope: '/receptie/' }).catch(() => {});
            });
        }
    </script>
</body>
</html>
