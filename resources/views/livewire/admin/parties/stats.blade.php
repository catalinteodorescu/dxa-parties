@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $signed = fn ($n, $suffix = '') => ((float) $n > 0 ? '+' : ((float) $n < 0 ? '−' : '')).number_format(abs((float) $n), $suffix === ' lei' ? 2 : 0, ',', '.').$suffix;
    $fmtVal = function ($type, $v) use ($money, $signed) {
        if ($v === null) {
            return '—';
        }

        return match ($type) {
            'money' => $money($v),
            'money_signed' => $signed($v, ' lei'), // diferență cu semn (ex. casa de recepție)
            'percent' => number_format((float) $v, 1, ',', '.').'%',
            default => number_format((float) $v, 0, ',', '.'),
        };
    };
    // Diferenta fata de petrecerea comparata: [text, clasa] sau null. 'up' = mai mare e mai bine, 'down' = mai mic e mai bine.
    $delta = function ($type, $a, $b, $dir) {
        if ($a === null || $b === null || $type === 'money_signed') {
            return null;
        }
        $diff = (float) $a - (float) $b;
        if (abs($diff) < 0.005) {
            return ['text' => '= la fel', 'class' => 'text-ink-soft'];
        }
        if ($type === 'percent') {
            $text = ($diff > 0 ? '▲ +' : '▼ −').number_format(abs($diff), 1, ',', '.').' p.p.';
        } elseif ((float) $b == 0.0) {
            return null;
        } else {
            $text = ($diff > 0 ? '▲ +' : '▼ −').number_format(abs($diff / abs((float) $b) * 100), 1, ',', '.').'%';
        }
        $good = ($diff > 0 && $dir === 'up') || ($diff < 0 && $dir === 'down');
        $class = $dir === null ? 'text-ink-soft' : ($good ? 'text-success' : 'text-danger');

        return ['text' => $text, 'class' => $class];
    };
    $cmpShort = $compareParty ? \Illuminate\Support\Str::limit($compareParty->name, 22).' · '.$compareParty->start_date->format('d.m') : '';
    $methodLabels = \App\Support\PaymentMethods::labels();
@endphp

