@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
    $sourceClass = [
        \App\Models\LoyaltyStamp::SOURCE_ENTRY => 'bg-primary-soft text-primary',
        \App\Models\LoyaltyStamp::SOURCE_MANUAL_ADJUST => 'bg-info/10 text-info',
    ];
@endphp
<div class="max-w-3xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">Card de fidelitate · Sumar</h2>
        <p class="mt-1 text-sm text-ink-soft">
            @if ($loyaltyOn)
                Activ · {{ $stampsRequired }} ștampile până la intrarea gratis (pentru cardurile noi) ·
            @else
                <span class="text-warning font-medium">Oprit global</span> ·
            @endif
            <a href="{{ route('admin.settings.index') }}" wire:navigate class="text-primary hover:underline">Setări › Card de fidelitate</a>
        </p>
    </div>

    {{-- Sumar --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Participanți înrolați</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($enrolledCount) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Carduri active</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($activeCount) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Carduri complete</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($completedCount) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Ștampile din intrări</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($stampsFromEntries) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Ștampile ajustate manual</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($stampsManual) }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Intrări gratuite acordate</div>
            <div class="text-2xl font-semibold text-ink">{{ $int($freeEntriesGranted) }}</div>
        </div>
    </div>

    {{-- Aproape gata --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Aproape gata</h3>
        <p class="mt-1 text-xs text-ink-soft">Cardul activ e complet mai puțin ultimul cerc — următoarea intrare identificată e gratis.</p>
        @if ($almostReady->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Niciun card, momentan.</p>
        @else
            <div class="mt-3 space-y-1.5">
                @foreach ($almostReady as $c)
                    <a wire:key="ar-{{ $c->id }}" href="{{ route('admin.participants.show', $c->participant_id) }}" wire:navigate
                       class="flex items-center justify-between gap-3 rounded-xl border border-border px-3.5 py-2 hover:border-primary/40 hover:bg-primary-soft/40 transition-colors">
                        <span class="text-sm font-medium text-ink">{{ $c->participant->name }}</span>
                        <span class="text-xs text-ink-soft">{{ $c->participant->phone ?: 'fără telefon' }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Jurnal --}}
    <div class="{{ $card }}">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-sm font-semibold text-ink">Istoric ștampile</h3>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <x-select wire:model="filterSource" live :options="$sourceOptions" class="w-40" />
                <x-select wire:model="filterParty" live :options="$partyOptions" class="w-56" />
            </div>
        </div>

        @if ($stamps->isEmpty())
            <p class="mt-3 text-sm text-ink-soft">Nicio ștampilă înregistrată încă.</p>
        @else
            <div class="mt-3 space-y-2">
                @foreach ($stamps as $s)
                    <div wire:key="st-{{ $s->id }}" class="rounded-xl border border-border px-3.5 py-2.5 {{ $s->isVoided() ? 'bg-bg' : 'bg-surface' }}">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $s->isVoided() ? 'bg-bg text-ink-soft line-through' : ($sourceClass[$s->source] ?? 'bg-bg text-ink-soft') }}">{{ $s->sourceLabel() }}</span>
                            @if ($s->card?->participant)
                                <a href="{{ route('admin.participants.show', $s->card->participant_id) }}" wire:navigate class="text-sm font-medium {{ $s->isVoided() ? 'text-ink-soft line-through' : 'text-ink hover:text-primary' }}">
                                    {{ $s->card->participant->name }}
                                </a>
                            @endif
                            <span class="text-xs text-ink-soft">{{ $s->stamped_at->format('d.m.Y H:i') }}</span>
                            @if ($s->partyEntry?->party)
                                <span class="text-xs text-ink-soft">· {{ $s->partyEntry->party->name }}</span>
                            @endif
                        </div>
                        @if ($s->reason)
                            <div class="mt-0.5 text-xs text-ink-soft">{{ $s->reason }}@if ($s->createdBy) — {{ $s->createdBy->name }} @endif</div>
                        @endif
                        @if ($s->isVoided())
                            <div class="mt-0.5 text-xs text-danger">anulată: {{ $s->void_reason }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($stamps->count() >= 100)
                <p class="mt-2 text-[11px] text-ink-soft">Se afișează ultimele 100.</p>
            @endif
        @endif
    </div>
</div>
