@props([
    'path',              // calea Livewire in care se scrie costul/unitate calculat
    'unit' => '',        // unitatea de baza (ml, g etc.), doar cosmetic
    'defaultQty' => null, // cantitatea/bucata cunoscuta (din ambalajul de referinta), daca exista
    'hint' => true,       // afiseaza "(pt. X unit/bucata)" langa link; dezactivat cand spatiul e ingust (ex. coloana de cost din Raportari)
    'triggerClass' => '', // clasa (de regula latimea, ex. w-40) pusa pe linkul-declansator, ca sa cada exact sub inputul de cost de pe randul de deasupra
])

{{--
    Complementul lui x-qty-helper: aici NU cunosti costul pe unitate, ci doar
    pretul platit pe ambalaj (ex. "129 lei sticla de 700ml") - imparte automat
    si scrie costul/unitate rezultat. Daca produsul are deja un ambalaj de
    referinta salvat, cantitatea/bucata vine precompletata - ramane doar sa
    introduci pretul.

    Aliniere pe coloana: randul de mai jos oglindeste latimile coloanelor din
    randul de deasupra (prefix = spatii invizibile pt. coloanele dinaintea
    celei de cost, triggerClass = latimea coloanei de cost, suffix = spatiu
    invizibil pt. coloanele de dupa, ex. butonul de sters) - asa linkul START-eaza
    exact sub inputul de cost, indiferent cat de lat e textul lui. Cand
    triggerClass e setat, butonul devine block+w-full si text-right - altfel
    <button> are text-align:center implicit din browser, care pe text lung
    (impins pe 2 randuri) arata "centrat" in coloana ingusta in loc de aliniat
    la marginea din dreapta a inputului. Panoul extins insa NU e prins in acea
    latime - e copil direct al radacinii (care ramane un bloc normal, pe toata
    latimea sectiunii).

    "open" porneste MEREU pe false — vezi explicatia din qty-helper.blade.php
    (remount Livewire dupa $wire.set ar redeschide panoul daca ar porni pe true).
--}}
<div {{ $attributes->merge(['class' => 'mt-1.5']) }} x-data="{ open: false, price: '', qty: {{ $defaultQty ? (float) $defaultQty : "''" }} }">
    <div class="flex flex-wrap items-start gap-2">
        {{ $prefix ?? '' }}
        <div class="{{ $triggerClass }}">
            <button type="button" @click="open = !open" class="text-xs text-primary hover:underline {{ $triggerClass ? 'block w-full text-right' : '' }}">
                <span x-show="!open">Calculează din preț/bucată @if($hint && $defaultQty) (pt. {{ rtrim(rtrim(number_format((float) $defaultQty, 3, ',', '.'), '0'), ',') }}{{ $unit ? ' '.$unit : '' }}/bucată) @endif</span>
                <span x-show="open" x-cloak>Ascunde calculul</span>
            </button>
        </div>
        {{ $suffix ?? '' }}
    </div>

    <div x-show="open" x-cloak class="mt-1.5 rounded-lg border border-border bg-bg p-3">
        <div class="flex items-end gap-2.5">
            <div class="flex-1">
                <label class="block text-[11px] text-ink-soft mb-1">Preț/bucată (lei)</label>
                <input type="number" min="0" step="0.01" x-model.number="price"
                       class="w-full rounded-md border border-border bg-white px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>
            <span class="pb-2.5 text-ink-soft/70 text-sm shrink-0">÷</span>
            <div class="flex-1">
                <label class="block text-[11px] text-ink-soft mb-1">Cant./bucată @if($unit) ({{ $unit }}) @endif</label>
                <input type="number" min="0" step="0.001" x-model.number="qty"
                       class="w-full rounded-md border border-border bg-white px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>
        </div>

        <div class="mt-2.5 flex items-center justify-between gap-3 border-t border-border pt-2.5">
            <span class="text-sm text-ink-soft">
                Cost: <span class="font-semibold text-ink" x-text="(price && qty) ? (Math.round((price / qty) * 10000) / 10000) : '—'"></span>
                @if($unit) <span class="text-ink-soft">lei/{{ $unit }}</span> @endif
            </span>
            <div class="flex items-center gap-3 shrink-0">
                <button type="button" class="text-xs font-medium text-ink-soft hover:text-ink"
                        @click="open = false; price = ''; qty = {{ $defaultQty ? (float) $defaultQty : "''" }};">
                    Anulează
                </button>
                <button type="button" :disabled="!price || !qty"
                        class="inline-flex items-center gap-1 rounded-md bg-primary text-white text-xs font-medium px-3 py-1.5 disabled:opacity-40 disabled:cursor-not-allowed"
                        @click="$wire.set('{{ $path }}', String(Math.round((price / qty) * 10000) / 10000)); open = false; price = ''; qty = {{ $defaultQty ? (float) $defaultQty : "''" }};">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Aplică
                </button>
            </div>
        </div>
    </div>
</div>
