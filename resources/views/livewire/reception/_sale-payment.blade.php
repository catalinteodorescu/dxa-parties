{{-- DXA: adaugat (PWA Recepție - Etapa 3). Plata pe total, comună ecranelor de vânzare tokeni / credite. Variabile: $prefix, $methods, $topMethods, $payments, $total, $paid, $rest. --}}
@php $money = fn ($n) => number_format((float) $n, 2, ',', '.'); @endphp
<div>
    <label class="block text-sm font-medium text-ink mb-1.5">Plată (pe total)</label>

    <div class="flex flex-wrap gap-2 mb-3">
        @foreach ($topMethods as $key => $label)
            <button type="button" wire:key="{{ $prefix }}payall-{{ $key }}" wire:click="payAll('{{ $key }}')"
                    class="rounded-full border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink-soft hover:bg-bg">Tot cu {{ $label }}</button>
        @endforeach
    </div>

    <div class="space-y-2">
        @foreach ($payments as $i => $p)
            <div wire:key="{{ $prefix }}pay-{{ $i }}" class="flex items-center gap-2">
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
