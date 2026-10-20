{{--
    DXA: adaugat (PWA Recepție / Bar). Loader între ecrane: un clopoțel care se leagănă, cu marginile colorate progresiv în culoarea temei
    aplicației. Apare (după ~150 ms, ca să nu clipească la navigări rapide) când Livewire schimbă ecranul (wire:navigate) și dispare când
    noul ecran e gata (plasă de siguranță, runda 71: dispare singur după 6 s și la întoarcerea din cache). @persist îl păstrează între
    ecrane, deci nu se resetează. Runda 88: Recepția are clopoțel, Barul are pahar; loaderul e un card mic în centru, fără voal pe tot
    ecranul (voalul acoperea antetul: se vedea o umbră albastră, iar ceasul din bara de stare trecea din alb în negru).
--}}
@php $loaderIsBar = ($pwa ?? null) === \App\Support\BarApp::class; @endphp
<style>
    .rc-glass { transform-origin: 50% 92%; animation: rc-cheers 1.3s ease-in-out infinite; }
    .rc-glass-trace { stroke-dasharray: 100; stroke-dashoffset: 100; animation: rc-trace 1.4s ease-in-out infinite; }
    @keyframes rc-cheers {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(-9deg); } 50% { transform: rotate(0deg); } 75% { transform: rotate(9deg); }
    }
    .rc-bell { transform-origin: 50% 6%; animation: rc-ring 1.1s ease-in-out infinite; }
    .rc-bell-trace { stroke-dasharray: 100; stroke-dashoffset: 100; animation: rc-trace 1.4s ease-in-out infinite; }
    @keyframes rc-ring {
        0%, 60%, 100% { transform: rotate(0deg); }
        10% { transform: rotate(13deg); } 20% { transform: rotate(-11deg); }
        30% { transform: rotate(8deg); } 40% { transform: rotate(-5deg); } 50% { transform: rotate(2deg); }
    }
    @keyframes rc-trace { 0% { stroke-dashoffset: 100; } 55%, 100% { stroke-dashoffset: 0; } }
    @media (prefers-reduced-motion: reduce) { .rc-bell, .rc-bell-trace, .rc-glass, .rc-glass-trace { animation: none; stroke-dashoffset: 0; } }
</style>
@persist('reception-loader')
    <div x-data="{ show: false, timer: null, safety: null }"
         x-init="document.addEventListener('livewire:navigate', () => { clearTimeout(timer); clearTimeout(safety); timer = setTimeout(() => show = true, 150); safety = setTimeout(() => show = false, 6000); });
                 document.addEventListener('livewire:navigated', () => { clearTimeout(timer); clearTimeout(safety); show = false; });
                 window.addEventListener('pageshow', () => { clearTimeout(timer); clearTimeout(safety); show = false; });"
         x-show="show" x-cloak x-transition.opacity.duration.150ms
         class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none" aria-hidden="true">
      <div class="rounded-2xl bg-surface p-5 shadow-lg border border-border">
        @if ($loaderIsBar)
        <svg class="rc-glass w-14 h-14" viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            {{-- pahar de martini: umplere discretă și contur gri; deasupra, conturul se colorează în tema aplicației --}}
            <path d="m19 3-7 8-7-8Z" pathLength="100" style="fill: var(--color-primary-soft); stroke: var(--color-border)"/>
            <path d="M12 11v11M8 22h8" pathLength="100" style="stroke: var(--color-border)"/>
            <path class="rc-glass-trace" d="m19 3-7 8-7-8Z" pathLength="100" style="stroke: var(--color-primary)"/>
            <path class="rc-glass-trace" d="M12 11v11M8 22h8" pathLength="100" style="stroke: var(--color-primary)"/>
            {{-- măslina --}}
            <circle cx="15.2" cy="5.2" r="1" style="fill: var(--color-primary); stroke: none"/>
        </svg>
        @else
        <svg class="rc-bell w-14 h-14" viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            {{-- corp + limbă: umplere discretă și contur gri; deasupra, conturul se colorează în tema aplicației --}}
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" pathLength="100" style="fill: var(--color-primary-soft); stroke: var(--color-border)"/>
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" pathLength="100" style="stroke: var(--color-border)"/>
            <path class="rc-bell-trace" d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" pathLength="100" style="stroke: var(--color-primary)"/>
            <path class="rc-bell-trace" d="M10.3 21a1.94 1.94 0 0 0 3.4 0" pathLength="100" style="stroke: var(--color-primary)"/>
        </svg>
        @endif
      </div>
    </div>
@endpersist
