{{--
    DXA: adaugat (runda 36). Vânzări online pe petrecere. Folosește $report (App\Services\OnlineSalesReport::forParty).
--}}
@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $tile = 'rounded-xl border border-border px-3.5 py-2.5';
    $th = 'px-3 py-2 text-xs font-medium text-ink-soft whitespace-nowrap';
    $td = 'px-3 py-2 text-sm text-ink whitespace-nowrap';
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
    $money = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $t = $report->totals;
    $tierStates = [
        'active' => ['activă', 'bg-primary-soft text-primary'],
        'pending' => ['în așteptare', 'bg-bg text-ink-soft'],
        'sold_out' => ['epuizată', 'bg-ink/5 text-ink-soft'],
    ];
@endphp

<div class="max-w-4xl">
    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="{{ route('admin.parties.show', $party) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Înapoi la petrecere
        </a>
    </div>

    <div class="{{ $card }} mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">{{ $party->start_date->format('d.m.Y') }}</span>
            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $party->online_sales ? 'bg-primary-soft text-primary' : 'bg-bg text-ink-soft' }}">{{ $party->online_sales ? 'Se vând bilete online' : 'Vânzare online oprită' }}</span>
        </div>
        <h2 class="mt-2 text-xl font-semibold text-ink">Vânzări online · {{ $party->name }}</h2>
        <p class="mt-1.5 text-xs text-ink-soft">Din biletele cumpărate în aplicație. Comenzile sunt „de plătit la intrare”, deci „valoare” înseamnă prețul biletelor, nu bani încasați. Biletele anulate nu se numără.</p>
    </div>

    @if (! $report->has_data)
        <div class="{{ $card }} text-sm text-ink-soft">Nu s-a vândut încă niciun bilet online la această petrecere.</div>
    @else
        {{-- Totaluri --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mb-4">
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Bilete vândute</div>
                <div class="text-lg font-semibold text-ink">{{ $int($t->tickets) }}</div>
                <div class="text-[11px] text-ink-soft">
                    @if ($t->limit !== null){{ $int($t->left) }} rămase din {{ $int($t->limit) }}@else fără limită totală @endif
                </div>
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Valoare bilete</div>
                <div class="text-lg font-semibold text-ink">{{ $money($t->revenue) }}</div>
                <div class="text-[11px] text-ink-soft">{{ $int($t->orders) }} {{ $t->orders === 1 ? 'comandă' : 'comenzi' }} · {{ $int($t->buyers) }} {{ $t->buyers === 1 ? 'cumpărător' : 'cumpărători' }}</div>
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Reduceri cu cod</div>
                <div class="text-lg font-semibold text-ink">{{ $money($t->discount) }}</div>
                <div class="text-[11px] text-ink-soft">deja scăzute din valoare</div>
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Bilete oferite</div>
                <div class="text-lg font-semibold text-ink">{{ $int($t->offered) }}</div>
                <div class="text-[11px] text-ink-soft">în combo{{ $t->free > 0 ? ' · +'.$int($t->free).' gratuite' : '' }}</div>
            </div>
        </div>
        <p class="-mt-2 mb-4 text-xs text-ink-soft">
            {{ $int($t->paid) }} plătite · {{ $int($t->offered) }} oferite în combo · {{ $int($t->free) }} gratuite · {{ $int($t->used) }} deja intrate
            @if ($t->voided > 0) · {{ $int($t->voided) }} anulate @endif
        </p>

        {{-- Pe tip de bilet --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Bilete pe tip</h3>
            <div class="mt-3 -mx-5 overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead><tr class="border-b border-border">
                        <th class="{{ $th }} pl-5">Tip</th><th class="{{ $th }} text-right">Vândute</th><th class="{{ $th }} text-right">Plătite</th>
                        <th class="{{ $th }} text-right">Oferite</th><th class="{{ $th }} text-right">Gratuite</th><th class="{{ $th }} text-right">Rămase</th>
                        <th class="{{ $th }} text-right">Valoare</th><th class="{{ $th }} text-right pr-5">Reduceri</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($report->types as $r)
                            <tr wire:key="os-t-{{ $loop->index }}" class="border-b border-border last:border-0">
                                <td class="{{ $td }} pl-5 font-medium">{{ $r->name }}@if (! $r->defined) <span class="ml-1 text-[11px] font-normal text-ink-soft">(tip șters)</span>@endif</td>
                                <td class="{{ $td }} text-right">{{ $int($r->tickets) }}</td>
                                <td class="{{ $td }} text-right">{{ $int($r->paid) }}</td>
                                <td class="{{ $td }} text-right">{{ $int($r->offered) }}</td>
                                <td class="{{ $td }} text-right">{{ $int($r->free) }}</td>
                                <td class="{{ $td }} text-right">{{ $r->left !== null ? $int($r->left).' / '.$int($r->limit) : '—' }}</td>
                                <td class="{{ $td }} text-right">{{ $money($r->revenue) }}</td>
                                <td class="{{ $td }} text-right pr-5">{{ $r->discount > 0 ? $money($r->discount) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-[11px] text-ink-soft">„Rămase” = limita tipului minus biletele vândute; „—” = fără limită pe tip.</p>
        </div>

        {{-- Combo --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Combo-uri vândute</h3>
            @if ($report->combos->isEmpty())
                <p class="mt-2 text-sm text-ink-soft">Niciun combo vândut.</p>
            @else
                <div class="mt-3 -mx-5 overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead><tr class="border-b border-border">
                            <th class="{{ $th }} pl-5">Combo</th><th class="{{ $th }} text-right">Seturi</th><th class="{{ $th }} text-right">Bilete</th>
                            <th class="{{ $th }} text-right">Plătite</th><th class="{{ $th }} text-right">Oferite</th><th class="{{ $th }} text-right pr-5">Valoare</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($report->combos as $r)
                                <tr wire:key="os-c-{{ $loop->index }}" class="border-b border-border last:border-0">
                                    <td class="{{ $td }} pl-5 font-medium">{{ $r->type }} · {{ $r->label }}</td>
                                    <td class="{{ $td }} text-right">{{ $int($r->sets) }}</td>
                                    <td class="{{ $td }} text-right">{{ $int($r->tickets) }}</td>
                                    <td class="{{ $td }} text-right">{{ $int($r->paid) }}</td>
                                    <td class="{{ $td }} text-right">{{ $int($r->offered) }}</td>
                                    <td class="{{ $td }} text-right pr-5">{{ $money($r->revenue) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Trepte --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Locuri rămase la preț special</h3>
            @if ($report->tiers->isEmpty())
                <p class="mt-2 text-sm text-ink-soft">Nicio treaptă „primele N bilete” definită la această petrecere.</p>
            @else
                <p class="mt-1 text-xs text-ink-soft">Treptele „primele N bilete”. Biletele gratuite și cele oferite în combo nu ocupă locuri din treaptă.</p>
                <div class="mt-3 space-y-2">
                    @foreach ($report->tiers as $r)
                        @php [$stLabel, $stClass] = $tierStates[$r->state]; @endphp
                        <div wire:key="os-tier-{{ $loop->index }}" class="rounded-xl border border-border px-3.5 py-2">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <span class="text-sm font-medium text-ink">{{ $r->type }} · {{ $money($r->price) }}</span>
                                    <span class="text-xs text-ink-soft">primele {{ $int($r->first) }}@if ($r->label) · {{ $r->label }}@endif</span>
                                </div>
                                <span class="shrink-0 inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $stClass }}">{{ $stLabel }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-bg overflow-hidden"><div class="h-full rounded-full bg-primary" style="width: {{ $r->size > 0 ? round($r->sold / $r->size * 100) : 0 }}%"></div></div>
                            <div class="mt-1 text-xs text-ink-soft">{{ $int($r->sold) }} vândute · {{ $int($r->left) }} {{ $r->left === 1 ? 'loc rămas' : 'locuri rămase' }} din {{ $int($r->size) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Coduri --}}
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Reduceri pe coduri</h3>
            @if ($report->codes->isEmpty())
                <p class="mt-2 text-sm text-ink-soft">Niciun cod de reducere la această petrecere.</p>
            @else
                <div class="mt-3 -mx-5 overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead><tr class="border-b border-border">
                            <th class="{{ $th }} pl-5">Cod</th><th class="{{ $th }} text-right">Bilete</th><th class="{{ $th }} text-right">Reducere</th>
                            <th class="{{ $th }} text-right">Valoare bilete</th><th class="{{ $th }} text-right pr-5">Utilizări rămase</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($report->codes as $r)
                                <tr wire:key="os-code-{{ $loop->index }}" class="border-b border-border last:border-0">
                                    <td class="{{ $td }} pl-5">
                                        <span class="font-mono font-medium">{{ $r->code }}</span>
                                        @if ($r->promoter)<span class="text-xs text-ink-soft"> · {{ $r->promoter }}</span>@endif
                                        @if (! $r->is_active)<span class="text-[11px] text-ink-soft"> (dezactivat)</span>@endif
                                    </td>
                                    <td class="{{ $td }} text-right">{{ $int($r->tickets) }}</td>
                                    <td class="{{ $td }} text-right">{{ $r->discount > 0 ? $money($r->discount) : '—' }}</td>
                                    <td class="{{ $td }} text-right">{{ $money($r->revenue) }}</td>
                                    <td class="{{ $td }} text-right pr-5">{{ $r->left !== null ? $int($r->left).' / '.$int($r->max_uses) : 'nelimitat' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
