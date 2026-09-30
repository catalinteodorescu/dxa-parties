<!DOCTYPE html>
<html lang="ro">
<head>
    @include('layouts.partials-participant-head')
</head>
<body class="pa-body">
    {{-- DXA: adaugat (Aplicația participanților). Shell mobile-first: antet cu logo + acces cont, conținut într-o coloană, navigare jos. --}}
    @php
        $me = auth('participant')->user();
        $tabs = [
            ['app.home', 'Acasă', ['app.home'], '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>'],
            ['app.parties', 'Petreceri', ['app.parties', 'app.party'], '<path d="M5.8 11.3 2 22l10.7-3.8"/><path d="M4 3h.01M22 8h.01M15 2h.01M22 20h.01"/><path d="m22 2-2.2.7a2.9 2.9 0 0 0-1.9 3.2L18 7"/><path d="M11 13c1.9 1.9 3.9 3.2 6 2.9M8 8.5c-.3 2.4 1 4.5 2.9 6.4"/>'],
            ['app.account', $me ? 'Cont' : 'Intră', ['app.account', 'app.login', 'app.register', 'app.verify', 'app.forgot', 'app.reset'], '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>'],
        ];
    @endphp
    <div class="pa-shell">
        <header class="pa-top">
            <a href="{{ route('app.home') }}" wire:navigate class="pa-a" aria-label="{{ \App\Support\Branding::name() }}">
                <img src="{{ \App\Support\Branding::logoUrl('on_color') }}" alt="{{ \App\Support\Branding::name() }}">
            </a>
            @if ($me)
                <a href="{{ route('app.account') }}" wire:navigate class="pa-a" aria-label="Contul meu ({{ $me->name }})">
                    @include('livewire.participant._avatar', ['who' => $me, 'size' => 'sm'])
                </a>
            @else
                <a href="{{ route('app.login') }}" wire:navigate class="pa-btn pa-btn-sm">Intră în cont</a>
            @endif
        </header>

        <main>
            {{ $slot }}
        </main>
    </div>

    <nav class="pa-nav" aria-label="Navigare principală">
        @foreach ($tabs as [$route, $label, $group, $icon])
            <a href="{{ route($route) }}" wire:navigate class="{{ request()->routeIs(...$group) ? 'on' : '' }}" @if (request()->routeIs(...$group)) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    @include('layouts.partials-participant-toast')

    @livewireScripts
    @include('layouts.partials-participant-sw')
</body>
</html>
