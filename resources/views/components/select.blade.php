@props([
    'options' => [],           // [valoare => etichetă]
    'placeholder' => 'Alege…',
    'live' => false,           // true = actualizează Livewire imediat (pentru filtre)
    'searchable' => null,      // null = auto (căutare de la SEARCH_THRESHOLD opțiuni); true/false = forțat
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

    // DXA: adaugat — cautare automata cand lista are multe optiuni (prag 6),
    // sau fortata explicit prin prop.
    $searchThreshold = 6;
    $isSearchable = $searchable === null ? count($optionPairs) >= $searchThreshold : (bool) $searchable;
@endphp

<div
    x-data="{
        open: false,
        openUp: false,
        query: '',
        isSearchable: {{ $isSearchable ? 'true' : 'false' }},
        value: $wire.entangle(@js($model)){{ $live ? '.live' : '' }},
        options: @js($optionPairs),
        labelFor(v) {
            const found = this.options.find(o => o.value === String(v));
            return found ? found.label : @js($placeholder);
        },
        normalize(s) {
            return (s ?? '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        },
        filtered() {
            if (! this.isSearchable || ! this.query) return this.options;
            const q = this.normalize(this.query);
            return this.options.filter(o => this.normalize(o.label).includes(q));
        },
        close() { this.open = false; this.query = ''; },
        toggle() {
            if (this.open) { this.close(); return; }
            this.open = true;
            this.$nextTick(() => {
                const rect = this.$refs.trigger.getBoundingClientRect();
                const estimated = Math.min(this.options.length, 6) * 40 + (this.isSearchable ? 44 : 0) + 16;
                this.openUp = (window.innerHeight - rect.bottom) < estimated && rect.top > estimated;
                if (this.isSearchable) this.$nextTick(() => this.$refs.search && this.$refs.search.focus());
            });
        },
    }"
    @click.outside="close()"
    @keydown.escape="close()"
    {{ $attributes->except('wire:model')->merge(['class' => 'relative']) }}
>
    <button type="button" x-ref="trigger" @click="toggle()"
            class="w-full flex items-center justify-between gap-2 rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm focus:outline-none"
            :class="open ? 'ring-2 ring-primary/40 border-primary' : ''">
        <span x-text="labelFor(value)" :class="value ? 'text-ink' : 'text-ink-soft/60'" class="min-w-0 truncate text-left"></span>
        <svg class="w-4 h-4 shrink-0 text-ink-soft transition-transform" :class="open ? 'rotate-180' : ''"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute z-20 w-full rounded-lg border border-border bg-surface shadow-lg py-1"
         :class="openUp ? 'bottom-full mb-1' : 'top-full mt-1'">
        <div x-show="isSearchable" class="px-1.5 pb-1.5 mb-1 border-b border-border" @click.stop>
            <input type="text" x-model="query" x-ref="search" placeholder="Caută…"
                   class="w-full rounded-md border border-border px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40" />
        </div>
        <div class="max-h-60 overflow-auto">
            <template x-for="opt in filtered()" :key="opt.value">
                <button type="button" @click="value = opt.value; close()"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm text-left hover:bg-bg"
                        :class="String(value) === opt.value ? 'text-primary font-medium' : 'text-ink'">
                    <span x-text="opt.label" class="truncate"></span>
                    <svg x-show="String(value) === opt.value" class="w-4 h-4 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </button>
            </template>
            <div x-show="isSearchable && query && filtered().length === 0" class="px-3 py-2 text-sm text-ink-soft">Niciun rezultat.</div>
        </div>
    </div>
</div>
