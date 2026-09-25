@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $input = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y H:i') : '—';
    $anonymized = $participant->isAnonymized();
@endphp
<div class="max-w-3xl">
    <div class="mb-5">
        <a href="{{ route('admin.participants.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Înapoi la Participanți
        </a>
    </div>

    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">{{ $participant->name }}</h2>
        <p class="mt-1 text-sm text-ink-soft">
            {{ $participant->phone ?: 'fără telefon' }} · adăugat {{ $participant->created_at->format('d.m.Y') }}
            @if ($anonymized) · anonimizat {{ $participant->anonymized_at->format('d.m.Y') }} @endif
        </p>
    </div>

    @if ($message)
        <x-alert type="success" class="mb-4">{{ $message }}</x-alert>
    @endif
    @if ($error)
        <x-alert type="error" class="mb-4">{{ $error }}</x-alert>
    @endif

    {{-- Numărători --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Intrări valabile</div>
            <div class="text-lg font-semibold text-ink">{{ $validEntries }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Plătite</div>
            <div class="text-lg font-semibold text-ink">{{ $paidEntries }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Gratuite</div>
            <div class="text-lg font-semibold text-ink">{{ $freeEntries }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Ultima intrare</div>
            <div class="text-sm font-semibold text-ink mt-1">{{ $fmt($lastAt) }}</div>
        </div>
    </div>

    {{-- Cheltuieli --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Cheltuit la bar</div>
            <div class="text-lg font-semibold text-ink">{{ $money($spend->bar_spent) }} <span class="text-xs font-normal text-ink-soft">lei</span></div>
            <div class="text-[11px] text-ink-soft">{{ $spend->bar_sales }} {{ $spend->bar_sales === 1 ? 'bon' : 'bonuri' }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Tokeni cumpărați</div>
            <div class="text-lg font-semibold text-ink">{{ number_format($spend->tokens, 0, ',', '.') }}</div>
            <div class="text-[11px] text-ink-soft">{{ $money($spend->tokens_amount) }} lei</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Plătit la intrări</div>
            <div class="text-lg font-semibold text-ink">{{ $money($spend->entries_amount) }} <span class="text-xs font-normal text-ink-soft">lei</span></div>
            <div class="text-[11px] text-ink-soft">{{ $spend->entries_paid }} plătite</div>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
            <div class="text-[11px] text-ink-soft">Prima intrare</div>
            <div class="text-sm font-semibold text-ink mt-1">{{ $fmt($firstAt) }}</div>
        </div>
    </div>

    {{-- Pe petreceri --}}
    @if ($spend->parties->isNotEmpty())
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Pe petreceri</h3>
            <p class="mt-1 text-xs text-ink-soft">Cheltuit la bar = plățile vânzărilor finalizate, fără beneficii (include plățile cu tokeni, la valoarea lor în lei). Nu se adună cu tokenii cumpărați.</p>
            <div class="mt-3 space-y-2">
                @foreach ($spend->parties as $row)
                    <div wire:key="sp-{{ $row->party_id ?? 'none' }}" class="rounded-xl border border-border px-3.5 py-2">
                        <div class="text-sm font-medium text-ink">
                            {{ $row->party }}
                            @if ($row->date) <span class="font-normal text-ink-soft">· {{ $row->date->format('d.m.Y') }}</span> @endif
                        </div>
                        <div class="mt-0.5 text-xs text-ink-soft">
                            Intrări {{ $row->entries }} ({{ $money($row->entries_amount) }} lei)
                            · Bar {{ $money($row->bar_spent) }} lei ({{ $row->bar_sales }} {{ $row->bar_sales === 1 ? 'bon' : 'bonuri' }})
                            · Tokeni {{ number_format($row->tokens, 0, ',', '.') }}@if ($row->tokens > 0) ({{ $money($row->tokens_amount) }} lei)@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Date --}}
    @unless ($anonymized)
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Date</h3>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-5 gap-2">
                <input type="text" wire:model="name" maxlength="120" placeholder="Nume" class="sm:col-span-2 {{ $input }}">
                <input type="text" inputmode="tel" wire:model="phone" maxlength="20" placeholder="Telefon" x-on:keydown.enter.prevent="$wire.save()" class="sm:col-span-2 {{ $input }}">
                <x-btn variant="warning" wire:click="save">Salvează</x-btn>
            </div>
            <p class="mt-2 text-xs text-ink-soft">Telefonul e cheia participantului: când își face cont în aplicație cu același număr, istoricul se păstrează.</p>
        </div>
    @endunless

    {{-- Istoric --}}
    <div class="{{ $card }} mb-4">
        <h3 class="text-sm font-semibold text-ink">Istoric intrări</h3>
        @if ($entries->isEmpty())
            <p class="mt-2 text-sm text-ink-soft">Nicio intrare încă.</p>
        @else
            <div class="mt-3 space-y-2">
                @foreach ($entries as $e)
                    <div wire:key="pe-{{ $e->id }}" class="rounded-xl border border-border px-3.5 py-2 {{ $e->isCancelled() ? 'bg-bg' : 'bg-surface' }}">
                        <div class="text-sm font-medium {{ $e->isCancelled() ? 'text-ink-soft line-through' : 'text-ink' }}">
                            {{ $e->party?->name ?? 'Petrecere ștearsă' }} · {{ $e->ticket_type }} · {{ $e->price_paid > 0 ? $money($e->price_paid).' lei' : 'gratuit' }}
                        </div>
                        <div class="mt-0.5 text-xs text-ink-soft">
                            {{ $e->entered_at->format('d.m.Y H:i') }}
                            @if ($e->isCancelled()) · <span class="text-danger">anulată: {{ $e->cancel_reason }}</span> @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($totalEntries > $entries->count())
                <p class="mt-2 text-[11px] text-ink-soft">Se afișează ultimele {{ $entries->count() }} din {{ $totalEntries }}.</p>
            @endif
        @endif
    </div>

    {{-- Ștergere / anonimizare --}}
    <div class="{{ $card }}">
        <h3 class="text-sm font-semibold text-ink">Date personale</h3>
        @if ($totalEntries === 0)
            <p class="mt-1 text-xs text-ink-soft leading-relaxed">Participantul n-are nicio intrare, deci poate fi șters complet.</p>
            <div class="mt-3">
                <x-btn variant="danger" outline wire:click="delete" wire:confirm="Ștergi definitiv acest participant?">Șterge participantul</x-btn>
            </div>
        @elseif (! $anonymized)
            <p class="mt-1 text-xs text-ink-soft leading-relaxed">
                Are intrări înregistrate, deci nu se șterge. Poți să-l anonimizezi: numele și telefonul se golesc, iar intrările rămân doar ca număr în statistici. Nu se poate anula.
            </p>
            <div class="mt-3">
                <x-btn variant="danger" outline wire:click="anonymize" wire:confirm="Anonimizezi acest participant? Numele și telefonul se șterg definitiv.">Anonimizează</x-btn>
            </div>
        @else
            <p class="mt-1 text-xs text-ink-soft">Participantul a fost anonimizat; intrările rămân doar ca număr în statistici.</p>
        @endif
    </div>
</div>
