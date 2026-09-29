@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-ink">Vânzare credite</h1>
        </div>
        @include('livewire.reception._sale-nav', ['current' => 'credits'])
    </div>
    @if ($party)<p class="-mt-1 text-sm text-ink-soft">{{ $party->name }}</p>@endif

    <x-flash />

    @if (! $party)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Alege mai întâi petrecerea.</div>
    @elseif (! $purchasable)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Cumpărarea de credite e oprită în Setări › Metode de plată.</div>
    @else
        <div class="rounded-2xl border border-border bg-surface p-4 space-y-5">
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

            @include('livewire.reception._quick-picks')

            @include('livewire.admin._participant-picker', ['single' => true, 'label' => 'Participant', 'hint' => 'obligatoriu', 'scannable' => true])

            <div class="rounded-xl bg-bg px-4 py-3 flex items-baseline justify-between gap-3">
                <span class="text-sm text-ink-soft">Total</span>
                <span class="text-2xl font-semibold text-primary">{{ $money($total) }} <span class="text-sm font-normal text-ink-soft">lei</span></span>
            </div>

            @include('livewire.reception._sale-payment', ['prefix' => 'c'])

            @if ($error)
                <x-alert type="error">{{ $error }}</x-alert>
            @endif

            <x-btn variant="primary" wire:click="sell" class="w-full">Vinde credite</x-btn>
        </div>
    @endif

    @if ($done)
        @include('livewire.reception._done', ['title' => $done['title'], 'line' => $done['line'], 'total' => $done['total'], 'note' => $done['note'], 'links' => []])
    @endif
</div>
