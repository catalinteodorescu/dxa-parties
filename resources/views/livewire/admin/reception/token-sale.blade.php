@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp
<div
    class="{{ $card }} space-y-5"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-ink">Tokeni</h3>
            @if ($enabled)
                <p class="mt-0.5 text-xs text-ink-soft">1 token = {{ $money($rate) }} lei</p>
            @endif
        </div>
        <a href="{{ route('admin.reception.tokens') }}" wire:navigate class="text-right text-xs text-primary hover:underline">
            În circulație: <span class="font-semibold {{ $circulation < 0 ? 'text-warning' : '' }}">{{ $int($circulation) }}</span> · detalii
        </a>
    </div>

    @if (! $enabled)
        <p class="text-sm text-ink-soft">Tokenii sunt opriți în Setări › Metode de plată.</p>
    @elseif (! $sellable)
        <p class="text-sm text-ink-soft">Tokenii sunt în starea „Doar încasare”: se acceptă la bar, dar nu se mai vând.</p>
    @else
        {{-- Cantitate --}}
        <div>
            <label class="block text-sm font-medium text-ink mb-1.5">Număr de tokeni</label>
            <div class="flex flex-wrap items-center gap-2">
                <x-btn variant="neutral" size="md" wire:click="stepTokens(-1)" aria-label="Mai puțin">−</x-btn>
                <input type="number" min="1" max="1000" wire:model.live.debounce.300ms="tokens" inputmode="numeric"
                       class="w-24 text-center rounded-lg border border-border bg-white px-3 py-2 text-base font-semibold text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                <x-btn variant="neutral" size="md" wire:click="stepTokens(1)" aria-label="Mai mult">+</x-btn>
                @foreach ([5, 10, 20, 50] as $n)
                    <button type="button" wire:key="qty-{{ $n }}" wire:click="setTokens({{ $n }})"
                            class="rounded-full border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink-soft hover:bg-bg">{{ $n }}</button>
                @endforeach
            </div>
        </div>

        {{-- Chip-uri rapide: participanții din ultima intrare înregistrată aici, fără căutare --}}
        @if ($quickPicks->isNotEmpty())
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-ink-soft">Cumpără:</span>
                @foreach ($quickPicks as $qp)
                    <button type="button" wire:key="quick-{{ $qp->id }}" wire:click="pickQuickParticipant({{ $qp->id }})"
                            class="inline-flex items-center rounded-full border border-primary/30 bg-primary-soft text-primary text-xs font-medium px-2.5 py-1 hover:bg-primary/20">
                        {{ $qp->name }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Participant (opțional): cine cumpără tokenii --}}
        @include('livewire.admin._participant-picker', ['single' => true, 'label' => 'Participant', 'hint' => 'opțional'])

        <div class="rounded-xl bg-bg px-4 py-3 flex items-baseline justify-between gap-3">
            <span class="text-sm text-ink-soft">Total ({{ $int($qty) }} × {{ $money($rate) }} lei)</span>
            <span class="text-2xl font-semibold text-ink">{{ $money($total) }} <span class="text-sm font-normal text-ink-soft">lei</span></span>
        </div>

        {{-- Plata pe total --}}
        <div>
            <label class="block text-sm font-medium text-ink mb-1.5">Plată (pe total)</label>

            <div class="flex flex-wrap gap-2 mb-3">
                @foreach ($methods as $key => $label)
                    <button type="button" wire:key="tpayall-{{ $key }}" wire:click="payAll('{{ $key }}')"
                            class="rounded-full border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink-soft hover:bg-bg">Tot cu {{ $label }}</button>
                @endforeach
            </div>

            <div class="space-y-2">
                @foreach ($payments as $i => $p)
                    <div wire:key="tpay-{{ $i }}" class="flex items-center gap-2">
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

        @if ($message)
            <x-alert type="success">{{ $message }}</x-alert>
        @endif
        @if ($error)
            <x-alert type="error">{{ $error }}</x-alert>
        @endif

        <x-btn variant="primary" wire:click="sell" class="w-full sm:w-auto">Vinde tokenii</x-btn>
    @endif

    {{-- La această petrecere --}}
    @if ($totals)
        <p class="text-xs text-ink-soft">
            La această petrecere: vânduți {{ $int($totals->sold) }} ({{ $money($totals->sold_amount) }} lei) · încasați la bar {{ $int($totals->collected) }}.
        </p>
    @endif
</div>
