@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', '.').'%';
    $barPct = fn ($value, $max) => $max > 0 ? max(2, round((float) $value / $max * 100, 1)) : 0;
    $methodLabel = \App\Support\PaymentMethods::labels();
@endphp
<div class="max-w-3xl">
    <div class="mb-5 flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-xl font-semibold text-ink">Bar · Statistici</h2>
            <p class="mt-1 text-sm text-ink-soft">Peste toate petrecerile — privire generală, produse, clasament, staff.</p>
        </div>
        <x-select wire:model="period" live class="w-44"
                  :options="['all' => 'Tot istoricul', 'recent' => 'Ultimele 3 luni']" />
    </div>

    @if ($overview->parties_count === 0)
        <div class="{{ $card }}">
            <p class="text-sm text-ink-soft">Nicio petrecere cu raportare finalizată {{ $period === 'recent' ? 'în ultimele 3 luni' : 'încă' }}.</p>
        </div>
    @else
        {{-- Privire generală --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Petreceri</div>
                <div class="text-2xl font-semibold text-ink">{{ $int($overview->parties_count) }}</div>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Venit</div>
                <div class="text-2xl font-semibold text-ink">{{ $money($overview->revenue) }}</div>
                <p class="mt-0.5 text-[11px] text-ink-soft">lei</p>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Profit</div>
                <div class="text-2xl font-semibold text-ink">{{ $money($overview->profit) }}</div>
                <p class="mt-0.5 text-[11px] text-ink-soft">marjă {{ $pct($overview->margin) }}</p>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Cost</div>
                <div class="text-2xl font-semibold text-ink">{{ $money($overview->cost) }}</div>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Bonuri</div>
                <div class="text-2xl font-semibold text-ink">{{ $int($overview->tx_count) }}</div>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Bon mediu</div>
                <div class="text-2xl font-semibold text-ink">{{ $overview->avg_ticket === null ? '—' : $money($overview->avg_ticket) }}</div>
                <p class="mt-0.5 text-[11px] text-ink-soft">lei</p>
            </div>
        </div>

        {{-- Distribuție pe metode de plată --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Metode de plată</h3>
            @if ($overview->payments_total <= 0)
                <p class="mt-3 text-sm text-ink-soft">Nicio plată înregistrată.</p>
            @else
                <div class="mt-3 space-y-2">
                    @foreach ($overview->payments as $method => $p)
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-medium text-ink">{{ $methodLabel[$method] ?? $method }}</span>
                                <span class="text-ink-soft">{{ $money($p['amount']) }} lei · {{ $pct($overview->payments_total > 0 ? $p['amount'] / $overview->payments_total * 100 : null) }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-bg overflow-hidden">
                                <div class="h-full rounded-full {{ \App\Support\PaymentMethods::color($method) }}" style="width: {{ $barPct($p['amount'], $overview->payments_total) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Trend: venit / profit pe petrecere --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Venit &amp; profit pe petrecere</h3>
            @if ($trend->isEmpty())
                <p class="mt-2 text-sm text-ink-soft">Nicio petrecere în perioada selectată.</p>
            @else
                @php
                    $n = $trend->count();
                    $slot = $n <= 6 ? 84 : ($n <= 12 ? 60 : 44);
                    $w = $n * $slot;
                    $chartH = 150;
                    $maxV = max(1, $trend->max('revenue'));
                    // Doua bare (venit+profit) per petrecere: latimea perechii (2*barW+2) trebuie sa
                    // incapa in slot cu un mic gutter, altfel barele petrecerilor vecine se suprapun
                    // (se intampla cu barW fix=24 cand sunt multe petreceri si slot scade la 44).
                    $barW = (int) max(8, min(24, floor(($slot - 10) / 2)));
                @endphp
                @php $leftPad = 64; @endphp
                <div class="relative mt-3 overflow-x-auto" x-data="{ tip: null, tx: 0, ty: 0 }">
                    <div x-show="tip" x-text="tip" x-bind:style="`left: ${tx + 12}px; top: ${ty + 12}px`" style="display: none" class="fixed z-50 pointer-events-none whitespace-nowrap rounded-lg bg-ink px-2 py-1 text-xs font-medium text-white shadow-lg"></div>
                    <svg viewBox="-{{ $leftPad }} 0 {{ $w + $leftPad }} {{ $chartH + 70 }}" width="{{ $w + $leftPad }}" height="{{ $chartH + 70 }}" style="max-width: none" role="img" aria-label="Venit și profit pe petrecere">
                        <line x1="0" y1="{{ $chartH }}" x2="{{ $w }}" y2="{{ $chartH }}" style="stroke: var(--color-border)" stroke-width="1"/>
                        @foreach ($trend as $i => $row)
                            @php
                                $hv = (float) $row->revenue / $maxV * ($chartH - 8);
                                $hp = max(0, (float) $row->profit) / $maxV * ($chartH - 8);
                                $cx = $i * $slot + $slot / 2;
                                $xv = $cx - $barW - 1;
                                $xp = $cx + 1;
                            @endphp
                            <rect x="{{ $xv }}" y="{{ $chartH - $hv }}" width="{{ $barW }}" height="{{ $hv }}" rx="3" style="fill: var(--color-primary); cursor: pointer"
                                  @mouseenter="tip = {{ \Illuminate\Support\Js::from($row->party->name.' · venit '.$money($row->revenue).' lei') }}"
                                  @mousemove="tx = $event.clientX; ty = $event.clientY" @mouseleave="tip = null"></rect>
                            <rect x="{{ $xp }}" y="{{ $chartH - $hp }}" width="{{ $barW }}" height="{{ $hp }}" rx="3" style="fill: var(--color-ink-soft); opacity: 0.4; cursor: pointer"
                                  @mouseenter="tip = {{ \Illuminate\Support\Js::from($row->party->name.' · profit '.$money($row->profit).' lei') }}"
                                  @mousemove="tx = $event.clientX; ty = $event.clientY" @mouseleave="tip = null"></rect>
                            <text x="{{ $cx }}" y="{{ $chartH + 14 }}" text-anchor="end" font-size="11" style="fill: var(--color-ink-soft)" transform="rotate(-40 {{ $cx }} {{ $chartH + 14 }})">{{ \Illuminate\Support\Str::limit($row->party->name, 12, '') }}</text>
                        @endforeach
                    </svg>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background: var(--color-primary)"></span>Venit</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background: var(--color-ink-soft); opacity: 0.4"></span>Profit</span>
                </div>
            @endif
        </div>

        {{-- Cele mai vândute produse --}}
        <div class="grid sm:grid-cols-3 gap-3 mb-4">
            @foreach ([['top_qty', 'Top cantitate', 'buc', fn ($p) => number_format($p->qty, 0, ',', '.')], ['top_revenue', 'Top venit', 'lei', fn ($p) => $money($p->revenue)], ['top_profit', 'Top profit', 'lei', fn ($p) => $money($p->profit)]] as [$key, $title, $unit, $fmt])
                @php $list = $products->{$key}; $max = $list->max($key === 'top_qty' ? 'qty' : ($key === 'top_revenue' ? 'revenue' : 'profit')) ?: 1; @endphp
                <div class="{{ $card }}">
                    <h3 class="text-sm font-semibold text-ink">{{ $title }}</h3>
                    @if ($list->isEmpty())
                        <p class="mt-3 text-xs text-ink-soft">Fără date.</p>
                    @else
                        <div class="mt-3 space-y-2">
                            @foreach ($list as $p)
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1 gap-2">
                                        <span class="font-medium text-ink truncate">{{ $p->name }}</span>
                                        <span class="text-ink-soft shrink-0">{{ $fmt($p) }} {{ $unit }}</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-bg overflow-hidden">
                                        <div class="h-full rounded-full bg-primary" style="width: {{ $barPct($key === 'top_qty' ? $p->qty : ($key === 'top_revenue' ? $p->revenue : $p->profit), $max) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Clasament petreceri --}}
        <div class="{{ $card }} mb-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h3 class="text-sm font-semibold text-ink">Clasament petreceri</h3>
                    <p class="mt-1 text-xs text-ink-soft">All-time, top 10.</p>
                </div>
                <x-select wire:model="rankBy" live class="w-52"
                          :options="['revenue' => 'După venit bar', 'margin' => 'După marjă', 'per_participant' => 'După venit / participant']" />
            </div>
            @if ($ranking->isEmpty())
                <p class="mt-3 text-sm text-ink-soft">Nicio petrecere cu raportare finalizată.</p>
            @else
                @php
                    $rankMax = match ($rankBy) {
                        'margin' => $ranking->max(fn ($r) => $r->stats->margin ?? 0) ?: 1,
                        'per_participant' => $ranking->max(fn ($r) => $r->stats->bar_per_participant ?? 0) ?: 1,
                        default => $ranking->max(fn ($r) => $r->stats->revenue) ?: 1,
                    };
                @endphp
                <div class="mt-3 space-y-1.5">
                    @foreach ($ranking as $row)
                        @php
                            $val = match ($rankBy) {
                                'margin' => $row->stats->margin,
                                'per_participant' => $row->stats->bar_per_participant,
                                default => $row->stats->revenue,
                            };
                            $label = match ($rankBy) {
                                'margin' => $pct($val),
                                'per_participant' => $val === null ? '—' : $money($val).' lei',
                                default => $money($val).' lei',
                            };
                        @endphp
                        <a wire:key="rk-{{ $row->party->id }}" href="{{ route('admin.parties.stats', $row->party) }}" wire:navigate
                           class="relative flex items-center justify-between gap-3 overflow-hidden rounded-xl border border-border px-3.5 py-2 hover:border-primary/40 transition-colors">
                            <span class="absolute inset-y-0 left-0 bg-primary-soft/50" style="width: {{ $barPct($val ?? 0, $rankMax) }}%"></span>
                            <span class="relative z-10 text-sm font-medium text-ink">{{ $loop->iteration }}. {{ $row->party->name }}</span>
                            <span class="relative z-10 text-xs text-ink-soft shrink-0">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Staff --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Staff</h3>
            <p class="mt-1 text-xs text-ink-soft">Cine a înregistrat cele mai multe vânzări/valoare, în perioada selectată.</p>
            @if ($staff->isEmpty())
                <p class="mt-3 text-sm text-ink-soft">Nicio vânzare din aplicație în perioada selectată.</p>
            @else
                @php $staffMax = $staff->max('total') ?: 1; @endphp
                <div class="mt-3 space-y-2">
                    @foreach ($staff as $s)
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-medium text-ink">{{ $s->name }}</span>
                                <span class="text-ink-soft">{{ $money($s->total) }} lei · {{ $int($s->count) }} {{ $s->count === 1 ? 'bon' : 'bonuri' }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-bg overflow-hidden">
                                <div class="h-full rounded-full bg-primary" style="width: {{ $barPct($s->total, $staffMax) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
