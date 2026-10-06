@props([
    'from',                     // proprietatea Livewire cu data de început (Y-m-d), ex. "dateFrom"
    'to',                       // proprietatea Livewire cu data de sfârșit (Y-m-d), ex. "dateTo"
    'placeholder' => 'Alege intervalul',
])

{{-- DXA: adaugat (runda 42). Un singur câmp pentru interval de date: calendar propriu (fără librării), luni începând cu luni,
     primul click = început, al doilea = sfârșit (inversate automat dacă sunt date invers), scurtături și „Șterge”.
     Ambele proprietăți se scriu odată, într-o singură cerere Livewire. --}}
<div
    x-data="{
        open: false,
        from: $wire.$entangle('{{ $from }}', true),
        to: $wire.$entangle('{{ $to }}', true),
        view: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
        draft: '',
        hover: '',
        months: ['Ianuarie','Februarie','Martie','Aprilie','Mai','Iunie','Iulie','August','Septembrie','Octombrie','Noiembrie','Decembrie'],
        pad(n) { return String(n).padStart(2, '0'); },
        iso(d) { return d.getFullYear() + '-' + this.pad(d.getMonth() + 1) + '-' + this.pad(d.getDate()); },
        parse(s) { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); },
        fmt(s) { return s ? s.slice(8, 10) + '.' + s.slice(5, 7) + '.' + s.slice(0, 4) : ''; },
        get label() {
            if (! this.from && ! this.to) return '';
            if (this.from && this.to) return this.from === this.to ? this.fmt(this.from) : this.fmt(this.from) + ' – ' + this.fmt(this.to);
            return this.from ? 'din ' + this.fmt(this.from) : 'până la ' + this.fmt(this.to);
        },
        get title() { return this.months[this.view.getMonth()] + ' ' + this.view.getFullYear(); },
        get cells() {
            const y = this.view.getFullYear(), m = this.view.getMonth();
            const offset = (new Date(y, m, 1).getDay() + 6) % 7;
            return Array.from({ length: 42 }, (_, i) => {
                const d = new Date(y, m, 1 - offset + i);
                return { iso: this.iso(d), day: d.getDate(), inMonth: d.getMonth() === m };
            });
        },
        get span() {
            if (this.draft) { const h = this.hover || this.draft; return this.draft <= h ? [this.draft, h] : [h, this.draft]; }
            return [this.from || '', this.to || this.from || ''];
        },
        inSpan(i) { const [a, b] = this.span; return a && i >= a && i <= b; },
        isEdge(i) { const [a, b] = this.span; return i === a || i === b; },
        toggle() {
            if (this.open) { this.open = false; return; }
            this.draft = ''; this.hover = '';
            const base = this.from ? this.parse(this.from) : (this.to ? this.parse(this.to) : new Date());
            this.view = new Date(base.getFullYear(), base.getMonth(), 1);
            this.open = true;
        },
        step(n) { this.view = new Date(this.view.getFullYear(), this.view.getMonth() + n, 1); },
        pick(i) {
            if (! this.draft) { this.draft = i; this.hover = i; return; }
            const a = this.draft <= i ? this.draft : i, b = this.draft <= i ? i : this.draft;
            this.draft = ''; this.hover = '';
            this.apply(a, b);
        },
        apply(a, b) { this.from = a; this.to = b; this.open = false; },
        preset(kind) {
            const t = new Date(); const today = new Date(t.getFullYear(), t.getMonth(), t.getDate());
            if (kind === 'today') return this.apply(this.iso(today), this.iso(today));
            if (kind === 'week') return this.apply(this.iso(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 6)), this.iso(today));
            if (kind === 'month') return this.apply(this.iso(new Date(today.getFullYear(), today.getMonth(), 1)), this.iso(new Date(today.getFullYear(), today.getMonth() + 1, 0)));
            return this.apply(this.iso(new Date(today.getFullYear(), today.getMonth() - 1, 1)), this.iso(new Date(today.getFullYear(), today.getMonth(), 0)));
        },
        clear() { this.from = ''; this.to = ''; this.draft = ''; this.open = false; },
    }"
    @click.outside="open = false"
    @keydown.escape="open = false"
    data-date-range
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <button type="button" @click="toggle()"
            class="w-full flex items-center justify-between gap-2 rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm focus:outline-none"
            :class="open ? 'ring-2 ring-primary/40 border-primary' : ''">
        <span class="truncate" :class="label ? 'text-ink' : 'text-ink-soft/60'" x-text="label || @js($placeholder)"></span>
        <svg class="w-4 h-4 shrink-0 text-ink-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/></svg>
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute left-0 z-30 mt-1 rounded-xl border border-border bg-surface p-3 shadow-lg"
         style="width: 19rem; max-width: calc(100vw - 2rem)">
        <div class="flex items-center justify-between mb-2">
            <button type="button" @click="step(-1)" class="rounded-lg text-ink-soft hover:bg-bg" style="width: 2rem; height: 2rem" aria-label="Luna anterioară">‹</button>
            <span class="text-sm font-semibold text-ink" x-text="title"></span>
            <button type="button" @click="step(1)" class="rounded-lg text-ink-soft hover:bg-bg" style="width: 2rem; height: 2rem" aria-label="Luna următoare">›</button>
        </div>

        <div class="text-center text-[11px] text-ink-soft/70 mb-1" style="display: grid; grid-template-columns: repeat(7, 1fr)">
            <template x-for="d in ['L','M','M','J','V','S','D']"><span x-text="d"></span></template>
        </div>
        <div style="display: grid; grid-template-columns: repeat(7, 1fr); row-gap: 2px" @mouseleave="hover = draft">
            <template x-for="c in cells" :key="c.iso">
                <button type="button" @click="pick(c.iso)" @mouseenter="if (draft) hover = c.iso"
                        :data-day="c.iso"
                        class="text-sm" style="height: 2.25rem"
                        :class="[
                            isEdge(c.iso) ? 'bg-primary text-white font-semibold rounded-lg' : (inSpan(c.iso) ? 'bg-primary-soft text-ink' : 'rounded-lg hover:bg-bg'),
                            ! c.inMonth && ! inSpan(c.iso) ? 'text-ink-soft/40' : '',
                        ]"
                        x-text="c.day"></button>
            </template>
        </div>

        <div class="mt-3 flex flex-wrap gap-1.5 border-t border-border pt-3">
            <button type="button" @click="preset('today')" class="rounded-full border border-border px-2.5 py-1 text-xs text-ink-soft hover:text-ink">Azi</button>
            <button type="button" @click="preset('week')" class="rounded-full border border-border px-2.5 py-1 text-xs text-ink-soft hover:text-ink">Ultimele 7 zile</button>
            <button type="button" @click="preset('month')" class="rounded-full border border-border px-2.5 py-1 text-xs text-ink-soft hover:text-ink">Luna aceasta</button>
            <button type="button" @click="preset('last')" class="rounded-full border border-border px-2.5 py-1 text-xs text-ink-soft hover:text-ink">Luna trecută</button>
            <button type="button" @click="clear()" class="ml-auto px-2 py-1 text-xs text-ink-soft hover:text-ink">Șterge</button>
        </div>
    </div>
</div>
