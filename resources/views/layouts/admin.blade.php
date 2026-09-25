<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\Branding::name() }} — Panou admin</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-theme-style />
    @livewireStyles
</head>
<body class="bg-bg text-ink font-sans antialiased">

    <div x-data="{ sidebarOpen: false }" class="min-h-screen md:flex">

        {{-- Backdrop, doar pe mobil cand sidebar-ul e deschis --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
             class="fixed inset-0 bg-ink/40 z-30 md:hidden"></div>

        {{-- Sidebar: off-canvas pe mobil, fix pe desktop (sticky, inaltime = viewport, scroll propriu pe nav) --}}
        <aside
            class="w-64 shrink-0 bg-surface border-r border-border flex flex-col fixed inset-y-0 left-0 z-40 transform transition-transform duration-200 md:sticky md:top-0 md:h-screen md:self-start md:translate-x-0 md:z-auto"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            {{-- Brand text (wordmark), pe verde solid --}}
            <a href="{{ route('admin.dashboard') }}" wire:navigate @click="sidebarOpen = false"
               class="bg-primary h-14 flex items-center px-6 shrink-0">
                <img src="{{ \App\Support\Branding::logoUrl('on_color') }}" alt="{{ \App\Support\Branding::name() }}" class="h-10 w-auto">
            </a>

            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

                <a href="{{ route('admin.dashboard') }}" wire:navigate @click="sidebarOpen = false"
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

                {{-- Grup collapsabil: Administratori --}}
                <div x-data="{ contentOpen: true }" class="pt-4">
                    <button type="button" @click="contentOpen = ! contentOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 11v2a1 1 0 0 0 1 1h2l3.5 3.5V7.5L6 11H4a1 1 0 0 0-1 0z"/>
                            <path d="M15 8a4 4 0 0 1 0 8"/>
                            <path d="M9.5 7.5L18 4v16l-8.5-3.5"/>
                        </svg>
                        <span>Conținut</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="contentOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    <div x-show="contentOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        <a href="{{ route('admin.parties.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.parties.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="2" x2="12" y2="4.5"/><circle cx="12" cy="13" r="7.5"/><line x1="12" y1="5.5" x2="12" y2="20.5"/><line x1="4.5" y1="13" x2="19.5" y2="13"/><path d="M6.2 9c3.6 1.6 8 1.6 11.6 0"/><path d="M6.2 17c3.6-1.6 8-1.6 11.6 0"/><path d="M9 5.8c-1.6 4.6-1.6 9.8 0 14.4"/><path d="M15 5.8c1.6 4.6 1.6 9.8 0 14.4"/>
                            </svg>
                            Petreceri
                        </a>

                        <a href="{{ route('admin.announcements.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.announcements.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 11v2a1 1 0 0 0 1 1h2l3.5 3.5V7.5L6 11H4a1 1 0 0 0-1 0z"/>
                                <path d="M15 8a4 4 0 0 1 0 8"/>
                                <path d="M9.5 7.5L18 4v16l-8.5-3.5"/>
                            </svg>
                            Anunțuri
                        </a>
                    </div>
                </div>

                <div x-data="{ adminsOpen: true }" class="pt-4">
                    <button type="button" @click="adminsOpen = ! adminsOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="8" r="3"/>
                            <path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/>
                            <circle cx="17" cy="8" r="2.5"/>
                            <path d="M15.5 14c2.5 .3 4.5 2 4.5 5"/>
                        </svg>
                        <span>Administratori</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="adminsOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    {{-- Sub-elemente, indentate cu ghidaj pe stanga --}}
                    <div x-show="adminsOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        <a href="{{ route('admin.users.index') }}" wire:navigate @click="sidebarOpen = false"
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

                        <a href="{{ route('admin.account.edit') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.account.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"/>
                                <circle cx="12" cy="10" r="3"/>
                                <path d="M6.5 18.5c1.2-2.2 3.2-3.5 5.5-3.5s4.3 1.3 5.5 3.5"/>
                            </svg>
                            Contul meu
                        </a>

                        @if (auth('admin')->user()->isSuperAdmin())
                            <a href="{{ route('admin.logs.index') }}" wire:navigate @click="sidebarOpen = false"
                               class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                      {{ request()->routeIs('admin.logs.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="9"/>
                                    <polyline points="12 7 12 12 15 15"/>
                                </svg>
                                Jurnal activitate
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Grup collapsabil: Bar --}}
                {{-- DXA: adaugat (Bar) — Meniu + Stocuri + Necesare; Raportări/Rapoarte se adaugă aici mai târziu. --}}
                <div x-data="{ menuBarOpen: true }" class="pt-4">
                    <button type="button" @click="menuBarOpen = ! menuBarOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 4h14l-7 8-7-8z"/><line x1="12" y1="12" x2="12" y2="20"/><line x1="8" y1="20" x2="16" y2="20"/>
                        </svg>
                        <span>Bar</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="menuBarOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    <div x-show="menuBarOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        <a href="{{ route('admin.menu-items.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.menu-items.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 3h10l-1.2 15.5A2 2 0 0 1 13.8 20h-3.6a2 2 0 0 1-2-1.8L7 3z"/><line x1="7.6" y1="9" x2="16.4" y2="9"/>
                            </svg>
                            Meniu
                        </a>

                        <a href="{{ route('admin.stock-items.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.stock-items.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 7L12 3 4 7m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            Stocuri
                        </a>

                        {{-- DXA: adaugat (Bar - necesare) --}}
                        <a href="{{ route('admin.stock-requisitions.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.stock-requisitions.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 4H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><rect x="9" y="2" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/>
                            </svg>
                            Necesare
                        </a>

                        {{-- DXA: adaugat (Bar - vanzari) --}}
                        <a href="{{ route('admin.sales.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.sales.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                            Vânzări
                        </a>

                        {{-- DXA: adaugat (Bar - raportari) --}}
                        <a href="{{ route('admin.stock-reports.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.stock-reports.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>
                            </svg>
                            Raportări
                        </a>
                    </div>
                </div>

                {{-- Grup collapsabil: Recepție --}}
                {{-- DXA: adaugat (Recepție) — Intrări + Tokeni + Raportări (închiderea casei). --}}
                <div x-data="{ receptionOpen: true }" class="pt-4">
                    <button type="button" @click="receptionOpen = ! receptionOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                        </svg>
                        <span>Recepție</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="receptionOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    <div x-show="receptionOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        <a href="{{ route('admin.reception.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.reception.index') || request()->routeIs('admin.reception.create') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            Intrări
                        </a>

                        <a href="{{ route('admin.reception.tokens') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.reception.tokens') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"/><path d="M9 9h6M12 9v7"/>
                            </svg>
                            Tokeni
                        </a>

                        <a href="{{ route('admin.reception.reports.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.reception.reports.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/>
                            </svg>
                            Raportări
                        </a>
                    </div>
                </div>

                {{-- DXA: adaugat (Participanți) --}}
                <a href="{{ route('admin.participants.index') }}" wire:navigate @click="sidebarOpen = false"
                   class="mt-4 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                          {{ request()->routeIs('admin.participants.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Participanți
                </a>

                {{-- Grupurile urmatoare (Credite utilizatori, Card fidelitate)
                     se adaugă aici, fiecare in grupul potrivit, pe măsură ce le construim. --}}

                {{-- Grup collapsabil: Setări --}}
                {{-- DXA: adaugat (Setari) --}}
                <div x-data="{ settingsOpen: true }" class="pt-4">
                    <button type="button" @click="settingsOpen = ! settingsOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        <span>Setări</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="settingsOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    <div x-show="settingsOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        <a href="{{ route('admin.settings.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.settings.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                            Setări generale
                        </a>
                    </div>
                </div>
            </nav>

            <div class="p-3 border-t border-border shrink-0">
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

            {{-- Header: verde solid (fara gradient), text deschis --}}
            <header class="bg-primary h-14 shrink-0 flex items-center gap-3 px-4 md:px-6 text-white">
                <button type="button" @click="sidebarOpen = true" class="md:hidden text-white/90 hover:text-white -ml-1 p-1">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="7" x2="20" y2="7"/>
                        <line x1="4" y1="12" x2="20" y2="12"/>
                        <line x1="4" y1="17" x2="20" y2="17"/>
                    </svg>
                </button>
                <img src="{{ \App\Support\Branding::logoUrl('on_color') }}" alt="{{ \App\Support\Branding::name() }}" class="md:hidden h-8 w-auto">
                <span class="text-sm text-white/85 ml-auto truncate">Salut, {{ auth('admin')->user()->name }}</span>
            </header>

            <main class="flex-1 p-4 md:p-6">
                {{ $slot }}
            </main>

        </div>

    </div>

    @livewireScripts
</body>
</html>
