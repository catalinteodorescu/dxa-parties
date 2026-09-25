@php
    $draft = $report->isDraft();
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $input = 'rounded-lg border border-border bg-white px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $signed = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs((float) $n), 2, ',', '.');
    $diff = $fig->cash_diff;
    $diffOk = $diff !== null && abs($diff) < 0.005;
    $diffWord = $diff === null ? '' : ($diffOk ? 'casă corectă' : ($diff < 0 ? 'lipsesc '.$money(abs($diff)).' lei' : 'în plus '.$money($diff).' lei'));
    $session = $report->session;
    $ticketTotal = collect($fig->tickets)->sum('count');
@endphp

<div class="max-w-4xl">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('admin.reception.reports.index') }}" wire:navigate class="text-xs text-ink-soft hover:text-ink">← Raportări de recepție</a>
            <h2 class="mt-1 text-xl font-semibold text-ink">
                {{ $report->title() }}
                @if ($draft)
                    <span class="align-middle inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-primary-soft text-primary">Draft</span>
                @else
                    <span class="align-middle inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-info-soft text-info">Finalizat</span>
                @endif
            </h2>
            <p class="mt-1 text-sm text-ink-soft">
                {{ $report->party?->name ?? '—' }}
                <span class="text-ink-soft/40">·</span> {{ $session?->title() }}
                @if (! $draft && $report->finalized_at)
                    <span class="text-ink-soft/40">·</span> finalizată {{ $report->finalized_at->format('d.m.Y H:i') }}@if ($report->finalizer) de {{ $report->finalizer->name }}@endif
                @endif
            </p>
        </div>
        @if (! $draft)
            <x-btn variant="neutral" wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf" class="self-start">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export PDF
            </x-btn>
        @endif
    </div>

    @if (! $draft)
        <x-alert type="success" class="mb-4" :dismissible="false">Raportare finalizată: valorile sunt înghețate, iar sesiunea de recepție e închisă. Următoarea intrare sau vânzare de tokeni deschide o sesiune nouă.</x-alert>
    @endif

    {{-- Rezumat --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="rounded-xl border border-border bg-surface px-4 py-3">
            <div class="text-[11px] text-ink-soft">Cash așteptat</div>
            <div class="text-xl font-semibold text-ink">{{ $money($fig->expected_cash) }} <span class="text-xs font-normal text-ink-soft">lei</span></div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-4 py-3">
            <div class="text-[11px] text-ink-soft">Cash numărat</div>
            <div class="text-xl font-semibold text-ink">
                @if ($fig->counted_cash === null) — @else {{ $money($fig->counted_cash) }} <span class="text-xs font-normal text-ink-soft">lei</span>@endif
            </div>
        </div>
        <div class="rounded-xl border px-4 py-3 {{ $diff === null ? 'border-border bg-surface' : ($diffOk ? 'border-success/30 bg-success-soft' : 'border-warning/40 bg-warning/10') }}">
            <div class="text-[11px] text-ink-soft">Diferență</div>
            <div class="text-xl font-semibold {{ $diff === null ? 'text-ink' : ($diffOk ? 'text-success' : 'text-warning') }}">
                @if ($diff === null) — @else {{ $signed($diff) }} <span class="text-xs font-normal">lei</span>@endif
            </div>
            @if ($diff !== null)<div class="text-[11px] text-ink-soft">{{ $diffWord }}</div>@endif
        </div>
    </div>

    {{-- 1. Cash --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">1. Cash</h3>
        <p class="mt-1 text-xs text-ink-soft">Doar cash-ul se numără. Cash așteptat = fond de casă + încasat din intrări + încasat din tokeni − predat/scos.</p>

        <dl class="mt-4 divide-y divide-border text-sm">
            <div class="py-2.5 flex items-center justify-between gap-3">
                <dt class="text-ink">Fond de casă <span class="block text-[11px] text-ink-soft">cash-ul pus în casă la început (propus din raportarea anterioară)</span></dt>
                <dd>
                    @if ($draft)
                        <input type="text" inputmode="decimal" wire:model.live.debounce.700ms="opening_float" placeholder="0" class="{{ $input }} w-32 text-right">
                    @else
                        <span class="text-ink">{{ $money($fig->opening_float) }} lei</span>
                    @endif
                </dd>
            </div>
            <div class="py-2.5 flex items-center justify-between gap-3">
                <dt class="text-ink-soft">+ Încasat din intrări (cash)</dt>
                <dd class="text-ink">{{ $money($fig->cash_entries) }} lei</dd>
            </div>
            <div class="py-2.5 flex items-center justify-between gap-3">
                <dt class="text-ink-soft">+ Încasat din tokeni (cash)</dt>
                <dd class="text-ink">{{ $money($fig->cash_tokens) }} lei</dd>
            </div>
            <div class="py-2.5">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink">− Predat / scos din casă</dt>
                    <dd>
                        @if ($draft)
                            <input type="text" inputmode="decimal" wire:model.live.debounce.700ms="handed_over" placeholder="0" class="{{ $input }} w-32 text-right">
                        @else
                            <span class="text-ink">{{ $money($fig->handed_over) }} lei</span>
                        @endif
                    </dd>
                </div>
                @if ($draft)
                    <input type="text" wire:model.blur="handed_note" maxlength="255" placeholder="Notă (opțional): cui s-a predat, de ce" class="{{ $input }} mt-2 w-full">
                @elseif ($report->handed_note)
                    <p class="mt-1 text-xs text-ink-soft">{{ $report->handed_note }}</p>
                @endif
            </div>
            <div class="py-2.5 flex items-center justify-between gap-3">
                <dt class="font-semibold text-ink">= Cash așteptat</dt>
                <dd class="font-semibold text-ink">{{ $money($fig->expected_cash) }} lei</dd>
            </div>
            <div class="py-2.5 flex items-center justify-between gap-3">
                <dt class="font-semibold text-ink">Cash numărat</dt>
                <dd>
                    @if ($draft)
                        <input type="text" inputmode="decimal" wire:model.live.debounce.700ms="counted_cash" placeholder="0" class="{{ $input }} w-32 text-right">
                    @else
                        <span class="font-semibold text-ink">{{ $fig->counted_cash === null ? '—' : $money($fig->counted_cash).' lei' }}</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    {{-- 2. Alte metode --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">2. Alte metode de plată</h3>
        <p class="mt-1 text-xs text-ink-soft">Totaluri așteptate din intrări și tokeni (nu se numără fizic). Poți adăuga o notă, de exemplu „verificat în extrasul de card”.</p>

        <div class="mt-3 space-y-2">
            @forelse ($fig->other_methods as $m)
                <div wire:key="method-{{ $m['key'] }}" class="rounded-xl border border-border px-4 py-3">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-ink">{{ $m['label'] }}</span>
                        <span class="font-medium text-ink">{{ $money($m['amount']) }} lei</span>
                    </div>
                    @if ($draft)
                        <input type="text" wire:model.blur="method_notes.{{ $m['key'] }}" maxlength="255" placeholder="Notă (opțional)" class="{{ $input }} mt-2 w-full">
                    @elseif (! empty($m['note']))
                        <p class="mt-1 text-xs text-ink-soft">{{ $m['note'] }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-ink-soft">Nicio încasare cu altă metodă decât cash.</p>
            @endforelse
        </div>
    </div>

    {{-- 3. Activitate --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">3. Activitate în sesiune</h3>

        <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl bg-bg px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Intrări</div>
                <div class="text-lg font-semibold text-ink">{{ $fig->entries_count }}</div>
                <div class="text-[11px] text-ink-soft">din care {{ $fig->entries_free }} gratuite</div>
            </div>
            <div class="rounded-xl bg-bg px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Încasat din intrări</div>
                <div class="text-lg font-semibold text-ink">{{ $money($fig->entries_revenue) }}</div>
                <div class="text-[11px] text-ink-soft">lei, toate metodele</div>
            </div>
            <div class="rounded-xl bg-bg px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Tokeni vânduți</div>
                <div class="text-lg font-semibold text-ink">{{ number_format($fig->tokens_sold, 0, ',', '.') }}</div>
                <div class="text-[11px] text-ink-soft">în {{ $fig->token_sales }} {{ $fig->token_sales === 1 ? 'vânzare' : 'vânzări' }}</div>
            </div>
            <div class="rounded-xl bg-bg px-3 py-2.5">
                <div class="text-[11px] text-ink-soft">Încasat din tokeni</div>
                <div class="text-lg font-semibold text-ink">{{ $money($fig->tokens_amount) }}</div>
                <div class="text-[11px] text-ink-soft">lei, toate metodele</div>
            </div>
        </div>

        @if ($fig->tickets)
            <ul class="mt-3 text-sm text-ink-soft space-y-1">
                @foreach ($fig->tickets as $t)
                    <li wire:key="ticket-{{ $loop->index }}">{{ $t['name'] }}: <span class="text-ink">{{ $t['count'] }}</span> × · {{ $money($t['revenue']) }} lei</li>
                @endforeach
            </ul>
        @endif

        @if ($fig->entries_cancelled > 0 || $fig->token_sales_cancelled > 0)
            <p class="mt-3 text-xs text-ink-soft">
                Anulate în sesiune (nu intră în totaluri):
                {{ $fig->entries_cancelled }} {{ $fig->entries_cancelled === 1 ? 'intrare' : 'intrări' }},
                {{ $fig->token_sales_cancelled }} {{ $fig->token_sales_cancelled === 1 ? 'vânzare de tokeni' : 'vânzări de tokeni' }}.
            </p>
        @endif
    </div>

    {{-- Notă --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Notă</h3>
        @if ($draft)
            <textarea wire:model.blur="note" rows="3" maxlength="2000" placeholder="Observații despre seară, diferențe, incidente (opțional)" class="{{ $input }} mt-3 w-full"></textarea>
        @else
            <p class="mt-2 text-sm text-ink">{{ $report->note ?: '—' }}</p>
        @endif
    </div>

    {{-- Acțiuni (draft) --}}
    @if ($draft)
        <div class="rounded-2xl border border-border bg-surface p-5">
            @if ($error)
                <x-alert type="error" class="mb-3" dismiss-prop="error" :dismiss-value="null">{{ $error }}</x-alert>
            @endif
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-ink-soft">
                    @if ($savedAt) Salvat automat la {{ $savedAt }}. @else Se salvează automat. @endif
                    Sesiunea rămâne deschisă până finalizezi.
                </p>
                <div class="flex items-center gap-2">
                    <x-btn variant="neutral" :href="route('admin.reception.reports.index')" wire:navigate>Înapoi la listă</x-btn>
                    <x-btn variant="primary" wire:click="askFinalize" wire:loading.attr="disabled" wire:target="askFinalize">Finalizează</x-btn>
                </div>
            </div>
        </div>
    @endif

    {{-- Dialog de finalizare --}}
    @if ($confirming)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink/40" wire:click="cancelFinalize"></div>
            <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
                <h3 class="text-base font-semibold text-ink">Finalizezi raportarea?</h3>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Cash așteptat</dt><dd class="text-ink">{{ $money($fig->expected_cash) }} lei</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Cash numărat</dt><dd class="text-ink">{{ $money($fig->counted_cash) }} lei</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Diferență</dt><dd class="font-semibold {{ $diffOk ? 'text-success' : 'text-warning' }}">{{ $signed($diff) }} lei</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Intrări · tokeni vânduți</dt><dd class="text-ink">{{ $fig->entries_count }} · {{ number_format($fig->tokens_sold, 0, ',', '.') }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-ink-soft">Ireversibil: valorile se îngheață, iar sesiunea se închide — intrările și vânzările ei nu se mai pot anula. Următoarea înregistrare deschide o sesiune nouă.</p>
                <div class="mt-5 flex items-center justify-end gap-3">
                    <button type="button" wire:click="cancelFinalize" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Mai verific</button>
                    <x-btn variant="primary" wire:click="finalize" wire:loading.attr="disabled" wire:target="finalize">Finalizează</x-btn>
                </div>
            </div>
        </div>
    @endif
</div>
