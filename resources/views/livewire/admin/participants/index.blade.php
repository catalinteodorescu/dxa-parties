@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $input = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y H:i') : '—';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<div class="max-w-4xl">
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
        <div class="space-y-3">
            @foreach ($participants as $p)
                <div wire:key="participant-{{ $p->id }}" class="rounded-2xl border border-border bg-surface p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold {{ $p->isAnonymized() ? 'text-ink-soft' : 'text-ink' }}">{{ $p->name }}</div>
                            <div class="mt-0.5 text-xs text-ink-soft">{{ $p->phone ?: 'fără telefon' }}</div>
                        </div>
                        <x-btn variant="neutral" size="sm" outline :href="route('admin.participants.show', $p)" wire:navigate>Vezi</x-btn>
                    </div>
                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div class="rounded-lg bg-bg px-3 py-2">
                            <div class="text-ink-soft">Intrări</div>
                            <div class="mt-0.5 text-sm font-semibold text-ink">{{ (int) $p->entries_count }}</div>
                        </div>
                        <div class="rounded-lg bg-bg px-3 py-2">
                            <div class="text-ink-soft">Cheltuit la bar</div>
                            <div class="mt-0.5 text-sm font-semibold text-ink">{{ $money($p->bar_spent) }} lei</div>
                        </div>
                        <div class="rounded-lg bg-bg px-3 py-2">
                            <div class="text-ink-soft">Tokeni cumpărați</div>
                            <div class="mt-0.5 text-sm font-semibold text-ink">{{ number_format((int) $p->tokens_bought, 0, ',', '.') }}</div>
                        </div>
                        <div class="rounded-lg bg-bg px-3 py-2">
                            <div class="text-ink-soft">Ultima intrare</div>
                            <div class="mt-0.5 text-sm font-semibold text-ink">{{ $fmt($p->last_at) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $participants->links() }}</div>
    @endif
</div>
