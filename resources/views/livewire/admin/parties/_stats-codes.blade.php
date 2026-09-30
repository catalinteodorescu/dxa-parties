{{--
    DXA: adaugat (Coduri de reducere - statistici). Cât au adus codurile și promotorii la această petrecere, din intrările valabile
    cu cod (nu depinde de raportări). Se include din stats.blade.php; folosește $codeStats (App\Services\DiscountCodeStats::forParty),
    $card, $money. „Noi" = participanți fără nicio intrare la o petrecere anterioară.
--}}
@php
    $cs = $codeStats;
    $t = $cs->totals;
    $fmtInt = fn ($n) => number_format((float) $n, 0, ',', '.');
    $maxPromoter = max(1, (int) $cs->promoters->max('tickets'));
    $maxDay = max(1, (int) $cs->daily->max('tickets'));
    $tile = 'rounded-xl border border-border px-3.5 py-2.5';
@endphp

<div class="{{ $card }} mb-4">
    <div>
        <h3 class="text-sm font-semibold text-ink">Coduri de reducere</h3>
        <p class="mt-1 text-xs text-ink-soft">Ce au adus codurile și promotorii la această petrecere. O reducere acordată = un bilet. Anulările nu se numără.</p>
    </div>

    @if ($t->tickets === 0)
        <p class="mt-3 text-sm text-ink-soft">Niciun cod folosit încă la această petrecere.</p>
    @else
        <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-2">
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Bilete cu reducere</div>
                <div class="text-lg font-semibold text-ink">{{ $fmtInt($t->tickets) }}</div>
                @if ($t->share_pct !== null)<div class="text-[11px] text-ink-soft">{{ number_format($t->share_pct, 1, ',', '.') }}% din {{ $fmtInt($t->party_entries) }} intrări</div>@endif
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Reducere acordată</div>
                <div class="text-lg font-semibold text-ink">{{ $money($t->discount) }}</div>
                <div class="text-[11px] text-ink-soft">încasat cu cod {{ $money($t->revenue) }}</div>
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Participanți aduși</div>
                <div class="text-lg font-semibold text-ink">{{ $fmtInt($t->participants) }}</div>
                <div class="text-[11px] text-ink-soft">{{ $fmtInt($t->new) }} noi · {{ $fmtInt($t->returning) }} reveniți</div>
            </div>
            <div class="{{ $tile }}">
                <div class="text-[11px] text-ink-soft">Bilete fără participant</div>
                <div class="text-lg font-semibold text-ink">{{ $fmtInt($t->anonymous) }}</div>
                <div class="text-[11px] text-ink-soft">nu se numără ca persoane</div>
            </div>
        </div>

        {{-- Pe promotor --}}
        <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-ink-soft">Pe promotor</h4>
        <div class="mt-2 space-y-2">
            @foreach ($cs->promoters as $r)
                <div wire:key="cs-p-{{ $r->promoter_id ?? 'none' }}" class="rounded-xl border border-border px-3.5 py-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex items-baseline gap-2">
                            <span class="w-5 shrink-0 text-xs text-ink-soft">{{ $loop->iteration }}.</span>
                            <span class="truncate text-sm font-medium text-ink">{{ $r->promoter }}</span>
                            @if ($r->codes)<span class="truncate text-xs text-ink-soft font-mono">{{ implode(', ', $r->codes) }}</span>@endif
                        </div>
                        <div class="shrink-0 text-right text-sm font-semibold text-ink">{{ $fmtInt($r->participants) }} {{ $r->participants === 1 ? 'participant' : 'participanți' }}</div>
                    </div>
                    <div class="mt-1.5 ml-7 h-1.5 rounded-full bg-bg overflow-hidden"><div class="h-full rounded-full bg-primary" style="width: {{ round($r->tickets / $maxPromoter * 100) }}%"></div></div>
                    <div class="mt-1 pl-7 text-xs text-ink-soft">
                        {{ $fmtInt($r->tickets) }} {{ $r->tickets === 1 ? 'bilet' : 'bilete' }}
                        · {{ $fmtInt($r->new) }} noi · {{ $fmtInt($r->returning) }} reveniți
                        @if ($r->anonymous > 0)· {{ $fmtInt($r->anonymous) }} fără participant @endif
                        · reducere {{ $money($r->discount) }} · încasat {{ $money($r->revenue) }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Pe cod (și codurile încă nefolosite) --}}
    @if ($cs->codes->isNotEmpty())
        <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-ink-soft">Pe cod</h4>
        <div class="mt-2 space-y-2">
            @foreach ($cs->codes as $r)
                <div wire:key="cs-c-{{ $r->code_id }}" class="rounded-xl border border-border px-3.5 py-2 {{ $r->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="font-mono text-sm font-medium text-ink">{{ $r->code }}</span>
                            @if ($r->promoter)<span class="text-xs text-ink-soft"> · {{ $r->promoter }}</span>@endif
                            @unless ($r->is_active)<span class="ml-1 inline-flex items-center rounded-full bg-bg text-ink-soft text-[11px] px-2 py-0.5">inactiv</span>@endunless
                        </div>
                        <div class="shrink-0 text-right text-sm font-semibold text-ink">
                            {{ $fmtInt($r->tickets) }}@if ($r->max_uses) / {{ $fmtInt($r->max_uses) }}@endif {{ $r->tickets === 1 ? 'bilet' : 'bilete' }}
                        </div>
                    </div>
                    <div class="mt-0.5 text-xs text-ink-soft">
                        {{ $fmtInt($r->participants) }} {{ $r->participants === 1 ? 'participant' : 'participanți' }} ({{ $fmtInt($r->new) }} noi)
                        · reducere {{ $money($r->discount) }} · încasat {{ $money($r->revenue) }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Pe zile --}}
    @if ($cs->daily->count() > 1)
        <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-ink-soft">Utilizări pe zile</h4>
        <div class="mt-2 space-y-1">
            @foreach ($cs->daily as $d)
                <div wire:key="cs-d-{{ $d->day }}" class="flex items-center gap-3 text-xs">
                    <span class="w-20 shrink-0 text-ink-soft">{{ \Illuminate\Support\Carbon::parse($d->day)->format('d.m.Y') }}</span>
                    <div class="flex-1 h-2 rounded-full bg-bg overflow-hidden"><div class="h-full rounded-full bg-primary" style="width: {{ round($d->tickets / $maxDay * 100) }}%"></div></div>
                    <span class="w-24 shrink-0 text-right text-ink">{{ $fmtInt($d->tickets) }} {{ $d->tickets === 1 ? 'bilet' : 'bilete' }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
