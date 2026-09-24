@props([
    'path',                 // calea Livewire, ex. "guests.0.style" sau "start_time"
    'options' => [],        // [valoare => etichetă]
    'selected' => null,     // valoarea curentă (randată pe server)
    'placeholder' => 'Alege…',
    'searchable' => null,   // null = auto (căutare de la SEARCH_THRESHOLD opțiuni); true/false = forțat
])

@php
    $sel = $selected === null ? '' : (string) $selected;
    $currentLabel = array_key_exists($sel, $options) ? $options[$sel] : null;

    // DXA: adaugat — cautare automata cand lista are multe optiuni (prag 6),
    // sau fortata explicit prin prop.
    $searchThreshold = 6;
    $isSearchable = $searchable === null ? count($options) >= $searchThreshold : (bool) $searchable;
@endphp

<div
    x-data="{
        open: false,
        openUp: false,
        query: '',
        isSearchable: {{ $isSearchable ? 'true' : 'false' }},
        labels: @js(array_values($options)),
        normalize(s) {
            return (s ?? '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        },
        matches(label) {
            return ! this.query || this.normalize(label).includes(this.normalize(this.query));
        },
        anyMatch() { return this.labels.some(l => this.matches(l)); },
        close() { this.open = false; this.query = ''; },
        toggle() {
            if (this.open) { this.close(); return; }
            this.open = true;
            this.$nextTick(() => {
                const rect = this.$refs.trigger.getBoundingClientRect();
                const estimated = Math.min(this.labels.length, 6) * 40 + (this.isSearchable ? 44 : 0) + 16;
                this.openUp = (window.innerHeight - rect.bottom) < estimated && rect.top > estimated;
                if (this.isSearchable) this.$nextTick(() => this.$refs.search && this.$refs.search.focus());
            });
        },
    }"
    @click.outside="close()"
    @keydown.escape="close()"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <button type="button" x-ref="trigger" @click="toggle()"
            class="w-full flex items-center justify-between gap-2 rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm focus:outline-none"
            :class="open ? 'ring-2 ring-primary/40 border-primary' : ''">
        <span class="{{ $currentLabel === null ? 'text-ink-soft/60' : 'text-ink' }} truncate">{{ $currentLabel ?? $placeholder }}</span>
        <svg class="w-4 h-4 shrink-0 text-ink-soft transition-transform" :class="open ? 'rotate-180' : ''"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute z-30 w-full rounded-lg border border-border bg-surface shadow-lg py-1"
         :class="openUp ? 'bottom-full mb-1' : 'top-full mt-1'">
        <div x-show="isSearchable" class="px-1.5 pb-1.5 mb-1 border-b border-border" @click.stop>
            <input type="text" x-model="query" x-ref="search" placeholder="Caută…"
                   class="w-full rounded-md border border-border px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40" />
        </div>
        <div class="max-h-60 overflow-auto">
            @foreach ($options as $val => $label)
                <button type="button"
                        wire:click="$set('{{ $path }}', '{{ $val }}')"
                        x-show="matches(@js($label))"
                        @click="close()"
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
            <div x-show="isSearchable && query && ! anyMatch()" class="px-3 py-2 text-sm text-ink-soft">Niciun rezultat.</div>
        </div>
    </div>
</div>