<div class="max-w-4xl">
    {{-- Antet --}}
    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="{{ route('admin.parties.show', $party) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Înapoi la petrecere
        </a>
    </div>

    <div class="{{ $card }} mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $party->isFestival() ? 'bg-purple/10 text-purple' : 'bg-bg text-ink-soft' }}">{{ $party->isFestival() ? 'Festival' : 'Simplă' }}</span>
            <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">{{ $party->start_date->format('d.m.Y') }}</span>
        </div>
        <h2 class="mt-2 text-xl font-semibold text-ink">Statistici · {{ $party->name }}</h2>
        @if ($stats->has_data)
            <p class="mt-1.5 text-xs text-ink-soft">
                Raportări finalizate incluse:
                @foreach ($stats->reports as $r)
                    <a href="{{ route('admin.stock-reports.edit', $r) }}" wire:navigate class="text-primary hover:underline">{{ $r->title() }}</a>@if (! $loop->last), @endif
                @endforeach
            </p>
        @endif
    </div>

    @if ($unreported['count'] > 0)
        <x-alert type="info" class="mb-4">
            {{ $unreported['count'] }} {{ $unreported['count'] === 1 ? 'vânzare' : 'vânzări' }} ({{ $money($unreported['total']) }}) din sesiunile acestei petreceri nu sunt încă raportate, deci nu sunt incluse în statistici.
        </x-alert>
    @endif

    {{-- Comparație --}}
    <div class="{{ $card }} mb-4">
        <label class="block text-sm font-medium text-ink mb-1.5">Compară cu</label>
        <x-select wire:model="compare" live class="max-w-md" :options="$compareOptions" />
        @if ($compareParty)
            <p class="mt-2 text-xs text-ink-soft">
                Comparat cu
                <a href="{{ route('admin.parties.stats', $compareParty) }}" wire:navigate class="text-primary hover:underline">{{ $compareParty->name }}</a>
                ({{ $compareParty->start_date->format('d.m.Y') }}).
                Sub fiecare valoare, „vs” arată valoarea petrecerii comparate.
            </p>
        @elseif (count($compareOptions) === 1)
            <p class="mt-2 text-xs text-ink-soft">Nu există alte petreceri cu raportări finalizate.</p>
        @else
            <p class="mt-2 text-xs text-ink-soft">Alege o petrecere din listă ca să compari, sau lasă „Fără comparație”.</p>
        @endif
    </div>

    @if (! $stats->has_data)
        <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
            Nicio raportare finalizată pentru această petrecere. Statisticile apar după ce o
            <a href="{{ route('admin.stock-reports.index') }}" wire:navigate class="text-primary hover:underline">Raportare</a>
            legată de petrecere este finalizată.
        </div>

        {{-- DXA: adaugat (Recepție): intrările se văd și fără raportări --}}
        @if ($stats->attendance)
            <div class="mt-4">
                @include('livewire.admin.parties._stats-attendance', ['headline' => true])
            </div>
        @endif
        @if ($stats->reception)
            @include('livewire.admin.parties._stats-reception')
        @endif
        @if ($stats->tokens)
            @include('livewire.admin.parties._stats-tokens')
        @endif
        @if ($stats->participants)
            @include('livewire.admin.parties._stats-participants')
        @endif
    @else
        {{-- KPI: cost / profit (mereu primele, cardurile mari) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            @foreach ($kpis as $k)
                @if ($k->group === 'primary')
                    @include('livewire.admin.parties._stats-kpi', ['big' => true])
                @endif
            @endforeach
        </div>

        {{-- DXA: adaugat — separare vizuala pe teme (header simplu + grid), in loc de un singur
             grid nediferentiat; ordinea e fixa, o tema fara date pentru petrecerea curenta nu apare. --}}
        @php
            $kpiGroups = ['bar' => 'Bar', 'reception' => 'Recepție', 'tokens' => 'Tokeni', 'participants' => 'Participanți'];
        @endphp
        @foreach ($kpiGroups as $groupKey => $groupLabel)
            @php $groupKpis = collect($kpis)->where('group', $groupKey); @endphp
            @if ($groupKpis->isNotEmpty())
                <div class="mb-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-soft/70 mb-2">{{ $groupLabel }}</h3>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        @foreach ($groupKpis as $k)
                            @include('livewire.admin.parties._stats-kpi', ['big' => false])
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Curba vânzărilor pe ore de ceas --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Vânzări pe ore</h3>
            @if ($axis === [])
                <p class="mt-2 text-sm text-ink-soft">Nicio vânzare din aplicație cu oră înregistrată în raportările incluse.</p>
            @else
                @php
                    $n = count($axis);
                    // Latimea unei ore in px (1 unitate viewBox = 1 px, deci textul nu se scaleaza): mai lata cand sunt putine ore.
                    $slot = $n <= 6 ? 72 : ($n <= 12 ? 56 : 44);
                    $w = $n * $slot;
                    $chartH = 150;
                    $maxV = 1;
                    foreach ($axis as $h) {
                        $maxV = max($maxV, $stats->hours[$h]['total'] ?? 0, $cmp?->hours[$h]['total'] ?? 0);
                    }
                    $labelStep = $n > 12 ? 2 : 1;
                    $barW = $cmp ? 20 : 30;
                    $peakOf = function ($hours) {
                        if ($hours === []) {
                            return null;
                        }
                        $h = array_key_first(collect($hours)->sortByDesc('total')->all());

                        return [$h, $hours[$h]];
                    };
                    $peak = $peakOf($stats->hours);
                    $cmpPeak = $cmp ? $peakOf($cmp->hours) : null;
                    $hourRange = fn ($h) => sprintf('%02d:00–%02d:00', $h, ($h + 1) % 24);
                @endphp
                <div class="mt-3 overflow-x-auto">
                    <svg viewBox="0 0 {{ $w }} {{ $chartH + 24 }}" width="{{ $w }}" height="{{ $chartH + 24 }}" style="max-width: none" role="img" aria-label="Vânzări pe ore">
                        <line x1="0" y1="{{ $chartH }}" x2="{{ $w }}" y2="{{ $chartH }}" style="stroke: var(--color-border)" stroke-width="1"/>
                        @foreach ($axis as $i => $h)
                            @php
                                $a = (float) ($stats->hours[$h]['total'] ?? 0);
                                $b = (float) ($cmp?->hours[$h]['total'] ?? 0);
                                $ha = $a / $maxV * ($chartH - 8);
                                $hb = $b / $maxV * ($chartH - 8);
                                $cx = $i * $slot + $slot / 2;
                                $xa = $cmp ? $cx - $barW - 1 : $cx - $barW / 2;
                                $xb = $cx + 1;
                            @endphp
                            @if ($a > 0)
                                <rect x="{{ $xa }}" y="{{ $chartH - $ha }}" width="{{ $barW }}" height="{{ $ha }}" rx="3" style="fill: var(--color-primary)">
                                    <title>{{ $hourRange($h) }} · {{ $money($a) }} · {{ $stats->hours[$h]['count'] }} bonuri</title>
                                </rect>
                            @endif
                            @if ($cmp && $b > 0)
                                <rect x="{{ $xb }}" y="{{ $chartH - $hb }}" width="{{ $barW }}" height="{{ $hb }}" rx="3" style="fill: var(--color-ink-soft); opacity: 0.4">
                                    <title>{{ $cmpShort }} · {{ $hourRange($h) }} · {{ $money($b) }} · {{ $cmp->hours[$h]['count'] }} bonuri</title>
                                </rect>
                            @endif
                            @if ($i % $labelStep === 0)
                                <text x="{{ $cx }}" y="{{ $chartH + 16 }}" text-anchor="middle" font-size="12" style="fill: var(--color-ink-soft)">{{ sprintf('%02d', $h) }}</text>
                            @endif
                        @endforeach
                    </svg>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background: var(--color-primary)"></span>{{ $party->name }}</span>
                    @if ($cmp)
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background: var(--color-ink-soft); opacity: 0.4"></span>{{ $compareParty->name }}</span>
                    @endif
                    <span>Ora de ceas, în lei</span>
                </div>
                @if ($peak)
                    <p class="mt-2 text-sm text-ink-soft">
                        Vârf: <span class="font-medium text-ink">{{ $hourRange($peak[0]) }}</span> · {{ $money($peak[1]['total']) }} ({{ $peak[1]['count'] }} bonuri)
                        @if ($cmpPeak)
                            <span class="text-xs">· {{ $compareParty->name }}: {{ $hourRange($cmpPeak[0]) }} · {{ $money($cmpPeak[1]['total']) }}</span>
                        @endif
                    </p>
                @endif
            @endif
        </div>

        {{-- Plăți --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Metode de plată</h3>
            @if ($stats->payments_total <= 0)
                <p class="mt-2 text-sm text-ink-soft">Fără plăți din aplicație în raportările incluse.</p>
            @else
                @php
                    $bar = function ($s) use ($methodLabels) {
                        $out = [];
                        foreach (array_keys($methodLabels) as $m) {
                            $amt = $s->payments[$m]['amount'] ?? 0;
                            if ($amt > 0 && $s->payments_total > 0) {
                                $out[] = [$m, $amt / $s->payments_total * 100];
                            }
                        }

                        return $out;
                    };
                @endphp
                <div class="mt-3 flex h-3 w-full rounded-full bg-bg">
                    @foreach ($bar($stats) as [$m, $pct])
                        <div class="{{ \App\Support\PaymentMethods::color($m) }} first:rounded-l-full last:rounded-r-full" style="width: {{ $pct }}%" title="{{ $methodLabels[$m] }} {{ number_format($pct, 1, ',', '.') }}%"></div>
                    @endforeach
                </div>
                @if ($cmp && $cmp->payments_total > 0)
                    <div class="mt-1.5 flex h-1.5 w-full rounded-full bg-bg opacity-60">
                        @foreach ($bar($cmp) as [$m, $pct])
                            <div class="{{ \App\Support\PaymentMethods::color($m) }} first:rounded-l-full last:rounded-r-full" style="width: {{ $pct }}%"></div>
                        @endforeach
                    </div>
                @endif
                <div class="mt-3 space-y-1.5">
                    @foreach ($methodLabels as $m => $label)
                        @php
                            $amt = $stats->payments[$m]['amount'] ?? 0;
                            $cAmt = $cmp?->payments[$m]['amount'] ?? 0;
                        @endphp
                        @if ($amt > 0 || $cAmt > 0)
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="inline-flex items-center gap-2 text-ink">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full {{ \App\Support\PaymentMethods::color($m) }}"></span>{{ $label }}
                                    @if ($m === 'token' && ($stats->payments[$m]['tokens'] ?? 0) > 0)
                                        <span class="text-xs text-ink-soft">{{ number_format($stats->payments[$m]['tokens'], 0, ',', '.') }} tk</span>
                                    @endif
                                </span>
                                <span class="text-right">
                                    <span class="font-medium text-ink">{{ $money($amt) }}</span>
                                    <span class="text-xs text-ink-soft">({{ $stats->payments_total > 0 ? number_format($amt / $stats->payments_total * 100, 1, ',', '.') : '0' }}%)</span>
                                    @if ($cmp && $cmp->payments_total > 0)
                                        <span class="block text-xs text-ink-soft" title="{{ $compareParty->name }}">vs {{ number_format($cAmt / $cmp->payments_total * 100, 1, ',', '.') }}%</span>
                                    @endif
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Produse --}}
        <div class="{{ $card }} mb-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h3 class="text-sm font-semibold text-ink">Produse</h3>
                <div class="inline-flex rounded-lg border border-border bg-bg p-0.5 text-xs">
                    @foreach (['revenue' => 'Venit', 'qty' => 'Cantitate', 'profit' => 'Profit'] as $key => $label)
                        <button type="button" wire:click="setSort('{{ $key }}')"
                                class="rounded-md px-2.5 py-1 font-medium {{ $sort === $key ? 'bg-surface text-ink shadow-sm' : 'text-ink-soft hover:text-ink' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            @if ($productsTotal === 0)
                <p class="mt-2 text-sm text-ink-soft">Niciun produs vândut.</p>
            @else
                <div class="mt-3 divide-y divide-border">
                    @foreach ($products as $p)
                        @php $c = $compareProducts->get($p->menu_item_id); @endphp
                        <div class="py-2.5" wire:key="stats-product-{{ $p->menu_item_id }}">
                            <div class="text-sm font-medium text-ink">{{ $p->name }}</div>
                            <div class="mt-1 grid grid-cols-3 gap-2 text-sm">
                                <div>
                                    <div class="text-[11px] text-ink-soft/80">Cantitate</div>
                                    <div class="text-ink">{{ rtrim(rtrim(number_format($p->qty, 3, ',', '.'), '0'), ',') }}</div>
                                    @if ($cmp)<div class="text-[11px] text-ink-soft">{{ $c ? rtrim(rtrim(number_format($c->qty, 3, ',', '.'), '0'), ',') : '—' }}</div>@endif
                                </div>
                                <div>
                                    <div class="text-[11px] text-ink-soft/80">Venit</div>
                                    <div class="text-ink">{{ $money($p->revenue) }}</div>
                                    @if ($cmp)<div class="text-[11px] text-ink-soft">{{ $c ? $money($c->revenue) : '—' }}</div>@endif
                                </div>
                                <div>
                                    <div class="text-[11px] text-ink-soft/80">Profit</div>
                                    <div class="text-ink">{{ $p->profit !== null ? $money($p->profit) : '—' }}</div>
                                    @if ($cmp)<div class="text-[11px] text-ink-soft">{{ $c && $c->profit !== null ? $money($c->profit) : '—' }}</div>@endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($cmp)
                    <p class="mt-1 text-[11px] text-ink-soft">Rândul mic de sub fiecare valoare: {{ $compareParty->name }}.</p>
                @endif
                @if ($productsTotal > 10)
                    <button type="button" wire:click="$toggle('showAllProducts')" class="mt-2 text-xs font-medium text-primary hover:underline">
                        {{ $showAllProducts ? 'Arată doar primele 10' : 'Arată toate cele '.$productsTotal.' produse' }}
                    </button>
                @endif
            @endif
        </div>

        {{-- Înregistrat de --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Înregistrat de</h3>
            <p class="mt-0.5 text-xs text-ink-soft">Contul care a introdus vânzarea (vânzările din aplicație). Când apare aplicația de Bar, aici se trece pe barmanul real.</p>
            @if ($stats->staff->isEmpty())
                <p class="mt-2 text-sm text-ink-soft">Nicio vânzare din aplicație în raportările incluse.</p>
            @else
                @php $staffMax = max(1, (float) $stats->staff->max('total')); @endphp
                <div class="mt-3 space-y-3">
                    @foreach ($stats->staff as $s)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="font-medium text-ink truncate">{{ $s->name }}</span>
                                <span class="text-ink shrink-0">{{ $money($s->total) }} <span class="text-xs text-ink-soft">· {{ $s->count }} {{ $s->count === 1 ? 'bon' : 'bonuri' }}</span></span>
                            </div>
                            <div class="mt-1 h-1.5 w-full rounded-full bg-bg"><div class="h-1.5 rounded-full bg-primary" style="width: {{ $s->total / $staffMax * 100 }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Inventar --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Inventar de final</h3>
            @if (! $stats->closing)
                <p class="mt-2 text-sm text-ink-soft">Nu s-a făcut inventar în raportările incluse.</p>
            @else
                @php
                    $cl = $stats->closing;
                    $cc = $cmp?->closing;
                    $tone = fn ($v) => $v === null ? 'text-ink-soft' : (abs((float) $v) < 0.005 ? 'text-success' : ((float) $v < 0 ? 'text-danger' : 'text-warning'));
                    $invRows = [
                        ['Diferență cash', $cl->cash_diff, $cc?->cash_diff, fn ($v) => $v === null ? '—' : ((float) $v == 0.0 ? '0,00 lei' : $signed($v, ' lei'))],
                        ['Diferență tokeni', $cl->tokens_diff, $cc?->tokens_diff, fn ($v) => $v === null ? '—' : ((float) $v == 0.0 ? '0' : $signed($v, ' tk'))],
                        ['Produse cu diferențe', $cl->diff_items, $cc?->diff_items, fn ($v) => $v === null ? '—' : (string) $v],
                        ['Valoare diferențe', $cl->inventory_value, $cc?->inventory_value, fn ($v) => $v === null ? '—' : ((float) $v == 0.0 ? '0,00 lei' : $signed($v, ' lei'))],
                    ];
                @endphp
                @if ($cl->clean)
                    <p class="mt-1 text-xs text-success">Fără diferențe la inventar.</p>
                @endif
                <div class="mt-3 divide-y divide-border">
                    @foreach ($invRows as [$label, $v, $cv, $fmt])
                        <div class="flex items-baseline justify-between gap-3 py-2 text-sm">
                            <span class="text-ink-soft">{{ $label }}</span>
                            <span class="text-right">
                                <span class="font-medium {{ $label === 'Produse cu diferențe' ? 'text-ink' : $tone($v) }}">{{ $fmt($v) }}</span>
                                @if ($cmp)
                                    <span class="block text-xs text-ink-soft" title="{{ $compareParty->name }}">vs {{ $cc ? $fmt($cv) : '—' }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
                @if ($cl->cost_unknown)
                    <p class="mt-1 text-[11px] text-warning">La unele produse costul e necunoscut, deci valoarea diferențelor e parțială.</p>
                @endif
            @endif
        </div>

        {{-- DXA: adaugat (Recepție) --}}
        @if ($stats->attendance)
            @include('livewire.admin.parties._stats-attendance', ['headline' => false])
        @endif
        @if ($stats->reception)
            @include('livewire.admin.parties._stats-reception')
        @endif
        @if ($stats->tokens)
            @include('livewire.admin.parties._stats-tokens')
        @endif
        @if ($stats->participants)
            @include('livewire.admin.parties._stats-participants')
        @endif

        <p class="mb-2 text-[11px] leading-relaxed text-ink-soft">
            Doar raportări finalizate. Venitul, costul și profitul includ și liniile introduse manual în Raportare; bonurile, curba pe ore,
            plățile și „înregistrat de” se calculează din vânzările din aplicație (liniile manuale nu au bon sau oră).
        </p>
    @endif
</div>
