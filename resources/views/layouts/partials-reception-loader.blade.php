{{--
    DXA: adaugat (PWA Recepție / Bar). Loader între ecrane: un pahar de martini care se leagănă ușor („noroc”), cu marginile
    colorate progresiv în culoarea temei aplicației. Apare (după ~150 ms, ca să nu clipească la navigări rapide) când Livewire
    schimbă ecranul (wire:navigate) și dispare când noul ecran e gata. @persist îl păstrează între ecrane, deci nu se resetează.
--}}
<style>
    .rc-glass { transform-origin: 50% 92%; animation: rc-cheers 1.3s ease-in-out infinite; }
    .rc-glass-trace { stroke-dasharray: 100; stroke-dashoffset: 100; animation: rc-trace 1.4s ease-in-out infinite; }
    @keyframes rc-cheers {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(-9deg); } 50% { transform: rotate(0deg); } 75% { transform: rotate(9deg); }
    }
    @keyframes rc-trace { 0% { stroke-dashoffset: 100; } 55%, 100% { stroke-dashoffset: 0; } }
    @media (prefers-reduced-motion: reduce) { .rc-glass, .rc-glass-trace { animation: none; stroke-dashoffset: 0; } }
</style>
@persist('reception-loader')
    <div x-data="{ show: false, timer: null }"
         x-init="document.addEventListener('livewire:navigate', () => { clearTimeout(timer); timer = setTimeout(() => show = true, 150); });
                 document.addEventListener('livewire:navigated', () => { clearTimeout(timer); show = false; });"
         x-show="show" x-cloak x-transition.opacity.duration.150ms
         class="fixed inset-0 z-50 flex items-center justify-center bg-bg/85 backdrop-blur-sm" aria-hidden="true">
        <svg class="rc-glass w-16 h-16" viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            {{-- pahar: umplere discretă și contur gri; deasupra, conturul se colorează în tema aplicației --}}
            <path d="m19 3-7 8-7-8Z" pathLength="100" style="fill: var(--color-primary-soft); stroke: var(--color-border)"/>
            <path d="M12 11v11M8 22h8" pathLength="100" style="stroke: var(--color-border)"/>
            <path class="rc-glass-trace" d="m19 3-7 8-7-8Z" pathLength="100" style="stroke: var(--color-primary)"/>
            <path class="rc-glass-trace" d="M12 11v11M8 22h8" pathLength="100" style="stroke: var(--color-primary)"/>
            {{-- măslina --}}
            <circle cx="15.2" cy="5.2" r="1" style="fill: var(--color-primary); stroke: none"/>
        </svg>
    </div>
@endpersist
