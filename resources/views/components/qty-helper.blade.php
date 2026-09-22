@props([
    'path',            // calea Livewire (wire model) in care se scrie rezultatul, ex. "initial_qty"
    'unit' => '',      // eticheta unitatii, doar cosmetic (ex. "ml")
    'defaultSize' => null, // daca produsul are deja un ambalaj de referinta definit, preia cantitatea/bucata de acolo
    'triggerClass' => '', // clasa (de regula latimea, ex. w-28) pusa pe linkul-declansator, ca sa cada exact sub inputul de cantitate de pe randul de deasupra
])

{{--
    Ajutor de calcul, fara nicio persistenta in DB — rezolva cazul "stiu ca-mi
    trebuie 6 sticle a cate 2 litri" fara sa calculezi tu mL total. Pur Alpine
    (client-side), scrie rezultatul in campul Livewire indicat prin $set la
    apasarea "Aplica". Refolosibil oriunde se introduce o cantitate (stoc
    initial, si mai tarziu Raportare/Necesare). Daca produsul are deja un
    ambalaj de referinta salvat (ex. "sticla 700ml"), cantitatea/bucata vine
    precompletata - ramane doar sa introduci numarul de bucati.

    Aliniere pe coloana: vezi explicatia din cost-helper.blade.php — prefix/
    triggerClass/suffix oglindesc latimile coloanelor din randul de deasupra,
    ca linkul sa cada exact sub inputul de cantitate; cand triggerClass e
    setat, butonul devine block+w-full si text-right (altfel <button> are
    text-align:center implicit din browser, vizibil "centrat" cand textul se
    rupe pe 2 randuri intr-o coloana ingusta). Panoul extins ramane insa pe
    toata latimea (copil direct al radacinii, neconstrans).

    IMPORTANT: "open" porneste MEREU pe false, indiferent de defaultSize. Daca
    ar porni pe true cand exista un ambalaj, un remount Livewire dupa $wire.set
    (declansat chiar de butonul Aplica) re-evalueaza acest x-data de la zero si
    redeschide panoul, anuland "open=false" pe care tocmai l-a setat click-ul -
    exact bug-ul "nu se ascunde dupa Aplica". Cu open mereu false ca stare de
    baza, inchiderea ramane corecta indiferent de remount.
--}}
<div {{ $attributes->merge(['class' => 'mt-1.5']) }} x-data="{ open: false, count: '', size: {{ $defaultSize ? (float) $defaultSize : "''" }} }">
    <div class="flex flex-wrap items-start gap-2">
        {{ $prefix ?? '' }}
        <div class="{{ $triggerClass }}">
            <button type="button" @click="open = !open" class="text-xs text-primary hover:underline {{ $triggerClass ? 'block w-full text-right' : '' }}">
                <span x-show="!open">Calculează din bucăți/sticle @if($defaultSize) (ai deja {{ rtrim(rtrim(number_format((float) $defaultSize, 3, ',', '.'), '0'), ',') }}{{ $unit ? ' '.$unit : '' }}/bucată) @endif</span>
                <span x-show="open" x-cloak>Ascunde calculul</span>
            </button>
        </div>
        {{ $suffix ?? '' }}
    </div>

    <div x-show="open" x-cloak class="mt-1.5 rounded-lg border border-border bg-bg p-3">
        <div class="flex items-end gap-2.5">
            <div class="flex-1">
                <label class="block text-[11px] text-ink-soft mb-1">Nr. bucăți/sticle</label>
                <input type="number" min="0" step="1" x-model.number="count"
                       class="w-full rounded-md border border-border bg-white px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>
            <span class="pb-2.5 text-ink-soft/70 text-sm shrink-0">×</span>
            <div class="flex-1">
                <label class="block text-[11px] text-ink-soft mb-1">Cant./bucată @if($unit) ({{ $unit }}) @endif</label>
                <input type="number" min="0" step="0.001" x-model.number="size"
                       class="w-full rounded-md border border-border bg-white px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>
        </div>

        <div class="mt-2.5 flex items-center justify-between gap-3 border-t border-border pt-2.5">
            <span class="text-sm text-ink-soft">
                Total: <span class="font-semibold text-ink" x-text="(count && size) ? (Math.round(count * size * 1000) / 1000) : '—'"></span>
                @if($unit) <span class="text-ink-soft">{{ $unit }}</span> @endif
            </span>
            <div class="flex items-center gap-3 shrink-0">
                <button type="button" class="text-xs font-medium text-ink-soft hover:text-ink"
                        @click="open = false; count = ''; size = {{ $defaultSize ? (float) $defaultSize : "''" }};">
                    Anulează
                </button>
                <button type="button" :disabled="!count || !size"
                        class="inline-flex items-center gap-1 rounded-md bg-primary text-white text-xs font-medium px-3 py-1.5 disabled:opacity-40 disabled:cursor-not-allowed"
                        @click="$wire.set('{{ $path }}', String(Math.round(count * size * 1000) / 1000)); open = false; count = ''; size = {{ $defaultSize ? (float) $defaultSize : "''" }};">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Aplică
                </button>
            </div>
        </div>
    </div>
</div>
