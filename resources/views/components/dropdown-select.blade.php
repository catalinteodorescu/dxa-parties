@props([
    'path',                 // calea Livewire, ex. "guests.0.style" sau "start_time"
    'options' => [],        // [valoare => etichetă]
    'selected' => null,     // valoarea curentă (randată pe server)
    'placeholder' => 'Alege…',
])

@php
    $sel = $selected === null ? '' : (string) $selected;
    $currentLabel = array_key_exists($sel, $options) ? $options[$sel] : null;
@endphp

<div
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape="open = false"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <button type="button" @click="open = ! open"
            class="w-full flex items-center justify-between gap-2 rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm focus:outline-none"
            :class="open ? 'ring-2 ring-primary/40 border-primary' : ''">
        <span class="{{ $currentLabel === null ? 'text-ink-soft/60' : 'text-ink' }} truncate">{{ $currentLabel ?? $placeholder }}</span>
        <svg class="w-4 h-4 shrink-0 text-ink-soft transition-transform" :class="open ? 'rotate-180' : ''"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-surface shadow-lg py-1 max-h-60 overflow-auto">
        @foreach ($options as $val => $label)
            <button type="button"
                    wire:click="$set('{{ $path }}', '{{ $val }}')"
                    @click="open = false"
                    @class([
                        'w-full flex items-center justify-between gap-2 px-3 py-2 text-sm text-left hover:bg-bg',
                        'text-primary font-medium' => (string) $val === $sel,
                        'text-ink' => (string) $val !== $sel,
                    ])>
                <span class="truncate">{{ $label }}</span>
                @if ((string) $val === $sel)
                    <svg class="w-4 h-4 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                @endif
            </button>
        @endforeach
    </div>
</div>
