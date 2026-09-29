{{--
    DXA: adaugat (Participanți). Alegerea participanților (căutare, chip-uri, „+ Participant nou”). Se include din formularele care
    folosesc trait-ul PicksParticipants. Variabile: $single (un singur client), $label, $hint (opțional), plus datele din
    participantPickerData() și proprietățile publice ale componentei ($participantIds, $participantSearch, $newParticipant, $participantError).
--}}
@php
    // DXA: în aplicațiile PWA (scannable) se arată creditele participantului (dacă are), nu numărul de intrări; „+ Participant nou” se poate ascunde ($allowNew = false).
    $inApp = $scannable ?? false;
    $allowNew = $allowNew ?? true;
    $creditsLabel = fn ($p) => (float) $p->credit_balance > 0 ? number_format((float) $p->credit_balance, 2, ',', '.').' lei credite' : null;
@endphp
<div>
    <label class="block text-sm font-medium text-ink mb-1.5">
        {{ $label ?? ($single ?? false ? 'Participant' : 'Participanți') }}
        @if (! empty($hint))
            <span class="font-normal text-ink-soft">({{ $hint }})</span>
        @endif
    </label>

    @if ($chosenParticipants->isNotEmpty())
        <div class="mb-2 space-y-2">
            @foreach ($chosenParticipants as $cp)
                @php
                    $isDupe = isset($participantDupes[$cp->id]);
                    $loyal = $participantLoyaltyReady[$cp->id] ?? false;
                @endphp
                <div wire:key="chosen-{{ $cp->id }}" class="rounded-xl border {{ $isDupe ? 'border-danger bg-danger/10' : ($loyal ? 'border-warning bg-warning/10' : 'border-border bg-bg') }} px-3 py-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-ink truncate">{{ $cp->name }}</div>
                            <div class="text-xs text-ink-soft">{{ $cp->phone }}@if ($inApp) @if ($creditsLabel($cp)) · <span class="font-medium text-success">{{ $creditsLabel($cp) }}</span>@endif @else · {{ $participantVisits[$cp->id] ?? 0 }} intrări @endif</div>
                        </div>
                        <button type="button" wire:click="removeParticipant({{ $cp->id }})" aria-label="Scoate participantul"
                                class="shrink-0 h-7 w-7 rounded-full text-ink-soft hover:bg-border">×</button>
                    </div>

                    {{-- Mențiunile, pe rând nou --}}
                    @if ($isDupe)
                        <div class="mt-2 text-xs font-medium text-danger">A intrat deja în sesiunea curentă, la {{ $participantDupes[$cp->id] }}</div>
                    @elseif ($loyal)
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-medium text-warning">🎁 Intrare gratis disponibilă</span>
                            <button type="button" wire:click="applyLoyaltyFreeEntry({{ $cp->id }})"
                                    class="rounded-full bg-warning hover:bg-warning/90 text-white text-xs font-medium px-3 py-1.5 transition-colors">
                                Aplică intrarea gratis
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="flex items-center gap-2">
        <input type="text" wire:model.live.debounce.300ms="participantSearch" placeholder="Caută după telefon sau nume"
               class="flex-1 min-w-0 rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
        @if ($scannable ?? false)
            @include('livewire.reception._scanner')
        @endif
        @if ($allowNew)
            <x-btn variant="neutral" size="icon" outline tooltip="Participant nou" wire:click="toggleNewParticipant" class="shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            </x-btn>
        @endif
    </div>

    @if ($participantResults->isNotEmpty())
        <div class="mt-2 space-y-1.5">
            @foreach ($participantResults as $pr)
                <button type="button" wire:key="found-{{ $pr->id }}" wire:click="addParticipant({{ $pr->id }})"
                        class="w-full flex items-center justify-between gap-3 rounded-lg border {{ ($participantLoyaltyReady[$pr->id] ?? false) ? 'border-warning bg-warning/10' : 'border-border bg-surface' }} px-3 py-2 text-left text-sm hover:bg-bg">
                    <span class="min-w-0 truncate"><span class="font-medium text-ink">{{ $pr->name }}</span> <span class="text-ink-soft">{{ $pr->phone }}</span></span>
                    <span class="shrink-0 text-xs {{ ($participantLoyaltyReady[$pr->id] ?? false) ? 'text-warning font-medium' : 'text-ink-soft' }}">
                        @if ($participantLoyaltyReady[$pr->id] ?? false) 🎁 intrare gratis @endif
                        @if ($inApp)
                            @if ($creditsLabel($pr)) <span class="font-medium text-success">{{ $creditsLabel($pr) }}</span> @endif
                        @else
                            {{ $participantVisits[$pr->id] ?? 0 }} intrări
                        @endif
                    </span>
                </button>
            @endforeach
        </div>
    @elseif (mb_strlen(trim($participantSearch)) >= 2)
        <p class="mt-2 text-xs text-ink-soft">Niciun participant găsit.</p>
    @endif

    @if ($participantError && ! $newParticipant)
        <p class="mt-2 text-sm text-danger">{{ $participantError }}</p>
    @endif

    {{-- „+ Participant nou”, ca popup (nu inline) --}}
    @if ($newParticipant && $allowNew)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink/40" wire:click="toggleNewParticipant"></div>
            <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-ink">Participant nou</h3>
                    <button type="button" wire:click="toggleNewParticipant" aria-label="Închide"
                            class="h-7 w-7 inline-flex items-center justify-center rounded-lg text-ink-soft hover:bg-bg">×</button>
                </div>
                <div class="mt-3 space-y-2">
                    <input type="text" wire:model="newName" maxlength="120" placeholder="Nume"
                           class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                    <input type="text" inputmode="tel" wire:model="newPhone" maxlength="20" placeholder="Telefon (ex. 0722 123 456)"
                           x-on:keydown.enter.prevent="$wire.createParticipant()"
                           class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                    @if ($participantError)
                        <p class="text-sm text-danger">{{ $participantError }}</p>
                    @endif
                </div>
                <div class="mt-4 flex items-center justify-end gap-3">
                    <button type="button" wire:click="toggleNewParticipant" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Renunță</button>
                    <x-btn variant="primary" wire:click="createParticipant">Adaugă</x-btn>
                </div>
            </div>
        </div>
    @endif
</div>
