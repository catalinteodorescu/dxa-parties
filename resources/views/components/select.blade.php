@props([
    'options' => [],           // [valoare => etichetă]
    'placeholder' => 'Alege…',
    'live' => false,           // true = actualizează Livewire imediat (pentru filtre)
])

@php
    $model = $attributes->wire('model')->value();

    // Lista de perechi [value,label], NU un obiect JS — ca sa pastram ordinea
    // exacta din PHP. Un obiect JS isi reordoneaza singur cheile numerice
    // (id-uri) inaintea celor text, indiferent de ordinea de inserare.
    $optionPairs = array_map(
        fn ($k, $v) => ['value' => (string) $k, 'label' => $v],
        array_keys($options),
        array_values($options),
    );
@endphp

<div
    x-data="{
        open: false,
        value: $wire.entangle(@js($model)){{ $live ? '.live' : '' }},
        options: @js($optionPairs),
        labelFor(v) {
            const found = this.options.find(o => o.value === String(v));
            return found ? found.label : @js($placeholder);
        },
    }"
    @click.outside="open = false"
    @keydown.escape="open = false"
    {{ $attributes->except('wire:model')->merge(['class' => 'relative']) }}
>
    <button type="button" @click="open = ! open"
            class="w-full flex items-center justify-between gap-2 rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm focus:outline-none"
            :class="open ? 'ring-2 ring-primary/40 border-primary' : ''">
        <span x-text="labelFor(value)" :class="value ? 'text-ink' : 'text-ink-soft/60'" class="min-w-0 truncate text-left"></span>
        <svg class="w-4 h-4 shrink-0 text-ink-soft transition-transform" :class="open ? 'rotate-180' : ''"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute z-20 mt-1 w-full rounded-lg border border-border bg-surface shadow-lg py-1 max-h-60 overflow-auto">
        <template x-for="opt in options" :key="opt.value">
            <button type="button" @click="value = opt.value; open = false"
                    class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm text-left hover:bg-bg"
                    :class="String(value) === opt.value ? 'text-primary font-medium' : 'text-ink'">
                <span x-text="opt.label"></span>
                <svg x-show="String(value) === opt.value" class="w-4 h-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </button>
        </template>
    </div>
</div>
