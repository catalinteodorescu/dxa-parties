{{--
    DXA: adaugat (Participanți). Alegerea participanților (căutare, chip-uri, „+ Participant nou”). Se include din formularele care
    folosesc trait-ul PicksParticipants. Variabile: $single (un singur client), $label, $hint (opțional), plus datele din
    participantPickerData() și proprietățile publice ale componentei ($participantIds, $participantSearch, $newParticipant, $participantError).
--}}
<div>
    <label class="block text-sm font-medium text-ink mb-1.5">
        {{ $label ?? ($single ?? false ? 'Participant' : 'Participanți') }}
        @if (! empty($hint))
            <span class="font-normal text-ink-soft">({{ $hint }})</span>
        @endif
    </label>

    @if ($chosenParticipants->isNotEmpty())
        <div class="mb-2 flex flex-wrap gap-2">
            @foreach ($chosenParticipants as $cp)
                <span wire:key="chosen-{{ $cp->id }}" class="inline-flex items-center gap-2 rounded-full border {{ isset($participantDupes[$cp->id]) ? 'border-danger bg-danger/10' : 'border-border bg-bg' }} pl-3 pr-1.5 py-1 text-xs text-ink">
                    <span class="font-medium">{{ $cp->name }}</span>
                    <span class="text-ink-soft">{{ $cp->phone }} · {{ $participantVisits[$cp->id] ?? 0 }} intrări</span>
                    @if (isset($participantDupes[$cp->id]))
                        <span class="text-danger">a intrat deja în sesiunea curentă, la {{ $participantDupes[$cp->id] }}</span>
                    @endif
                    <button type="button" wire:click="removeParticipant({{ $cp->id }})" aria-label="Scoate participantul"
                            class="h-5 w-5 rounded-full text-ink-soft hover:bg-border">×</button>
                </span>
            @endforeach
        </div>
    @endif

    <input type="text" wire:model.live.debounce.300ms="participantSearch" placeholder="Caută după telefon sau nume"
           class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">

    @if ($participantResults->isNotEmpty())
        <div class="mt-2 space-y-1.5">
            @foreach ($participantResults as $pr)
                <button type="button" wire:key="found-{{ $pr->id }}" wire:click="addParticipant({{ $pr->id }})"
                        class="w-full flex items-center justify-between gap-3 rounded-lg border border-border bg-surface px-3 py-2 text-left text-sm hover:bg-bg">
                    <span class="min-w-0 truncate"><span class="font-medium text-ink">{{ $pr->name }}</span> <span class="text-ink-soft">{{ $pr->phone }}</span></span>
                    <span class="shrink-0 text-xs text-ink-soft">{{ $participantVisits[$pr->id] ?? 0 }} intrări</span>
                </button>
            @endforeach
        </div>
    @elseif (mb_strlen(trim($participantSearch)) >= 2)
        <p class="mt-2 text-xs text-ink-soft">Niciun participant găsit.</p>
    @endif

    <div class="mt-2">
        <button type="button" wire:click="toggleNewParticipant" class="text-sm text-primary hover:underline">
            {{ $newParticipant ? 'Renunță la participant nou' : '+ Participant nou' }}
        </button>
    </div>

    @if ($newParticipant)
        <div class="mt-2 grid grid-cols-1 sm:grid-cols-5 gap-2">
            <input type="text" wire:model="newName" maxlength="120" placeholder="Nume"
                   class="sm:col-span-2 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            <input type="text" inputmode="tel" wire:model="newPhone" maxlength="20" placeholder="Telefon (ex. 0722 123 456)"
                   class="sm:col-span-2 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            <x-btn variant="neutral" wire:click="createParticipant">Adaugă</x-btn>
        </div>
    @endif

    @if ($participantError)
        <p class="mt-2 text-sm text-danger">{{ $participantError }}</p>
    @endif
</div>
