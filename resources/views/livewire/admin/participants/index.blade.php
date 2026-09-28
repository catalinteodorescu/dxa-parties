@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $input = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    // Sablon de coloane pentru desktop; pe mobil totul devine card stivuit (ca la Administratori).
    $cols = 'md:grid-cols-[minmax(0,1.6fr)_minmax(0,1.1fr)_minmax(0,0.7fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_96px]';
@endphp
<div
    class="max-w-5xl"
    x-data="{
        confirmOpen: false,
        confirmTitle: '',
        confirmMessage: '',
        confirmMethod: '',
        confirmArgs: [],
        askConfirm(title, message, method, args = []) {
            this.confirmTitle = title;
            this.confirmMessage = message;
            this.confirmMethod = method;
            this.confirmArgs = args;
            this.confirmOpen = true;
        },
        runConfirm() {
            this.$wire.call(this.confirmMethod, ...this.confirmArgs);
            this.confirmOpen = false;
        },
    }"
>
    <div class="mb-5 flex items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-ink">Participanți</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ $total }} {{ $total === 1 ? 'participant identificat' : 'participanți identificați' }}. Se adaugă aici sau direct la recepție, când înregistrezi o intrare.</p>
        </div>
        <x-btn variant="primary" wire:click="toggleAdd" class="shrink-0">{{ $adding ? 'Renunță' : 'Adaugă' }}</x-btn>
    </div>

    <x-flash class="mb-4" />
    @if ($message)
        <x-alert type="success" class="mb-4">{{ $message }}</x-alert>
    @endif
    @if ($error)
        <x-alert type="error" class="mb-4">{{ $error }}</x-alert>
    @endif

    @if ($adding)
        <div class="{{ $card }} mb-4">
            <h3 class="text-sm font-semibold text-ink">Participant nou</h3>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-5 gap-2">
                <input type="text" wire:model="newName" maxlength="120" placeholder="Nume" class="sm:col-span-2 {{ $input }}">
                <input type="text" inputmode="tel" wire:model="newPhone" maxlength="20" placeholder="Telefon (ex. 0722 123 456)" x-on:keydown.enter.prevent="$wire.create()" class="sm:col-span-2 {{ $input }}">
                <x-btn variant="primary" wire:click="create">Salvează</x-btn>
            </div>
        </div>
    @endif

    <div class="{{ $card }} mb-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-ink mb-1.5">Caută</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nume sau telefon" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-ink mb-1.5">Sortează după</label>
            <x-select wire:model="sort" live :options="$sortOptions" />
        </div>
    </div>

    @if ($participants->isEmpty())
        <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
            {{ trim($search) !== '' ? 'Niciun participant nu se potrivește căutării.' : 'Niciun participant încă. Adaugă unul, sau identifică-l la recepție.' }}
        </div>
    @else
        <div class="hidden md:grid {{ $cols }} gap-4 px-5 py-2
                    text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
            <div>Nume</div>
            <div>Telefon</div>
            <div class="text-right">Intrări</div>
            <div class="text-right">Tokeni cheltuiți</div>
            <div class="text-right">Credite cheltuite</div>
            <div class="text-right">Acțiuni</div>
        </div>

        <div class="space-y-3">
            @foreach ($participants as $p)
                @php $anonymized = $p->isAnonymized(); @endphp
                <div wire:key="participant-{{ $p->id }}" class="rounded-2xl border border-border bg-surface p-4
                            md:px-5 md:py-3 md:grid {{ $cols }} md:items-center md:gap-4">

                    {{-- Nume --}}
                    <div class="min-w-0">
                        <div class="text-sm font-semibold {{ $anonymized ? 'text-ink-soft' : 'text-ink' }}">{{ $p->name }}</div>
                        <div class="mt-0.5 text-xs text-ink-soft md:hidden">{{ $p->phone ?: 'fără telefon' }}</div>
                    </div>

                    {{-- Telefon --}}
                    <div class="hidden md:block text-ink-soft">{{ $p->phone ?: '—' }}</div>

                    {{-- Intrări --}}
                    <div class="flex items-center justify-between gap-3 md:block md:text-right mt-2 md:mt-0">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Intrări</span>
                        <span class="text-sm font-semibold text-ink">{{ (int) $p->entries_count }}</span>
                    </div>

                    {{-- Tokeni cheltuiti --}}
                    <div class="flex items-center justify-between gap-3 md:block md:text-right mt-1 md:mt-0">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Tokeni cheltuiți</span>
                        <span class="text-sm font-semibold text-ink">{{ number_format((int) $p->tokens_spent, 0, ',', '.') }}</span>
                    </div>

                    {{-- Credite cheltuite --}}
                    <div class="flex items-center justify-between gap-3 md:block md:text-right mt-1 md:mt-0">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Credite cheltuite</span>
                        <span class="text-sm font-semibold text-ink">{{ $money($p->credits_spent) }} lei</span>
                    </div>

                    {{-- Actiuni: doar iconite (vezi, editeaza, sterge/anonimizeaza) --}}
                    <div class="mt-3 md:mt-0 flex items-center justify-end gap-1.5">
                        <x-btn variant="neutral" size="icon" outline tooltip="Vezi" :href="route('admin.participants.show', $p)" wire:navigate>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </x-btn>
                        <x-btn variant="warning" size="icon" outline tooltip="Editează" :href="route('admin.participants.show', $p)" wire:navigate>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                        </x-btn>
                        {{-- „Vezi card fidelitate” — pe viitor. --}}
                        @unless ($anonymized)
                            @if ((int) $p->entries_count === 0)
                                <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                                       x-on:click="askConfirm('Șterge participantul', 'Ștergi definitiv participantul „{{ addslashes($p->name) }}”? Acțiunea nu poate fi anulată.', 'delete', [{{ $p->id }}])">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </x-btn>
                            @else
                                <x-btn variant="danger" size="icon" outline tooltip="Anonimizează"
                                       x-on:click="askConfirm('Anonimizează participantul', 'Anonimizezi participantul „{{ addslashes($p->name) }}”? Numele și telefonul se șterg definitiv.', 'anonymize', [{{ $p->id }}])">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/><path d="m3 3 18 18"/>
                                    </svg>
                                </x-btn>
                            @endif
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $participants->links() }}</div>
    @endif

    {{-- Modal de confirmare, pentru ștergere/anonimizare --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink" x-text="confirmTitle"></h3>
            <p class="mt-2 text-sm text-ink-soft" x-text="confirmMessage"></p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="confirmOpen = false"
                        class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">
                    Renunță
                </button>
                <button type="button" @click="runConfirm()"
                        class="rounded-lg bg-danger hover:bg-danger/90 text-white text-sm font-medium px-4 py-2 transition-colors">
                    Confirmă
                </button>
            </div>
        </div>
    </div>
</div>
