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

            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto"
                 x-data
                 x-init="$nextTick(() => { const a = $el.querySelector('a.bg-primary-soft'); if (a) { $el.scrollTop += a.getBoundingClientRect().top - $el.getBoundingClientRect().top - $el.clientHeight / 2 } })">

                <a href="{{ route('admin.dashboard') }}" wire:navigate @click="sidebarOpen = false"
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                          {{ request()->routeIs('admin.dashboard') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>
                    </svg>
                    Dashboard
                </a>

                {{-- Grup collapsabil: Petreceri --}}
                {{-- DXA: adaugat (Petreceri) — Adaugă + Listă + Statistici (agregate, all-time). --}}
                <div x-data="{ partiesOpen: true }" class="pt-4">
                    <button type="button" @click="partiesOpen = ! partiesOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/>
                        </svg>
                        <span>Petreceri</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="partiesOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>

                    <div x-show="partiesOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        <a href="{{ route('admin.parties.create') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.parties.create') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14"/><path d="M12 5v14"/>
                            </svg>
                            Adaugă
                        </a>

                        <a href="{{ route('admin.parties.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.parties.index') || request()->routeIs('admin.parties.show') || request()->routeIs('admin.parties.edit') || request()->routeIs('admin.parties.stats') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/>
                            </svg>
                            Listă
                        </a>

                        <a href="{{ route('admin.parties.overview') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.parties.overview') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>
                            </svg>
                            Statistici
                        </a>

                        {{-- DXA: adaugat (Bilanțul serii) --}}
                        <a href="{{ route('admin.reconciliation') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.reconciliation') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
                            Bilanțul serii
                        </a>
                    </div>
                </div>

                {{-- Grup collapsabil: Conținut --}}
                <div x-data="{ contentOpen: true }" class="pt-4">
                    <button type="button" @click="contentOpen = ! contentOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z"/><path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12"/><path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17"/>
                        </svg>
                        <span>Conținut</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="contentOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>

                    <div x-show="contentOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        <a href="{{ route('admin.announcements.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.announcements.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"/><path d="M8 6v8"/>
                            </svg>
                            Anunțuri
                        </a>
                    </div>
                </div>

                {{-- Grup collapsabil: Bar --}}
                {{-- DXA: adaugat (Bar) — Meniu + Stocuri + Necesare; Raportări/Rapoarte se adaugă aici mai târziu. --}}
                <div x-data="{ menuBarOpen: true }" class="pt-4">
                    <button type="button" @click="menuBarOpen = ! menuBarOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 12 4.207 4.207A.707.707 0 0 1 4.707 3h14.586a.707.707 0 0 1 .5 1.207z"/><path d="M12 12v10"/><path d="M7 22h10"/>
                        </svg>
                        <span>Bar</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="menuBarOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>

                    <div x-show="menuBarOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        {{-- DXA: adaugat (Bar - vanzari) --}}
                        <a href="{{ route('admin.sales.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.sales.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 17V7"/><path d="M16 8h-6a2 2 0 0 0 0 4h4a2 2 0 0 1 0 4H8"/><path d="M4 3a1 1 0 0 1 1-1 1.3 1.3 0 0 1 .7.2l.933.6a1.3 1.3 0 0 0 1.4 0l.934-.6a1.3 1.3 0 0 1 1.4 0l.933.6a1.3 1.3 0 0 0 1.4 0l.933-.6a1.3 1.3 0 0 1 1.4 0l.934.6a1.3 1.3 0 0 0 1.4 0l.933-.6A1.3 1.3 0 0 1 19 2a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1 1.3 1.3 0 0 1-.7-.2l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.934.6a1.3 1.3 0 0 1-1.4 0l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-1.4 0l-.934-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-.7.2 1 1 0 0 1-1-1z"/>
                            </svg>
                            Vânzări
                        </a>

                        <a href="{{ route('admin.menu-items.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.menu-items.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v16"/><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"/>
                            </svg>
                            Meniu
                        </a>

                        <a href="{{ route('admin.stock-items.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.stock-items.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2.97 12.92A2 2 0 0 0 2 14.63v3.24a2 2 0 0 0 .97 1.71l3 1.8a2 2 0 0 0 2.06 0L12 19v-5.5l-5-3-4.03 2.42Z"/><path d="m7 16.5-4.74-2.85"/><path d="m7 16.5 5-3"/><path d="M7 16.5v5.17"/><path d="M12 13.5V19l3.97 2.38a2 2 0 0 0 2.06 0l3-1.8a2 2 0 0 0 .97-1.71v-3.24a2 2 0 0 0-.97-1.71L17 10.5l-5 3Z"/><path d="m17 16.5-5-3"/><path d="m17 16.5 4.74-2.85"/><path d="M17 16.5v5.17"/><path d="M7.97 4.42A2 2 0 0 0 7 6.13v4.37l5 3 5-3V6.13a2 2 0 0 0-.97-1.71l-3-1.8a2 2 0 0 0-2.06 0l-3 1.8Z"/><path d="M12 8 7.26 5.15"/><path d="m12 8 4.74-2.85"/><path d="M12 13.5V8"/>
                            </svg>
                            Stocuri
                        </a>

                        {{-- DXA: adaugat (Bar - necesare) --}}
                        <a href="{{ route('admin.stock-requisitions.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.stock-requisitions.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>
                            </svg>
                            Necesare
                        </a>

                        {{-- DXA: adaugat (Bar - raportari) --}}
                        <a href="{{ route('admin.stock-reports.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.stock-reports.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M8 18v-1"/><path d="M12 18v-6"/><path d="M16 18v-3"/>
                            </svg>
                            Raportări stoc
                        </a>

                        {{-- DXA: adaugat (Bar - raportări casă) --}}
                        <a href="{{ route('admin.bar.reports.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.bar.reports.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="m9 15 2 2 4-4"/></svg>
                            Raportări casă
                            @if (($n = \App\Models\BarReport::awaitingAdmin()->count()) > 0)<span class="ml-auto inline-flex min-w-[1.25rem] justify-center rounded-full bg-warning text-white text-[11px] font-semibold px-1.5 py-0.5">{{ $n }}</span>@endif
                        </a>

                        {{-- DXA: adaugat (Bar - statistici agregate) --}}
                        <a href="{{ route('admin.bar.stats') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.bar.stats') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>
                            </svg>
                            Statistici
                        </a>
                    </div>
                </div>

                {{-- Grup collapsabil: Recepție --}}
                {{-- DXA: adaugat (Recepție) — Intrări + Tokeni + Raportări (închiderea casei). --}}
                <div x-data="{ receptionOpen: true }" class="pt-4">
                    <button type="button" @click="receptionOpen = ! receptionOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>
                        </svg>
                        <span>Recepție</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="receptionOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"/>
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
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>
                            </svg>
                            Intrări
                        </a>

                        <a href="{{ route('admin.reception.tokens') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.reception.tokens') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                            </svg>
                            Tokeni
                        </a>

                        <a href="{{ route('admin.reception.reports.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.reception.reports.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M8 18v-1"/><path d="M12 18v-6"/><path d="M16 18v-3"/>
                            </svg>
                            Raportări casă
                            @if (($n = \App\Models\ReceptionReport::awaitingAdmin()->count()) > 0)<span class="ml-auto inline-flex min-w-[1.25rem] justify-center rounded-full bg-warning text-white text-[11px] font-semibold px-1.5 py-0.5">{{ $n }}</span>@endif
                        </a>

                        {{-- DXA: adaugat (Receptie - statistici agregate) --}}
                        <a href="{{ route('admin.reception.stats') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.reception.stats') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>
                            </svg>
                            Statistici
                        </a>
                    </div>
                </div>

                {{-- Grup collapsabil: Participanți --}}
                {{-- DXA: adaugat (Participanți) — Listă + Statistici (topuri) + Carduri (fidelitate). --}}
                <div x-data="{ participantsOpen: true }" class="pt-4">
                    <button type="button" @click="participantsOpen = ! participantsOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/>
                        </svg>
                        <span>Participanți</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="participantsOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>

                    <div x-show="participantsOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        <a href="{{ route('admin.participants.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.participants.index') || request()->routeIs('admin.participants.show') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/>
                            </svg>
                            Listă
                        </a>

                        @if (\App\Services\LoyaltyLedger::enabled())
                            <a href="{{ route('admin.loyalty.cards') }}" wire:navigate @click="sidebarOpen = false"
                               class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                      {{ request()->routeIs('admin.loyalty.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>
                                </svg>
                                Carduri
                            </a>
                        @endif

                        {{-- DXA: adaugat (Credite - pagina Credite): apare cât creditele sunt active sau există mișcări. --}}
                        @if (\App\Services\CreditsOverview::visible())
                            <a href="{{ route('admin.credits.index') }}" wire:navigate @click="sidebarOpen = false"
                               class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                      {{ request()->routeIs('admin.credits.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01"/><path d="M18 12h.01"/>
                                </svg>
                                Credite
                            </a>
                        @endif

                        <a href="{{ route('admin.participants.stats') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.participants.stats') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>
                            </svg>
                            Statistici
                        </a>
                    </div>
                </div>

                {{-- Grupul următor (Credite utilizatori) se adaugă aici, pe măsură ce-l construim. --}}

                {{-- Grup collapsabil: Utilizatori --}}
                <div x-data="{ adminsOpen: true }" class="pt-4">
                    <button type="button" @click="adminsOpen = ! adminsOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/>
                        </svg>
                        <span>Utilizatori</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="adminsOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"/>
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
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/>
                            </svg>
                            Listă useri
                        </a>

                        <a href="{{ route('admin.account.edit') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.account.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>
                            </svg>
                            Contul meu
                        </a>

                        @if (auth('admin')->user()->isSuperAdmin())
                            <a href="{{ route('admin.logs.index') }}" wire:navigate @click="sidebarOpen = false"
                               class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                      {{ request()->routeIs('admin.logs.*') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6h4"/>
                                </svg>
                                Jurnal activitate
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Grup collapsabil: Setări --}}
                {{-- DXA: adaugat (Setari) --}}
                <div x-data="{ settingsOpen: true }" class="pt-4">
                    <button type="button" @click="settingsOpen = ! settingsOpen"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wide text-ink-soft/70 hover:bg-bg hover:text-ink-soft transition-colors">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <span>Setări</span>
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="settingsOpen ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>

                    <div x-show="settingsOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-4 pl-3 border-l border-border space-y-1">

                        <a href="{{ route('admin.settings.index') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.settings.index') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            Setări generale
                        </a>

                        {{-- DXA: adaugat (PWA Recepție - setări aplicație): temă, nume, logo --}}
                        <a href="{{ route('admin.settings.reception-app') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.settings.reception-app') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>
                            </svg>
                            Aplicație recepție
                        </a>

                        {{-- DXA: adaugat (PWA Bar - setări aplicație): nume, logo, temă --}}
                        <a href="{{ route('admin.settings.bar-app') }}" wire:navigate @click="sidebarOpen = false"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ request()->routeIs('admin.settings.bar-app') ? 'bg-primary-soft text-primary' : 'text-ink-soft hover:bg-bg' }}">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>
                            </svg>
                            Aplicație bar
                        </a>
                    </div>
                </div>

                {{-- Deconectare: acum in fluxul normal al meniului (nu mai e fix jos), dupa Setari --}}
                <div class="pt-4 mt-4 border-t border-border">
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-soft hover:bg-bg hover:text-ink transition-colors">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            </svg>
                            Deconectare
                        </button>
                    </form>
                </div>
            </nav>
        </aside>

        {{-- Continut --}}
        <div class="flex-1 flex flex-col min-w-0">

            {{-- Header: verde solid (fara gradient), text deschis. Fix la scroll (sticky). --}}
            <header class="sticky top-0 z-20 bg-primary h-14 shrink-0 flex items-center gap-3 px-4 md:px-6 text-white">
                <button type="button" @click="sidebarOpen = true" class="md:hidden text-white/90 hover:text-white -ml-1 p-1">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>
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
