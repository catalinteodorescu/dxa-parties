@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $signed = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs((float) $n), 2, ',', '.');
    $sint = fn ($n) => $n === null ? '—' : ($n > 0 ? '+' : ($n < 0 ? '−' : '')).abs((int) $n);
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $partyOptions = $parties->mapWithKeys(fn ($p) => [$p->id => $p->name])->all();
    $tone = fn ($ok) => $ok ? 'text-success' : 'text-warning';
@endphp
<div class="max-w-4xl space-y-4">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-xl font-semibold text-ink">Bilanțul serii</h2>
            <p class="mt-1 text-sm text-ink-soft">Recepția și barul la un loc, pe petrecere: cât cash ai încasat, câți tokeni sunt în circulație și cum stau creditele.</p>
        </div>
        @if ($parties->isNotEmpty())
            <x-select wire:model="party" live class="w-60" :options="$partyOptions" />
        @endif
    </div>

    @if (! $selected)
        <div class="{{ $card }} text-sm text-ink-soft">Nicio raportare de casă încă (nici de recepție, nici de bar).</div>
    @else
        @if ($tot->provisional)
            <x-alert type="info">Cifre provizorii: cel puțin o raportare nu e încă trimisă / finalizată.</x-alert>
        @endif

        {{-- Totaluri --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Cash (recepție + bar)</div>
                <div class="text-2xl font-semibold text-ink">{{ $money($tot->counted_cash) }} <span class="text-sm font-normal text-ink-soft">lei numărat</span></div>
                <p class="mt-0.5 text-xs text-ink-soft">așteptat {{ $money($tot->expected_cash) }} · <span class="{{ $tone(abs($tot->cash_diff) < 0.005) }}">{{ abs($tot->cash_diff) < 0.005 ? 'corect' : $signed($tot->cash_diff).' lei' }}</span></p>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Tokeni</div>
                <div class="text-2xl font-semibold text-ink">{{ $tot->tokens_sold }} <span class="text-sm font-normal text-ink-soft">vânduți</span></div>
                <p class="mt-0.5 text-xs text-ink-soft">primiți la bar {{ $tot->tokens_received }} · în circulație {{ $tot->tokens_sold - $tot->tokens_received }} · diferențe numărare <span class="{{ $tone($tot->tokens_diff === 0) }}">{{ $tot->tokens_diff === 0 ? '0' : $sint($tot->tokens_diff) }}</span></p>
            </div>
            <div class="{{ $card }}">
                <div class="text-[11px] text-ink-soft">Credite</div>
                <div class="text-2xl font-semibold text-ink">{{ $money($tot->credits_sold) }} <span class="text-sm font-normal text-ink-soft">lei vândute</span></div>
                <p class="mt-0.5 text-xs text-ink-soft">cheltuite la bar {{ $money($tot->credits_spent) }}</p>
            </div>
        </div>

        {{-- Recepție --}}
        <div class="{{ $card }}">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-ink">Recepție</h3>
                <a href="{{ route('admin.reception.reports.index') }}" wire:navigate class="text-xs text-ink-soft hover:text-ink">Raportări recepție →</a>
            </div>
            @forelse ($reception as $x)
                @php $f = $x->fig; @endphp
                <div wire:key="rr-{{ $x->model->id }}" class="mt-3 rounded-xl border border-border px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <a href="{{ route('admin.reception.reports.show', $x->model) }}" wire:navigate class="font-medium text-ink hover:text-primary">{{ $x->model->title() }}</a>
                        <span class="text-[11px] {{ $x->final ? 'text-success' : 'text-warning' }}">{{ $x->model->isFinalized() ? 'finalizată' : ($x->model->isSubmitted() ? 'trimisă' : 'în lucru') }}</span>
                    </div>
                    <div class="mt-1 text-xs text-ink-soft">
                        Cash: așteptat {{ $money($f->expected_cash) }} · numărat {{ $f->counted_cash === null ? '—' : $money($f->counted_cash) }}
                        @if ($f->cash_diff !== null)· <span class="{{ $tone(abs($f->cash_diff) < 0.005) }}">{{ abs($f->cash_diff) < 0.005 ? 'corect' : $signed($f->cash_diff).' lei' }}</span>@endif
                    </div>
                    <div class="text-xs text-ink-soft">
                        Tokeni: fond {{ $f->opening_tokens }} − vânduți {{ $f->tokens_sold }} = {{ $f->expected_tokens }} · numărați {{ $f->counted_tokens ?? '—' }}
                        @if ($f->tokens_diff !== null)· <span class="{{ $tone($f->tokens_diff === 0) }}">{{ $f->tokens_diff === 0 ? 'corect' : $sint($f->tokens_diff) }}</span>@endif
                        · credite vândute {{ $money($f->credits_amount) }} lei
                    </div>
                </div>
            @empty
                <p class="mt-3 text-sm text-ink-soft">Nicio raportare de recepție la această petrecere.</p>
            @endforelse
        </div>

        {{-- Bar --}}
        <div class="{{ $card }}">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-ink">Bar</h3>
                <a href="{{ route('admin.bar.reports.index') }}" wire:navigate class="text-xs text-ink-soft hover:text-ink">Raportări bar →</a>
            </div>
            @forelse ($bar as $x)
                @php $f = $x->fig; @endphp
                <div wire:key="rb-{{ $x->model->id }}" class="mt-3 rounded-xl border border-border px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <a href="{{ route('admin.bar.reports.show', $x->model) }}" wire:navigate class="font-medium text-ink hover:text-primary">{{ $x->model->title() }}</a>
                        <span class="text-[11px] {{ $x->final ? 'text-success' : 'text-warning' }}">{{ $x->model->isSubmitted() ? 'trimisă' : ($x->final ? 'sesiune închisă' : 'în lucru') }}</span>
                    </div>
                    <div class="mt-1 text-xs text-ink-soft">
                        Cash: așteptat {{ $money($f->expected_cash) }} · numărat {{ $f->counted_cash === null ? '—' : $money($f->counted_cash) }}
                        @if ($f->cash_diff !== null)· <span class="{{ $tone(abs($f->cash_diff) < 0.005) }}">{{ abs($f->cash_diff) < 0.005 ? 'corect' : $signed($f->cash_diff).' lei' }}</span>@endif
                    </div>
                    <div class="text-xs text-ink-soft">
                        Tokeni: primiți {{ $f->tokens_received }} · numărați {{ $f->counted_tokens ?? '—' }}
                        @if ($f->tokens_diff !== null)· <span class="{{ $tone($f->tokens_diff === 0) }}">{{ $f->tokens_diff === 0 ? 'corect' : $sint($f->tokens_diff) }}</span>@endif
                        · vândut {{ $money($f->revenue) }} lei ({{ $f->sales_count }} bonuri)
                    </div>
                </div>
            @empty
                <p class="mt-3 text-sm text-ink-soft">Nicio raportare de bar la această petrecere.</p>
            @endforelse
        </div>
    @endif
</div>
