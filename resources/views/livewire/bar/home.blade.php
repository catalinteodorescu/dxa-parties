@php
    $lei = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $tile = 'flex items-center gap-4 rounded-2xl border border-border bg-surface px-4 py-4';
@endphp
<div class="space-y-4">

    <x-flash />

    @if (! $party)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">
            Nu există nicio petrecere la care să lucrezi acum (publicată, activă și neîncheiată).
        </div>
    @else
        {{-- Petrecerea selectată (doar numele) --}}
        <div class="rounded-2xl bg-primary text-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-white/80">Petrecere selectată</div>
                    <div class="text-lg font-semibold truncate">{{ $party->name }}</div>
                </div>
                <a href="{{ route('bar.party') }}" wire:navigate class="shrink-0 rounded-full bg-white/20 hover:bg-white/30 px-3.5 py-1.5 text-sm font-medium transition-colors">Schimbă</a>
            </div>
        </div>

        {{-- Acțiuni --}}
        <div class="space-y-2.5">
            <a href="{{ route('bar.sale') }}" wire:navigate class="{{ $tile }} hover:bg-bg">
                <span class="w-11 h-11 rounded-xl bg-primary-soft text-primary flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 22h8"/><path d="M12 11v11"/><path d="m19 3-7 8-7-8Z"/></svg>
                </span>
                <span class="flex-1"><span class="block text-base font-semibold text-ink">Vânzare</span><span class="block text-xs text-ink-soft">Produse, participant, plată</span></span>
                <span class="text-ink-soft">›</span>
            </a>

            <hr class="!my-5 border-border">

            <a href="{{ route('bar.recent') }}" wire:navigate class="{{ $tile }} hover:bg-bg">
                <span class="w-11 h-11 rounded-xl bg-bg text-ink-soft flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                </span>
                <span class="flex-1"><span class="block text-base font-semibold text-ink">Vânzări recente</span><span class="block text-xs text-ink-soft">Vezi bonurile, anulează o greșeală</span></span>
                <span class="text-ink-soft">›</span>
            </a>

            <a href="{{ route('bar.report') }}" wire:navigate class="{{ $tile }} hover:bg-bg">
                <span class="w-11 h-11 rounded-xl bg-bg text-ink-soft flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M8 18v-1"/><path d="M12 18v-6"/><path d="M16 18v-3"/></svg>
                </span>
                <span class="flex-1"><span class="block text-base font-semibold text-ink">Raportare</span><span class="block text-xs text-ink-soft">{{ $reportSubmitted ? 'Trimisă, așteaptă adminul' : 'Numără casa și trimite raportul' }}</span></span>
                <span class="text-ink-soft">›</span>
            </a>
        </div>

        {{-- Rezumat sesiune --}}
        <hr class="!my-5 border-border">
        <div class="rounded-2xl border border-border bg-surface p-4">
            <h2 class="text-sm font-semibold text-ink">Sesiunea de azi</h2>
            @if ($summary)
                <div class="mt-3 grid grid-cols-2 gap-2.5">
                    <div class="rounded-xl border border-border px-3 py-2.5">
                        <div class="text-[11px] text-ink-soft">Bonuri</div>
                        <div class="text-xl font-semibold text-ink">{{ $summary->sales_count }}</div>
                    </div>
                    <div class="rounded-xl border border-border px-3 py-2.5">
                        <div class="text-[11px] text-ink-soft">Vândut</div>
                        <div class="text-xl font-semibold text-ink">{{ $lei($summary->revenue) }}</div>
                    </div>
                </div>
                @if ($summary->methods)
                    <div class="mt-3 space-y-1 text-sm">
                        <div class="text-[11px] uppercase tracking-wide text-ink-soft/70">Încasat pe metodă</div>
                        @foreach ($summary->methods as $key => $m)
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-ink-soft">{{ \App\Support\PaymentMethods::label($key) }}@if ($m['tokens'] !== null) · {{ $m['tokens'] }} tokeni @endif</span>
                                <span class="font-medium text-ink">{{ $lei($m['amount']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="mt-2 text-sm text-ink-soft">Nicio vânzare înregistrată încă la această petrecere.</p>
            @endif
        </div>
    @endif
</div>
