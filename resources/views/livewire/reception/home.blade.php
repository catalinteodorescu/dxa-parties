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
        {{-- Petrecerea selectată --}}
        <div class="rounded-2xl bg-primary text-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-white/80">Petrecere selectată</div>
                    <div class="text-lg font-semibold truncate">{{ $party->name }}</div>
                </div>
                <a href="{{ route('receptie.party') }}" wire:navigate class="shrink-0 rounded-full bg-white/20 hover:bg-white/30 px-3.5 py-1.5 text-sm font-medium transition-colors">Schimbă</a>
            </div>
        </div>

        {{-- Acțiuni (ecranele lor vin în etapele următoare) --}}
        <div class="space-y-2.5">
            <livewire:reception.quick-scan />

            <a href="{{ route('receptie.entry') }}" wire:navigate class="{{ $tile }} hover:bg-bg">
                <span class="w-11 h-11 rounded-xl bg-primary-soft text-primary flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg>
                </span>
                <span class="flex-1"><span class="block text-base font-semibold text-ink">Intrare</span><span class="block text-xs text-ink-soft">Bilete, plată, participant</span></span>
                <span class="text-ink-soft">›</span>
            </a>

            @if ($tokensSellable)
                <a href="{{ route('receptie.tokens') }}" wire:navigate class="{{ $tile }} hover:bg-bg">
                    <span class="w-11 h-11 rounded-xl bg-warning/10 text-warning flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                    </span>
                    <span class="flex-1"><span class="block text-base font-semibold text-ink">Vânzare tokeni</span><span class="block text-xs text-ink-soft">Tokeni fizici pentru bar</span></span>
                    <span class="text-ink-soft">›</span>
                </a>
            @endif

            @if ($creditsSellable)
                <a href="{{ route('receptie.credits') }}" wire:navigate class="{{ $tile }} hover:bg-bg">
                    <span class="w-11 h-11 rounded-xl bg-success-soft text-success flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </span>
                    <span class="flex-1"><span class="block text-base font-semibold text-ink">Vânzare credite</span><span class="block text-xs text-ink-soft">Credite în portofelul participantului</span></span>
                    <span class="text-ink-soft">›</span>
                </a>
            @endif

            <hr class="!my-5 border-border">

            <a href="{{ route('receptie.recent') }}" wire:navigate class="{{ $tile }} hover:bg-bg">
                <span class="w-11 h-11 rounded-xl bg-bg text-ink-soft flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
                </span>
                <span class="flex-1"><span class="block text-base font-semibold text-ink">Tranzacții</span><span class="block text-xs text-ink-soft">Vezi și anulează, cât sesiunea e deschisă</span></span>
                <span class="text-ink-soft">›</span>
            </a>

            <a href="{{ route('receptie.report') }}" wire:navigate class="{{ $tile }} hover:bg-bg">
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
                        <div class="text-[11px] text-ink-soft">Intrări</div>
                        <div class="text-xl font-semibold text-ink">{{ $summary->entries_count }}</div>
                        @if ($summary->entries_free > 0)<div class="text-[11px] text-ink-soft">din care {{ $summary->entries_free }} gratuite</div>@endif
                    </div>
                    <div class="rounded-xl border border-border px-3 py-2.5">
                        <div class="text-[11px] text-ink-soft">Încasat</div>
                        <div class="text-xl font-semibold text-ink">{{ $lei($collected) }}</div>
                    </div>
                    <div class="rounded-xl border border-border px-3 py-2.5">
                        <div class="text-[11px] text-ink-soft">Tokeni vânduți</div>
                        <div class="text-xl font-semibold text-ink">{{ $summary->tokens_sold }}</div>
                    </div>
                    <div class="rounded-xl border border-border px-3 py-2.5">
                        <div class="text-[11px] text-ink-soft">Credite vândute</div>
                        <div class="text-xl font-semibold text-ink">{{ $lei($summary->credits_amount) }}</div>
                    </div>
                </div>
            @else
                <p class="mt-2 text-sm text-ink-soft">Nicio operațiune înregistrată încă la această petrecere.</p>
            @endif
        </div>
    @endif
</div>
