@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $lei = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $signed = fn ($n) => ((float) $n > 0 ? '+' : ((float) $n < 0 ? '−' : '')).number_format(abs((float) $n), 2, ',', '.').' lei';
    $typeClass = [
        \App\Models\CreditTransaction::LOAD => 'bg-success-soft text-success',
        \App\Models\CreditTransaction::PAYMENT => 'bg-primary-soft text-primary',
        \App\Models\CreditTransaction::REFUND => 'bg-info-soft text-info',
        \App\Models\CreditTransaction::ADJUSTMENT => 'bg-bg text-ink-soft',
    ];
    $sourceLoadLabels = \App\Models\CreditTransaction::SOURCE_LABELS;
    $loadedMax = max(array_merge([0.01], array_values($loadedBySource)));
    $spentMax = max(0.01, (float) $summary->spent_bar, (float) $summary->spent_entry);
@endphp
<div class="max-w-3xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">Credite · Sumar</h2>
        <p class="mt-1 text-sm text-ink-soft">
            @if ($creditsOn)
                Active · 1 credit = 1 leu ·
                {{ $purchasable ? 'participanții pot cumpăra credite' : 'cumpărarea de credite e oprită' }} ·
            @else
                <span class="text-warning font-medium">Oprite în Setări</span> (istoricul rămâne vizibil) ·
            @endif
            <a href="{{ route('admin.settings.index') }}" wire:navigate class="text-primary hover:underline">Setări › Metode de plată</a>
        </p>
    </div>

    {{-- Sumar --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
        <div class="{{ $card }} col-span-2 sm:col-span-1 border-primary/40 bg-primary-soft/40">
            <div class="text-[11px] text-ink-soft">Credite active</div>
            <div class="text-2xl font-semibold text-ink">{{ $lei($summary->active) }}</div>
            <div class="mt-0.5 text-[11px] text-ink-soft">soldul tuturor participanților · nefolosit încă</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Încărcat</div>
            <div class="text-2xl font-semibold text-ink">{{ $lei($summary->loaded) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Cheltuit</div>
            <div class="text-2xl font-semibold text-ink">{{ $lei($summary->spent) }}</div>
            <div class="mt-0.5 text-[11px] text-ink-soft">bar {{ $lei($summary->spent_bar) }} · intrare {{ $lei($summary->spent_entry) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Rambursat</div>
            <div class="text-2xl font-semibold text-ink">{{ $lei($summary->refunded) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Ajustat manual (net)</div>
            <div class="text-2xl font-semibold text-ink">{{ $signed($summary->adjusted) }}</div>
        </div>
    </div>

    {{-- Distribuții --}}
    <div class="grid sm:grid-cols-2 gap-3 mb-4">
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Încărcat pe sursă</h3>
            <div class="mt-3 space-y-3">
                @foreach ($loadedBySource as $source => $amount)
                    <div wire:key="ls-{{ $source }}">
                        <div class="flex items-baseline justify-between gap-2 text-xs">
                            <span class="text-ink-soft">{{ $sourceLoadLabels[$source] ?? $source }}</span>
                            <span class="font-medium text-ink">{{ $lei($amount) }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-bg overflow-hidden">
                            <div class="h-full rounded-full bg-primary" style="width: {{ round($amount / $loadedMax * 100, 1) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Cheltuit pe destinație</h3>
            <div class="mt-3 space-y-3">
                @foreach (['Bar' => $summary->spent_bar, 'Intrare' => $summary->spent_entry] as $label => $amount)
                    <div wire:key="sp-{{ $label }}">
                        <div class="flex items-baseline justify-between gap-2 text-xs">
                            <span class="text-ink-soft">{{ $label }}</span>
                            <span class="font-medium text-ink">{{ $lei($amount) }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-bg overflow-hidden">
                            <div class="h-full rounded-full bg-info" style="width: {{ round($amount / $spentMax * 100, 1) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Solduri mari --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Solduri mari</h3>
        @if ($top['rows']->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Niciun participant nu are credite acum.</p>
        @else
            <p class="mt-1 text-xs text-ink-soft">Primii {{ $top['rows']->count() }} dețin {{ $lei($top['sum']) }} — {{ number_format($top['share'], 1, ',', '.') }}% din creditele active.</p>
            <div class="mt-3 space-y-1.5">
                @foreach ($top['rows'] as $p)
                    <a wire:key="top-{{ $p->id }}" href="{{ route('admin.participants.show', $p->id) }}" wire:navigate
                       class="flex items-center justify-between gap-3 rounded-xl border border-border px-3.5 py-2 hover:border-primary/40 hover:bg-primary-soft/40 transition-colors">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-ink truncate">{{ $p->name }}</span>
                            <span class="block text-xs text-ink-soft">{{ $p->phone ?: 'fără telefon' }}</span>
                        </span>
                        <span class="text-sm font-semibold text-ink shrink-0">{{ $lei($p->credit_balance) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Istoric --}}
    <div class="{{ $card }}">
        <div class="flex flex-col gap-3">
            <h3 class="text-sm font-semibold text-ink">Istoric mișcări</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <x-select wire:model="filterType" live :options="$typeOptions" />
                <x-select wire:model="filterSource" live :options="$sourceOptions" />
                <x-select wire:model="filterParty" live :options="$partyOptions" />
            </div>
        </div>

        @if ($transactions->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Nicio mișcare de credite înregistrată{{ ($filterType || $filterSource || $filterParty) ? ' pentru filtrele alese' : ' încă' }}.</p>
        @else
            <div class="mt-3 space-y-2">
                @foreach ($transactions as $t)
                    @php
                        $ref = $t->reference;
                        $partyName = $t->party?->name
                            ?? ($ref instanceof \App\Models\Sale ? $ref->group?->party?->name : ($ref instanceof \App\Models\PartyEntry ? $ref->party?->name : null));
                        $origin = match (true) {
                            $t->reference_type === \App\Models\Sale::class => 'Vânzare la bar #'.$t->reference_id,
                            $t->reference_type === \App\Models\PartyEntry::class => 'Intrare',
                            default => null,
                        };
                        $cancelled = $t->isCancelled();
                    @endphp
                    <div wire:key="tx-{{ $t->id }}" class="rounded-xl border border-border px-3.5 py-2.5 {{ $cancelled ? 'bg-bg' : 'bg-surface' }}">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 flex-wrap min-w-0">
                                <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $cancelled ? 'bg-bg text-ink-soft line-through' : ($typeClass[$t->type] ?? 'bg-bg text-ink-soft') }}">{{ $t->typeLabel() }}</span>
                                @if ($t->participant && ! $t->participant->isAnonymized())
                                    <a href="{{ route('admin.participants.show', $t->participant_id) }}" wire:navigate class="text-sm font-medium {{ $cancelled ? 'text-ink-soft line-through' : 'text-ink hover:text-primary' }}">{{ $t->participant->name }}</a>
                                @else
                                    <span class="text-sm text-ink-soft">Participant anonimizat</span>
                                @endif
                            </div>
                            <span class="text-sm font-semibold shrink-0 {{ $cancelled ? 'text-ink-soft line-through' : ((float) $t->amount >= 0 ? 'text-success' : 'text-ink') }}">{{ $signed($t->amount) }}</span>
                        </div>
                        <div class="mt-0.5 text-xs text-ink-soft">
                            {{ $t->occurred_at->format('d.m.Y H:i') }} · {{ $t->sourceLabel() }}@if ($origin) · {{ $origin }}@endif@if ($partyName) · {{ $partyName }}@endif
                            @if ($t->createdBy) · {{ $t->createdBy->name }}@endif
                        </div>
                        @if ($t->note)
                            <div class="mt-0.5 text-xs text-ink-soft">{{ $t->note }}</div>
                        @endif
                        @if ($cancelled)
                            <div class="mt-0.5 text-xs text-danger">anulată: {{ $t->cancel_reason }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="mt-3">
                {{ $transactions->onEachSide(1)->links('pagination.dxa') }}
            </div>
        @endif
    </div>
</div>
