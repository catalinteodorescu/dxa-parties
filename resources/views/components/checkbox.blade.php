{{--
    DXA: adaugat. Checkbox custom (în loc de cel nativ): pătrat rotunjit, bifă albă pe fundal terracotta (culoarea temei).
    Atributele wire:* / name / value / disabled ajung pe <input> (ascuns vizual, dar accesibil de la tastatură).
    Utilizare: <x-checkbox wire:model="accessBar">Aplicație bar</x-checkbox>
--}}
<label {{ $attributes->only('class')->merge(['class' => 'inline-flex items-center gap-2.5 cursor-pointer select-none']) }}>
    <input type="checkbox" {{ $attributes->except('class') }} class="peer sr-only">
    <span class="w-5 h-5 shrink-0 rounded-md border border-border bg-white flex items-center justify-center transition-colors
                 peer-checked:bg-primary peer-checked:border-primary peer-checked:[&_svg]:opacity-100
                 peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 peer-disabled:opacity-50">
        <svg class="w-3.5 h-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </span>
    <span class="text-sm text-ink">{{ $slot }}</span>
</label>
