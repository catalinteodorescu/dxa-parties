{{--
    DXA: adaugat (Participanți - statistici). „Participanți”: cine a cheltuit cel mai mult la bar, cine a cumpărat cei mai mulți tokeni,
    cine a intrat de cele mai multe ori (la această petrecere). Se include din stats.blade.php; folosește $stats, $topRows, $topTotal,
    $topBy, $card, $money. Cheltuiala la bar vine din raportările finalizate; intrările și tokenii se numără imediat.
--}}
@php
    $part = $stats->participants;
    $tabs = ['bar' => 'Cheltuit la bar', 'tokens' => 'Tokeni cumpărați', 'entries' => 'Intrări'];
    $fmtInt = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp

<div class="{{ $card }} mb-4">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h3 class="text-sm font-semibold text-ink">Participanți</h3>
            <p class="mt-1 text-xs text-ink-soft">
                Clasament la această petrecere.
                @if ($part->bar_identified_pct !== null)
                    {{ number_format($part->bar_identified_pct, 1, ',', '.') }}% din vânzările din aplicație ({{ $money($part->bar_identified) }}) au un participant.
                @endif
            </p>
        </div>
        <div class="inline-flex rounded-lg border border-border bg-bg p-0.5 text-xs">
            @foreach ($tabs as $key => $label)
                <button type="button" wire:key="top-{{ $key }}" wire:click="$set('topBy', '{{ $key }}')"
                        class="rounded-md px-2.5 py-1 font-medium {{ $topBy === $key ? 'bg-surface text-ink shadow-sm' : 'text-ink-soft hover:text-ink' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($topRows->isEmpty())
        <p class="mt-3 text-sm text-ink-soft">Nicio valoare pentru această metrică.</p>
    @else
        <div class="mt-3 space-y-2">
            @foreach ($topRows as $r)
                <div wire:key="toprow-{{ $r->participant_id }}" class="rounded-xl border border-border px-3.5 py-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex items-baseline gap-2">
                            <span class="w-5 shrink-0 text-xs text-ink-soft">{{ $loop->iteration }}.</span>
                            <a href="{{ route('admin.participants.show', $r->participant_id) }}" wire:navigate class="truncate text-sm font-medium text-ink hover:text-primary">{{ $r->name }}</a>
                        </div>
                        <div class="shrink-0 text-right text-sm font-semibold text-ink">
                            @if ($topBy === 'tokens') {{ $fmtInt($r->tokens) }} tokeni
                            @elseif ($topBy === 'entries') {{ $fmtInt($r->entries) }} {{ $r->entries === 1 ? 'intrare' : 'intrări' }}
                            @else {{ $money($r->bar_spent) }}
                            @endif
                        </div>
                    </div>
                    <div class="mt-0.5 pl-7 text-xs text-ink-soft">
                        Bar {{ $money($r->bar_spent) }} ({{ $r->bar_sales }} {{ $r->bar_sales === 1 ? 'bon' : 'bonuri' }})
                        · Tokeni {{ $fmtInt($r->tokens) }}@if ($r->tokens > 0) ({{ $money($r->tokens_amount) }})@endif
                        · Intrări {{ $fmtInt($r->entries) }}
                    </div>
                </div>
            @endforeach
        </div>
        @if ($topTotal > $topRows->count())
            <p class="mt-2 text-[11px] text-ink-soft">Primii {{ $topRows->count() }} din {{ $topTotal }}.</p>
        @endif
    @endif

    <p class="mt-2 text-[11px] leading-relaxed text-ink-soft">
        „Cheltuit la bar” = plățile vânzărilor din raportările finalizate, fără beneficii (cadourile nu sunt cheltuială); include plățile cu tokeni, la valoarea lor în lei.
        Nu se adună cu „Tokeni cumpărați”, pentru că tokenii cumpărați se cheltuie ulterior la bar. Intrările și tokenii se numără imediat.
    </p>
</div>
