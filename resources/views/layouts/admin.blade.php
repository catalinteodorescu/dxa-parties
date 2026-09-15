<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Admin') }} — Panou admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-bg text-ink font-sans antialiased">

    <div class="min-h-screen flex">

        {{-- Sidebar --}}
        <aside class="w-64 shrink-0 bg-surface border-r border-border flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-border">
                <span class="font-semibold tracking-tight">{{ config('app.name', 'Dance Party') }}</span>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-1">

                <a href="{{ route('admin.dashboard') }}" wire:navigate
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                          {{ request()->routeIs('admin.dashboard') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                    </svg>
                    Dashboard
                </a>

                <div class="pt-4">
                    <div class="flex items-center gap-2.5 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="8" r="3"/>
                            <path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/>
                            <circle cx="17" cy="8" r="2.5"/>
                            <path d="M15.5 14c2.5 .3 4.5 2 4.5 5"/>
                        </svg>
                        Administratori
                    </div>

                    <a href="{{ route('admin.users.index') }}" wire:navigate
                       class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                              {{ request()->routeIs('admin.users.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                        Listă useri
                    </a>

                    @if (auth('admin')->user()->isSuperAdmin())
                        <a href="{{ route('admin.logs.index') }}" wire:navigate
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.logs.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"/>
                                <polyline points="12 7 12 12 15 15"/>
                            </svg>
                            Jurnal activitate
                        </a>
                    @endif

                    <a href="{{ route('admin.account.edit') }}" wire:navigate
                       class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                              {{ request()->routeIs('admin.account.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <circle cx="12" cy="10" r="3"/>
                            <path d="M6.5 18.5c1.2-2.2 3.2-3.5 5.5-3.5s4.3 1.3 5.5 3.5"/>
                        </svg>
                        Contul meu
                    </a>
                </div>

                {{-- Secțiunile următoare (Utilizatori, Anunțuri, Petreceri, Bar, Credite, Card fidelitate)
                     se adaugă aici, pe măsură ce le construim. --}}
            </nav>

            <div class="p-3 border-t border-border">
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-soft hover:bg-bg hover:text-ink transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Deconectare
                    </button>
                </form>
            </div>
        </aside>

        {{-- Continut --}}
        <div class="flex-1 flex flex-col min-w-0">

            <header class="h-16 shrink-0 border-b border-border bg-surface flex items-center justify-end px-6">
                <span class="text-sm text-ink-soft">Salut, {{ auth('admin')->user()->name }}</span>
            </header>

            <main class="flex-1 p-6">
                {{ $slot }}
            </main>

        </div>

    </div>

    @livewireScripts
</body>
</html>
