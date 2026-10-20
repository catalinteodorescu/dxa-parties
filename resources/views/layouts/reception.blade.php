@php $pwa = $pwa ?? \App\Support\ReceptionApp::class; @endphp
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
                <a href="{{ route($pwa::ROUTE_PREFIX.'.home') }}" wire:navigate class="flex items-center gap-2 min-w-0">
                    <img src="{{ $pwa::logoUrl() }}" alt="" class="h-8 w-auto">
                    <span class="text-sm font-semibold tracking-wide truncate">{{ $pwa::name() }}</span>
                </a>

                <div class="flex items-center gap-2 min-w-0">
                    <span class="truncate max-w-[6.5rem] sm:max-w-none text-sm text-white/80" data-active-worker>{{ auth('admin')->user()->name }}</span>
                    {{-- DXA: adaugat (runda 71). Schimbare rapidă între lucrători (nume + PIN) --}}
                    @livewire(\App\Livewire\Reception\SwitchWorker::class, ['prefix' => $pwa::ROUTE_PREFIX], key('switch-worker'))
                    @if (\App\Services\Plan\PlanGate::allows('push_staff'))
                        @php
                            $inboxApp = $pwa::ROUTE_PREFIX === 'bar' ? 'bar' : 'reception';
                            $unreadNotes = \App\Services\Push\Inbox::unread(\App\Services\Push\Inbox::forStaff((int) (\App\Http\Middleware\ApplyAppWorker::loginId() ?? auth('admin')->id()), $inboxApp));
                        @endphp
                        {{-- Runda 91: clopoțelul „Notificări”, cu numărul de necitite --}}
                        <a href="{{ route($pwa::ROUTE_PREFIX.'.notifications') }}" wire:navigate title="Notificări" aria-label="Notificări" data-bell
                           class="relative rounded-lg h-9 w-9 inline-flex items-center justify-center bg-white/15 hover:bg-white/25 transition-colors">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                            <span class="absolute -top-1 -right-1 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-white text-primary text-[10px] font-bold flex items-center justify-center leading-none"
                                  data-inbox-badge data-url="{{ route($pwa::ROUTE_PREFIX.'.notifications.count', [], false) }}" @if ($unreadNotes < 1) style="display: none" @endif>{{ $unreadNotes > 99 ? '99+' : $unreadNotes }}</span>
                        </a>
                    @endif
                    <form action="{{ route('session.logout') }}" method="POST">
                        @csrf
                        <input type="hidden" name="to" value="{{ $pwa::ROUTE_PREFIX }}">
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

    <x-staff-push-prompt :app="$pwa::ROUTE_PREFIX === 'bar' ? 'bar' : 'reception'" />

    @include('layouts.partials-reception-loader')
    @include('layouts.partials-reception-device')

    @include('layouts.partials-push-init')
    @include('layouts.partials-inbox-badge')
    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            // După login pagina vine prin navigare Livewire (fără evenimentul `load`): dacă documentul e deja încărcat, înregistrăm imediat.
            const registerSw = () => navigator.serviceWorker.register('{{ route($pwa::ROUTE_PREFIX.'.sw', [], false) }}', { scope: '{{ $pwa::URL_PREFIX }}' }).catch(() => {});
            document.readyState === 'complete' ? registerSw() : window.addEventListener('load', registerSw);
        }
    </script>
</body>
</html>
