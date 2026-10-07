@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<div
    class="{{ $card }} space-y-5"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-ink">Credite</h3>
            @if ($purchasable)
                <p class="mt-0.5 text-xs text-ink-soft">1 credit = 1 leu · merg în portofelul participantului</p>
            @endif
        </div>
        <a href="{{ route('admin.settings.index') }}" wire:navigate class="text-right text-xs text-primary hover:underline shrink-0">
            Setări › Metode de plată
        </a>
    </div>

    @if (! $purchasable)
        <p class="text-sm text-ink-soft">Cumpărarea de credite e oprită în Setări › Metode de plată („participanții pot cumpăra credite”).</p>
    @else
        {{-- Sumă --}}
        <div>
            <label class="block text-sm font-medium text-ink mb-1.5">Sumă (lei)</label>
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="amount" placeholder="ex. 20"
                       class="w-24 text-center rounded-lg border border-border bg-white px-3 py-2 text-base font-semibold text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @foreach ([20, 50, 100, 200] as $n)
                    <button type="button" wire:key="camt-{{ $n }}" wire:click="setAmount({{ $n }})"
                            class="rounded-full border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink-soft hover:bg-bg">{{ $n }} lei</button>
                @endforeach
            </div>
        </div>

        {{-- Participant (obligatoriu): în portofelul cui merg creditele --}}
        @include('livewire.admin._participant-picker', ['single' => true, 'label' => 'Participant', 'hint' => 'obligatoriu — creditele merg în portofelul lui'])

        <div class="rounded-xl bg-bg px-4 py-3 flex items-baseline justify-between gap-3">
            <span class="text-sm text-ink-soft">Total</span>
            <span class="text-2xl font-semibold text-ink">{{ $money($total) }} <span class="text-sm font-normal text-ink-soft">lei</span></span>
        </div>

        @include('livewire.reception._credit-bonus', ['bonus' => $bonus, 'amount' => $amountSold])

        {{-- Plata pe total --}}
        <div>
            <label class="block text-sm font-medium text-ink mb-1.5">Plată (pe total)</label>

            <div class="flex flex-wrap gap-2 mb-3">
                @foreach ($topMethods as $key => $label)
                    <button type="button" wire:key="cpayall-{{ $key }}" wire:click="payAll('{{ $key }}')"
                            class="rounded-full border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink-soft hover:bg-bg">Tot cu {{ $label }}</button>
                @endforeach
            </div>

            <div class="space-y-2">
                @foreach ($payments as $i => $p)
                    <div wire:key="cpay-{{ $i }}" class="flex items-center gap-2">
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

        <x-btn variant="primary" wire:click="sell" class="w-full sm:w-auto">Vinde credite</x-btn>
    @endif

    {{-- La această petrecere --}}
    @if ($totals)
        <p class="text-xs text-ink-soft">
            La această petrecere: vândute {{ $totals->sold_count }} {{ $totals->sold_count === 1 ? 'vânzare' : 'vânzări' }} ({{ $money($totals->sold_amount) }} lei).
        </p>
    @endif
</div>
