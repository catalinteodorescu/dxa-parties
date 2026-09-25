@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
    $signed = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs((float) $n), 0, ',', '.');
    $modeLabel = \App\Support\PaymentMethods::TOKEN_MODES[$mode] ?? $mode;
    $typeClass = [
        'sold' => 'bg-primary-soft text-primary',
        'adjustment' => 'bg-info/10 text-info',
        'write_off' => 'bg-danger/10 text-danger',
    ];
@endphp
<div class="max-w-3xl">
    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="{{ route('admin.reception.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Înapoi la Intrări
        </a>
    </div>

    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">Recepție · Sumar tokeni</h2>
        <p class="mt-1 text-sm text-ink-soft">
            Stare: <span class="font-medium text-ink">{{ $modeLabel }}</span> · 1 token = {{ $money($rate) }} lei ·
            <a href="{{ route('admin.settings.index') }}" wire:navigate class="text-primary hover:underline">Setări › Metode de plată</a>
        </p>
    </div>

    @if ($message)
        <x-alert type="success" class="mb-4">{{ $message }}</x-alert>
    @endif
    @if ($error)
        <x-alert type="error" class="mb-4">{{ $error }}</x-alert>
    @endif

    {{-- Circulația --}}
    <div class="{{ $card }} mb-4">
        <div class="text-[11px] text-ink-soft">Tokeni în circulație</div>
        <div class="text-3xl font-semibold {{ $circulation < 0 ? 'text-warning' : 'text-ink' }}">{{ $int($circulation) }}</div>
        <p class="mt-1 text-xs text-ink-soft">
            Emiși net {{ $int($issued) }} (vânzări + ajustări − casări) − încasați la bar {{ $int($collected) }}.
        </p>
        @if ($circulation < 0)
            <p class="mt-2 text-xs text-warning leading-relaxed">
                La bar s-au încasat mai mulți tokeni decât cei înregistrați aici. Probabil ai tokeni vânduți înainte de acest modul:
                adaugă-i printr-o ajustare pozitivă („Stoc inițial”), ca să vezi circulația reală.
            </p>
        @endif
    </div>

    {{-- Ajustare --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Ajustare</h3>
        <p class="mt-1 text-xs text-ink-soft leading-relaxed">
            Pentru stocul inițial (tokeni vânduți înainte de acest modul) sau o corecție. Număr pozitiv adaugă în circulație, negativ scade. Motivul e obligatoriu și se loghează.
        </p>
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
            <input type="text" inputmode="numeric" wire:model="adjustTokens" placeholder="ex. 250 sau -10"
                   class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            <input type="text" wire:model="adjustReason" maxlength="255" placeholder="Motiv (ex. Stoc inițial)"
                   class="sm:col-span-2 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
        </div>
        <div class="mt-3">
            <x-btn variant="primary" wire:click="adjust">Înregistrează ajustarea</x-btn>
        </div>
    </div>

    {{-- Casare --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Casare</h3>
        <p class="mt-1 text-xs text-ink-soft leading-relaxed">
            Scoate din circulație tot ce a rămas ({{ $int(max($circulation, 0)) }} tokeni), de exemplu înainte de a opri tokenii. Tokenii casați nu se mai pot folosi la bar decât dacă îi readaugi printr-o ajustare.
        </p>
        <div class="mt-3 flex flex-col sm:flex-row gap-2">
            <input type="text" wire:model="writeOffReason" maxlength="255" placeholder="Motiv (obligatoriu)"
                   class="flex-1 min-w-0 rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            <x-btn variant="danger" wire:click="writeOff" wire:confirm="Casezi toți tokenii rămași în circulație?" :disabled="$circulation <= 0">Casează tokenii</x-btn>
        </div>
    </div>

    {{-- Ledger --}}
    <div class="{{ $card }}">
        <h3 class="text-sm font-semibold text-ink">Mișcări</h3>
        @if ($transactions->isEmpty())
            <p class="mt-2 text-sm text-ink-soft">Nicio mișcare înregistrată încă.</p>
        @else
            <div class="mt-3 space-y-2">
                @foreach ($transactions as $t)
                    <div wire:key="tx-{{ $t->id }}" class="rounded-xl border border-border px-3.5 py-2.5 {{ $t->isCancelled() ? 'bg-bg' : 'bg-surface' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $typeClass[$t->type] ?? 'bg-bg text-ink-soft' }}">{{ \App\Models\TokenTransaction::TYPES[$t->type] ?? $t->type }}</span>
                                    <span class="text-sm font-medium {{ $t->isCancelled() ? 'text-ink-soft line-through' : 'text-ink' }}">{{ $signed($t->tokens) }} tokeni</span>
                                    @if ($t->type === 'sold')
                                        <span class="text-sm text-ink-soft {{ $t->isCancelled() ? 'line-through' : '' }}">{{ $money($t->amount) }} lei</span>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-xs text-ink-soft">
                                    {{ $t->occurred_at->format('d.m.Y H:i') }}
                                    @if ($t->party) · {{ $t->party->name }} @endif
                                    @if ($t->participant) · <span class="text-ink">{{ $t->participant->name }}</span> @endif
                                    @if ($t->creator) · {{ $t->creator->name }} @endif
                                    @foreach ($t->payments as $pay)
                                        · {{ $methodLabels[$pay->method] ?? $pay->method }} {{ $money($pay->amount) }}
                                    @endforeach
                                </div>
                                @if ($t->note)
                                    <div class="mt-0.5 text-xs text-ink-soft">{{ $t->note }}</div>
                                @endif
                                @if ($t->isCancelled())
                                    <div class="mt-0.5 text-xs text-danger">Anulat: {{ $t->cancel_reason }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($transactions->count() >= 100)
                <p class="mt-2 text-[11px] text-ink-soft">Se afișează ultimele 100 de mișcări.</p>
            @endif
        @endif
    </div>
</div>
