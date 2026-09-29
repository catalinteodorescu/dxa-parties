@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', '.').'%';
@endphp
<div class="max-w-3xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">Recepție · Statistici</h2>
        <p class="mt-1 text-sm text-ink-soft">Peste toate petrecerile — intrări, tokeni, casa de recepție.</p>
    </div>

    {{-- Intrări + % gratuite în timp --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Intrări</h3>
        <p class="mt-1 text-xs text-ink-soft">All-time, cronologic pe petrecere.</p>
        @if ($entries->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Nicio intrare înregistrată încă.</p>
        @else
            @php
                $n = $entries->count();
                $slot = $n <= 6 ? 84 : ($n <= 12 ? 60 : 44);
                $w = $n * $slot;
                $chartH = 120;
                $maxCount = max(1, $entries->max(fn ($r) => $r->attendance->count));
                $barW = 30;
                $nameOf = fn ($p) => \Illuminate\Support\Str::limit($p->name, 12, '');
            @endphp

            {{-- Intrări totale --}}
            @php $leftPad = 64; @endphp
            <div class="relative mt-3 overflow-x-auto" x-data="{ tip: null, tx: 0, ty: 0 }">
                <div x-show="tip" x-text="tip" x-bind:style="`left: ${tx + 12}px; top: ${ty + 12}px`" style="display: none" class="fixed z-50 pointer-events-none whitespace-nowrap rounded-lg bg-ink px-2 py-1 text-xs font-medium text-white shadow-lg"></div>
                <svg viewBox="-{{ $leftPad }} 0 {{ $w + $leftPad }} {{ $chartH + 70 }}" width="{{ $w + $leftPad }}" height="{{ $chartH + 70 }}" style="max-width: none" role="img" aria-label="Intrări pe petrecere">
                    <line x1="0" y1="{{ $chartH }}" x2="{{ $w }}" y2="{{ $chartH }}" style="stroke: var(--color-border)" stroke-width="1"/>
                    @foreach ($entries as $i => $row)
                        @php
                            $h = (float) $row->attendance->count / $maxCount * ($chartH - 8);
                            $cx = $i * $slot + $slot / 2;
                        @endphp
                        <rect x="{{ $cx - $barW / 2 }}" y="{{ $chartH - $h }}" width="{{ $barW }}" height="{{ $h }}" rx="3" style="fill: var(--color-primary); cursor: pointer"
                              @mouseenter="tip = {{ \Illuminate\Support\Js::from($row->party->name.' · '.$int($row->attendance->count).' intrări') }}"
                              @mousemove="tx = $event.clientX; ty = $event.clientY" @mouseleave="tip = null"></rect>
                        <text x="{{ $cx }}" y="{{ $chartH + 14 }}" text-anchor="end" font-size="11" style="fill: var(--color-ink-soft)" transform="rotate(-40 {{ $cx }} {{ $chartH + 14 }})">{{ $nameOf($row->party) }}</text>
                    @endforeach
                </svg>
            </div>
            <p class="mt-1 text-xs text-ink-soft">Intrări valabile per petrecere.</p>

            {{-- % gratuite --}}
            @php $chartH2 = 90; @endphp
            <div class="relative mt-4 overflow-x-auto" x-data="{ tip: null, tx: 0, ty: 0 }">
                <div x-show="tip" x-text="tip" x-bind:style="`left: ${tx + 12}px; top: ${ty + 12}px`" style="display: none" class="fixed z-50 pointer-events-none whitespace-nowrap rounded-lg bg-ink px-2 py-1 text-xs font-medium text-white shadow-lg"></div>
                <svg viewBox="-{{ $leftPad }} 0 {{ $w + $leftPad }} {{ $chartH2 + 70 }}" width="{{ $w + $leftPad }}" height="{{ $chartH2 + 70 }}" style="max-width: none" role="img" aria-label="Procent intrări gratuite pe petrecere">
                    <line x1="0" y1="{{ $chartH2 }}" x2="{{ $w }}" y2="{{ $chartH2 }}" style="stroke: var(--color-border)" stroke-width="1"/>
                    @foreach ($entries as $i => $row)
                        @php
                            $h = (float) $row->attendance->free_pct / 100 * ($chartH2 - 8);
                            $cx = $i * $slot + $slot / 2;
                        @endphp
                        <rect x="{{ $cx - $barW / 2 }}" y="{{ $chartH2 - $h }}" width="{{ $barW }}" height="{{ $h }}" rx="3" style="fill: var(--color-primary); cursor: pointer"
                              @mouseenter="tip = {{ \Illuminate\Support\Js::from($row->party->name.' · '.$pct($row->attendance->free_pct).' gratuite') }}"
                              @mousemove="tx = $event.clientX; ty = $event.clientY" @mouseleave="tip = null"></rect>
                        <text x="{{ $cx }}" y="{{ $chartH2 + 14 }}" text-anchor="end" font-size="11" style="fill: var(--color-ink-soft)" transform="rotate(-40 {{ $cx }} {{ $chartH2 + 14 }})">{{ $nameOf($row->party) }}</text>
                    @endforeach
                </svg>
            </div>
            <p class="mt-1 text-xs text-ink-soft">% intrări gratuite per petrecere (0–100%). Util pentru politica de reducere cu oră.</p>
        @endif
    </div>

    {{-- Metode de plată la intrări (din ele vin butoanele „Tot cu…” din recepție) --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Metode de plată la intrări</h3>
        <p class="mt-1 text-xs text-ink-soft">All-time, fără intrările anulate. Primele două apar ca butoane „Tot cu…” în recepție.</p>
        @if ($methodUsage->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Nicio plată înregistrată încă.</p>
        @else
            @php
                $labels = \App\Support\PaymentMethods::labels();
                $maxUses = max(1, $methodUsage->max('count'));
            @endphp
            <div class="mt-3 space-y-2.5">
                @foreach ($methodUsage as $row)
                    <div wire:key="mu-{{ $row->method }}">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium text-ink">{{ $labels[$row->method] ?? $row->method }}@if ($loop->index < 2) <span class="ml-1 text-[11px] font-normal text-primary">buton rapid</span>@endif</span>
                            <span class="text-ink-soft">{{ $int($row->count) }} plăți · {{ $money($row->amount) }} lei</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-bg overflow-hidden">
                            <div class="h-full rounded-full" style="width: {{ round($row->count / $maxUses * 100) }}%; background: var(--color-primary)"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Tokeni vânduți vs. încasați --}}
    <div class="{{ $card }} mb-4">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h3 class="text-sm font-semibold text-ink">Tokeni</h3>
                <p class="mt-1 text-xs text-ink-soft">Vânduți la recepție vs. încasați la bar.</p>
            </div>
            <x-select wire:model="period" live class="w-44"
                      :options="['all' => 'Tot istoricul', 'recent' => 'Ultimele 3 luni']" />
        </div>

        <div class="grid grid-cols-3 gap-3 mt-3">
            <div>
                <div class="text-[11px] text-ink-soft">Vânduți</div>
                <div class="text-xl font-semibold text-ink">{{ $int($tokensOverview->sold) }}</div>
            </div>
            <div>
                <div class="text-[11px] text-ink-soft">Încasați</div>
                <div class="text-xl font-semibold text-ink">{{ $int($tokensOverview->collected) }}</div>
            </div>
            <div>
                <div class="text-[11px] text-ink-soft">Diferență</div>
                <div class="text-xl font-semibold text-ink">{{ $int($tokensOverview->sold - $tokensOverview->collected) }}</div>
            </div>
        </div>

        @if ($tokensTimeline->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Niciun token vândut sau încasat în perioada selectată.</p>
        @else
            @php
                $n = $tokensTimeline->count();
                $slot = $n <= 6 ? 84 : ($n <= 12 ? 60 : 44);
                $w = $n * $slot;
                $chartH = 130;
                $maxT = max(1, $tokensTimeline->max(fn ($r) => max($r->totals->sold, $r->totals->collected)));
                // Doua bare (vanduti+incasati) per petrecere: latimea perechii (2*barW+2) trebuie sa
                // incapa in slot cu un mic gutter, altfel barele petrecerilor vecine se suprapun
                // (se intampla cu barW fix=24 cand sunt multe petreceri si slot scade la 44).
                $barW = (int) max(8, min(24, floor(($slot - 10) / 2)));
            @endphp
            @php $leftPad2 = 64; @endphp
            <div class="relative mt-4 overflow-x-auto" x-data="{ tip: null, tx: 0, ty: 0 }">
                <div x-show="tip" x-text="tip" x-bind:style="`left: ${tx + 12}px; top: ${ty + 12}px`" style="display: none" class="fixed z-50 pointer-events-none whitespace-nowrap rounded-lg bg-ink px-2 py-1 text-xs font-medium text-white shadow-lg"></div>
                <svg viewBox="-{{ $leftPad2 }} 0 {{ $w + $leftPad2 }} {{ $chartH + 70 }}" width="{{ $w + $leftPad2 }}" height="{{ $chartH + 70 }}" style="max-width: none" role="img" aria-label="Tokeni vânduți vs. încasați pe petrecere">
                    <line x1="0" y1="{{ $chartH }}" x2="{{ $w }}" y2="{{ $chartH }}" style="stroke: var(--color-border)" stroke-width="1"/>
                    @foreach ($tokensTimeline as $i => $row)
                        @php
                            $hs = (float) $row->totals->sold / $maxT * ($chartH - 8);
                            $hc = (float) $row->totals->collected / $maxT * ($chartH - 8);
                            $cx = $i * $slot + $slot / 2;
                            $xs = $cx - $barW - 1;
                            $xc = $cx + 1;
                        @endphp
                        <rect x="{{ $xs }}" y="{{ $chartH - $hs }}" width="{{ $barW }}" height="{{ $hs }}" rx="3" style="fill: var(--color-primary); cursor: pointer"
                              @mouseenter="tip = {{ \Illuminate\Support\Js::from($row->party->name.' · vânduți '.$int($row->totals->sold)) }}"
                              @mousemove="tx = $event.clientX; ty = $event.clientY" @mouseleave="tip = null"></rect>
                        <rect x="{{ $xc }}" y="{{ $chartH - $hc }}" width="{{ $barW }}" height="{{ $hc }}" rx="3" style="fill: var(--color-ink-soft); opacity: 0.4; cursor: pointer"
                              @mouseenter="tip = {{ \Illuminate\Support\Js::from($row->party->name.' · încasați '.$int($row->totals->collected)) }}"
                              @mousemove="tx = $event.clientX; ty = $event.clientY" @mouseleave="tip = null"></rect>
                        <text x="{{ $cx }}" y="{{ $chartH + 14 }}" text-anchor="end" font-size="11" style="fill: var(--color-ink-soft)" transform="rotate(-40 {{ $cx }} {{ $chartH + 14 }})">{{ \Illuminate\Support\Str::limit($row->party->name, 12, '') }}</text>
                    @endforeach
                </svg>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft">
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background: var(--color-primary)"></span>Vânduți</span>
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background: var(--color-ink-soft); opacity: 0.4"></span>Încasați</span>
            </div>
        @endif
    </div>

    {{-- Diferențe de casă --}}
    <div class="{{ $card }}">
        <h3 class="text-sm font-semibold text-ink">Diferențe de casă</h3>
        <p class="mt-1 text-xs text-ink-soft">All-time, cronologic — raportările de recepție finalizate. Evidențiate cu roșu cele cu diferență peste 20 lei.</p>
        @if ($cashDiff->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Nicio raportare de recepție finalizată încă.</p>
        @else
            @php
                $n = $cashDiff->count();
                $slot = $n <= 6 ? 84 : ($n <= 12 ? 60 : 44);
                $w = $n * $slot;
                $half = 70;
                $chartH = $half * 2;
                $maxAbs = max(1, $cashDiff->max(fn ($r) => abs($r->reception->cash_diff)));
                $barW = 30;
            @endphp
            @php $leftPad3 = 64; @endphp
            <div class="relative mt-3 overflow-x-auto" x-data="{ tip: null, tx: 0, ty: 0 }">
                <div x-show="tip" x-text="tip" x-bind:style="`left: ${tx + 12}px; top: ${ty + 12}px`" style="display: none" class="fixed z-50 pointer-events-none whitespace-nowrap rounded-lg bg-ink px-2 py-1 text-xs font-medium text-white shadow-lg"></div>
                <svg viewBox="-{{ $leftPad3 }} 0 {{ $w + $leftPad3 }} {{ $chartH + 70 }}" width="{{ $w + $leftPad3 }}" height="{{ $chartH + 70 }}" style="max-width: none" role="img" aria-label="Diferențe de casă pe petrecere">
                    <line x1="0" y1="{{ $half }}" x2="{{ $w }}" y2="{{ $half }}" style="stroke: var(--color-border)" stroke-width="1"/>
                    @foreach ($cashDiff as $i => $row)
                        @php
                            $diff = (float) $row->reception->cash_diff;
                            $h = abs($diff) / $maxAbs * ($half - 6);
                            $cx = $i * $slot + $slot / 2;
                            $color = $diff >= 0 ? 'var(--color-success)' : 'var(--color-danger)';
                            $y = $diff >= 0 ? $half - $h : $half;
                        @endphp
                        <rect x="{{ $cx - $barW / 2 }}" y="{{ $y }}" width="{{ $barW }}" height="{{ $h }}" rx="3" style="fill: {{ $color }}; cursor: pointer"
                              @mouseenter="tip = {{ \Illuminate\Support\Js::from($row->party->name.' · '.$money($diff).' lei') }}"
                              @mousemove="tx = $event.clientX; ty = $event.clientY" @mouseleave="tip = null"></rect>
                        <text x="{{ $cx }}" y="{{ $chartH + 14 }}" text-anchor="end" font-size="11" style="fill: var(--color-ink-soft)" transform="rotate(-40 {{ $cx }} {{ $chartH + 14 }})">{{ \Illuminate\Support\Str::limit($row->party->name, 12, '') }}</text>
                    @endforeach
                </svg>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft mb-3">
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background: var(--color-success)"></span>Surplus</span>
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background: var(--color-danger)"></span>Lipsă</span>
                <span>Linia de zero la mijloc</span>
            </div>

            <div class="space-y-1.5">
                @foreach ($cashDiff as $row)
                    @php $flag = abs((float) $row->reception->cash_diff) > 20; @endphp
                    <a wire:key="cd-{{ $row->party->id }}" href="{{ route('admin.parties.stats', $row->party) }}" wire:navigate
                       class="flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2 transition-colors
                              {{ $flag ? 'border-danger/40 bg-danger/5 hover:border-danger/60' : 'border-border hover:border-primary/40' }}">
                        <span class="text-sm font-medium text-ink">{{ $row->party->name }}</span>
                        <span class="text-xs {{ $flag ? 'text-danger font-medium' : 'text-ink-soft' }}">
                            {{ $money($row->reception->cash_diff) }} lei
                            @if ($row->reception->reports > 1)
                                · {{ $int($row->reception->reports) }} raportări
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
