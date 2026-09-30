@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<div class="space-y-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-ink">Intrare</h1>
            @if ($party)<p class="mt-0.5 text-sm text-ink-soft truncate">{{ $party->name }}</p>@endif
        </div>
        <a href="{{ route('receptie.home') }}" wire:navigate class="shrink-0 text-xs font-medium text-primary hover:underline">‹ Acasă</a>
    </div>

    <x-flash />

    @if (! $party)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Alege mai întâi petrecerea.</div>
    @else
    <div class="rounded-2xl border border-border bg-surface p-4 space-y-5">
    @if (! $tickets)
        <p class="text-sm text-warning">Petrecerea nu are niciun tip de bilet cu preț. Setează-l din pagina petrecerii.</p>
    @else
        {{-- Tip bilet --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach ($tickets as $t)
                <button type="button" wire:key="ticket-{{ $loop->index }}" wire:click="selectTicket('{{ addslashes($t['name']) }}')"
                        class="text-left rounded-xl border px-4 py-3 {{ $ticket === $t['name'] ? 'border-primary bg-primary-soft' : 'border-border bg-surface hover:bg-bg' }}">
                    <span class="block text-sm font-medium text-ink">{{ $t['name'] }}</span>
                    @if ($t['quote'])
                        <span class="block text-lg font-semibold {{ $t['quote']->price <= 0 ? 'text-success' : 'text-ink' }}">
                            {{ $t['quote']->price <= 0 ? 'Gratuit' : $money($t['quote']->price).' lei' }}
                        </span>
                        @if ($t['quote']->grace)
                            <span class="block text-[11px] text-warning">În toleranță ({{ $graceMinutes }} min) · preț de listă {{ $money($t['quote']->list_price) }} lei</span>
                        @endif
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Persoane --}}
        <div>
            <label class="block text-sm font-medium text-ink mb-1.5">Număr de persoane</label>
            <div class="inline-flex items-center gap-2">
                <x-btn variant="neutral" size="md" wire:click="stepCount(-1)" aria-label="Mai puțin">−</x-btn>
                <input type="number" min="1" max="50" wire:model.live.debounce.300ms="count" inputmode="numeric"
                       class="w-20 text-center rounded-lg border border-border bg-white px-3 py-2 text-base font-semibold text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                <x-btn variant="neutral" size="md" wire:click="stepCount(1)" aria-label="Mai mult">+</x-btn>
            </div>
        </div>

        {{-- Participanți identificați (opțional) --}}
        @include('livewire.admin._participant-picker', [
            'single' => false,
            'label' => 'Participanți',
            'hint' => 'opțional · '.$chosenParticipants->count().' din '.((int) $count > 0 ? (int) $count : 1).' identificați; restul rămân anonimi',
        ])

        @if ($canOverride)
        {{-- Suprascriere preț --}}
        <div>
            <label class="flex items-center gap-2.5 text-sm text-ink cursor-pointer">
                <input type="checkbox" wire:model.live="override" class="w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                Schimb prețul (per persoană)
            </label>
            @if ($override)
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div class="flex items-center gap-2">
                        <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="overridePrice" placeholder="Preț"
                               class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                        <span class="text-sm text-ink-soft">lei</span>
                    </div>
                    <input type="text" wire:model="overrideReason" maxlength="255" placeholder="Motiv (obligatoriu)"
                           class="sm:col-span-2 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                </div>
            @endif
        </div>
        @endif

        @include('livewire.partials.entry-tickets')

        {{-- Total + plată pe total --}}
        <div class="rounded-xl bg-bg px-4 py-3">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm text-ink-soft">{{ $this->totalLabel() }}</span>
                <span class="text-2xl font-semibold text-ink">{{ $money($total) }} <span class="text-sm font-normal text-ink-soft">lei</span></span>
            </div>
        </div>

        @if ($total > 0)
            <div>
                <label class="block text-sm font-medium text-ink mb-1.5">Plată (pe total)</label>

                <div class="flex flex-wrap gap-2 mb-3">
                    @foreach ($methods as $key => $label)
                        <button type="button" wire:key="payall-{{ $key }}" wire:click="payAll('{{ $key }}')"
                                class="rounded-full border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink-soft hover:bg-bg">Tot cu {{ $label }}</button>
                    @endforeach
                </div>

                <div class="space-y-2">
                    @foreach ($payments as $i => $p)
                        <div wire:key="pay-{{ $i }}" class="flex items-center gap-2">
                            <x-select wire:model="payments.{{ $i }}.method" :options="$methods" class="flex-1 min-w-0" />
                            <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="payments.{{ $i }}.amount" placeholder="Sumă"
                                   class="w-28 rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                            <button type="button" wire:click="fillRemaining({{ $i }})" class="shrink-0 text-xs font-medium text-primary hover:underline">Restul</button>
                            @if (count($payments) > 1)
                                <x-btn variant="danger" size="icon" outline tooltip="Scoate" wire:click="removePayment({{ $i }})">×</x-btn>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 flex items-center justify-between gap-3">
                    <button type="button" wire:click="addPayment" class="text-sm text-primary hover:underline">+ Altă metodă</button>
                    @if (abs($rest) < 0.005)
                        <span class="text-sm font-medium text-success">Încasat complet</span>
                    @elseif ($paid <= 0)
                        <span class="text-sm text-ink-soft">De încasat {{ $money($rest) }} lei</span>
                    @elseif ($rest > 0)
                        <span class="text-sm font-medium text-warning">Mai lipsesc {{ $money($rest) }} lei</span>
                    @else
                        <span class="text-sm font-medium text-danger">Cu {{ $money(abs($rest)) }} lei în plus</span>
                    @endif
                </div>
            </div>
        @else
            <p class="text-sm text-ink-soft">Intrare gratuită: nu se încasează nimic.</p>
        @endif

        @if ($message)
            <x-alert type="success">{{ $message }}</x-alert>
        @endif
        @if ($error)
            <x-alert type="error">{{ $error }}</x-alert>
        @endif

        <x-btn variant="primary" wire:click="save" class="w-full">Înregistrează intrarea</x-btn>
    @endif
    </div>
    @endif
</div>
