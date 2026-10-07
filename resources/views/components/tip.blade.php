{{--
    DXA: adaugat (runda 48b). Tooltip custom (ca cel din x-btn, dar cu text pe mai multe rânduri) pentru elemente mici fără buton: badge-uri din meniu etc.
    Apare la hover / focus, sub element, aliniat la dreapta (ca să încapă în sidebar). Utilizare: <x-tip text="…"> …elementul… </x-tip>
--}}
@props(['text'])

<span class="relative inline-flex" x-data="{ tip: false }"
      @mouseenter="tip = true" @mouseleave="tip = false"
      @focusin="tip = true" @focusout="tip = false">
    {{ $slot }}
    <span x-show="tip" x-cloak x-transition.opacity style="display: none"
          class="pointer-events-none absolute top-full right-0 mt-2 z-50 w-52 rounded-md bg-ink text-white text-xs font-normal normal-case tracking-normal leading-snug text-left px-2.5 py-2 shadow-lg">
        {{ $text }}
        <span class="absolute bottom-full right-3 border-4 border-transparent border-b-ink"></span>
    </span>
</span>
