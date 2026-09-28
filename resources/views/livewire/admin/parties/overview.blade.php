@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', '.').'%';
    $barPct = fn ($value, $max) => $max > 0 ? max(2, round((float) $value / $max * 100, 1)) : 0;
@endphp
<div class="max-w-3xl">
    <div class="mb-5 flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-xl font-semibold text-ink">Petreceri · Statistici</h2>
            <p class="mt-1 text-sm text-ink-soft">Privire de ansamblu, all-time — totaluri, frecvență, topuri.</p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('admin.bar.stats') }}" wire:navigate class="rounded-lg border border-border px-3 py-1.5 font-medium text-ink-soft hover:border-primary/40 hover:text-primary transition-colors">Bar → Statistici</a>
            <a href="{{ route('admin.reception.stats') }}" wire:navigate class="rounded-lg border border-border px-3 py-1.5 font-medium text-ink-soft hover:border-primary/40 hover:text-primary transition-colors">Recepție → Statistici</a>
        </div>
    </div>

    @if ($totals->count === 0)
        <div class="{{ $card }}">
            <p class="text-sm text-ink-soft">Nicio petrecere creată încă.</p>
        </div>
    @else
        {{-- Totaluri --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Petreceri</div>
                <div class="text-2xl font-semibold text-ink">{{ $int($totals->count) }}</div>
                <p class="mt-0.5 text-[11px] text-ink-soft">{{ $int($totals->basic) }} simple · {{ $int($totals->festival) }} festival</p>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Venit bar</div>
                <div class="text-2xl font-semibold text-ink">{{ $money($summary->revenue) }}</div>
                <p class="mt-0.5 text-[11px] text-ink-soft">lei, all-time</p>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Profit bar</div>
                <div class="text-2xl font-semibold text-ink">{{ $money($summary->profit) }}</div>
                <p class="mt-0.5 text-[11px] text-ink-soft">lei, all-time</p>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Intrări totale</div>
                <div class="text-2xl font-semibold text-ink">{{ $int($summary->entries_total) }}</div>
                <p class="mt-0.5 text-[11px] text-ink-soft">medie {{ $summary->entries_avg === null ? '—' : $int($summary->entries_avg) }} / petrecere</p>
            </div>
        </div>

        {{-- Frecvență --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Frecvență</h3>
            <p class="mt-1 text-xs text-ink-soft">Petreceri pe lună calendaristică, cronologic.</p>
            @php
                $n = $frequency->count();
                $slot = $n <= 6 ? 84 : ($n <= 12 ? 60 : 44);
                $w = $n * $slot;
                $chartH = 120;
                $maxF = max(1, $frequency->max('count'));
                $barW = 30;
            @endphp
            @php $leftPad = 64; @endphp
            <div class="relative mt-3 overflow-x-auto" x-data="{ tip: null, tx: 0, ty: 0 }">
                <div x-show="tip" x-text="tip" x-bind:style="`left: ${tx + 12}px; top: ${ty + 12}px`" style="display: none" class="fixed z-50 pointer-events-none whitespace-nowrap rounded-lg bg-ink px-2 py-1 text-xs font-medium text-white shadow-lg"></div>
                <svg viewBox="-{{ $leftPad }} 0 {{ $w + $leftPad }} {{ $chartH + 70 }}" width="{{ $w + $leftPad }}" height="{{ $chartH + 70 }}" style="max-width: none" role="img" aria-label="Petreceri pe lună">
                    <line x1="0" y1="{{ $chartH }}" x2="{{ $w }}" y2="{{ $chartH }}" style="stroke: var(--color-border)" stroke-width="1"/>
                    @foreach ($frequency as $i => $row)
                        @php
                            $h = (float) $row->count / $maxF * ($chartH - 8);
                            $cx = $i * $slot + $slot / 2;
                        @endphp
                        <rect x="{{ $cx - $barW / 2 }}" y="{{ $chartH - $h }}" width="{{ $barW }}" height="{{ $h }}" rx="3" style="fill: var(--color-primary); cursor: pointer"
                              @mouseenter="tip = {{ \Illuminate\Support\Js::from($row->label.' · '.$int($row->count).' '.($row->count === 1 ? 'petrecere' : 'petreceri')) }}"
                              @mousemove="tx = $event.clientX; ty = $event.clientY" @mouseleave="tip = null"></rect>
                        <text x="{{ $cx }}" y="{{ $chartH + 14 }}" text-anchor="end" font-size="11" style="fill: var(--color-ink-soft)" transform="rotate(-40 {{ $cx }} {{ $chartH + 14 }})">{{ $row->label }}</text>
                    @endforeach
                </svg>
            </div>
        </div>

        {{-- Distribuție pe tip --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Distribuție pe tip</h3>
            @php $kindMax = max($totals->basic, $totals->festival, 1); @endphp
            <div class="mt-3 space-y-2">
                <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-medium text-ink">Simplă</span>
                        <span class="text-ink-soft">{{ $int($totals->basic) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-bg overflow-hidden">
                        <div class="h-full rounded-full bg-primary" style="width: {{ $barPct($totals->basic, $kindMax) }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-medium text-ink">Festival</span>
                        <span class="text-ink-soft">{{ $int($totals->festival) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-bg overflow-hidden">
                        <div class="h-full rounded-full bg-purple" style="width: {{ $barPct($totals->festival, $kindMax) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Topuri --}}
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach ([
                ['title' => 'Top venit', 'rows' => $topRevenue, 'val' => fn ($r) => $money($r->stats->revenue).' lei', 'max' => fn ($r) => $r->stats->revenue],
                ['title' => 'Top marjă', 'rows' => $topMargin, 'val' => fn ($r) => $pct($r->stats->margin), 'max' => fn ($r) => $r->stats->margin ?? 0],
                ['title' => 'Top venit / participant', 'rows' => $topPerParticipant, 'val' => fn ($r) => $r->stats->bar_per_participant === null ? '—' : $money($r->stats->bar_per_participant).' lei', 'max' => fn ($r) => $r->stats->bar_per_participant ?? 0],
                ['title' => 'Top participare', 'rows' => $topAttendance, 'val' => fn ($r) => $int($r->attendance->count).' intrări', 'max' => fn ($r) => $r->attendance->count],
            ] as $top)
                <div class="{{ $card }}">
                    <h3 class="text-sm font-semibold text-ink">{{ $top['title'] }}</h3>
                    @if ($top['rows']->isEmpty())
                        <p class="mt-3 text-xs text-ink-soft">Fără date.</p>
                    @else
                        @php $topMax = $top['rows']->max($top['max']) ?: 1; @endphp
                        <div class="mt-3 space-y-1.5">
                            @foreach ($top['rows'] as $row)
                                <a wire:key="{{ $top['title'] }}-{{ $row->party->id }}" href="{{ route('admin.parties.stats', $row->party) }}" wire:navigate
                                   class="relative flex items-center justify-between gap-3 overflow-hidden rounded-xl border border-border px-3.5 py-2 hover:border-primary/40 transition-colors">
                                    <span class="absolute inset-y-0 left-0 bg-primary-soft/50" style="width: {{ $barPct($top['max']($row), $topMax) }}%"></span>
                                    <span class="relative z-10 text-sm font-medium text-ink">{{ $loop->iteration }}. {{ $row->party->name }}</span>
                                    <span class="relative z-10 text-xs text-ink-soft shrink-0">{{ $top['val']($row) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
