@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $input = 'rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
@endphp
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-ink">Vânzare</h1>
        </div>
        @include('livewire.bar._nav', ['current' => 'sale'])
    </div>
    @if ($party)<p class="-mt-1 text-sm text-ink-soft">{{ $party->name }}</p>@endif

    <x-flash />

    @if (! $party)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Alege mai întâi petrecerea.</div>
    @else
        <div class="rounded-2xl border border-border bg-surface p-4 space-y-5">

            {{-- Participant --}}
            @include('livewire.admin._participant-picker', ['single' => true, 'label' => 'Participant', 'hint' => 'opțional', 'scannable' => true, 'allowNew' => false])

            {{-- Produse --}}
            <div class="space-y-3">
                <label class="block text-sm font-medium text-ink">Produse</label>

                @if ($quickItems->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-xs text-ink-soft">Cele mai vândute:</span>
                        @foreach ($quickItems as $q)
                            <button type="button" wire:key="quick-{{ $q->id }}" wire:click="addItem({{ $q->id }})"
                                    class="inline-flex items-center rounded-full border border-primary/30 bg-primary-soft text-primary text-sm font-medium px-3 py-1.5 hover:bg-primary/20">
                                {{ $q->name }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <x-select wire:model="pick" :live="true" :options="$productOptions" placeholder="Alege un produs…" :searchable="true" />

                @if ($cartItems->isNotEmpty())
                    <div class="rounded-xl border border-border divide-y divide-border">
                        @foreach ($cart as $id => $qty)
                            @php $item = $cartItems->get((int) $id); @endphp
                            @continue(! $item)
                            <div wire:key="cart-{{ $id }}" class="flex items-center gap-2 px-3 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-ink truncate">{{ $item->name }}</div>
                                    <div class="text-xs text-ink-soft">{{ $money($item->price) }} lei × {{ $qty }} = {{ $money($qty * (float) $item->price) }} lei</div>
                                </div>
                                <div class="inline-flex items-center gap-1.5 shrink-0">
                                    <x-btn variant="neutral" size="icon" outline wire:click="stepQty({{ $id }}, -1)" aria-label="Mai puțin">−</x-btn>
                                    <span class="w-7 text-center text-sm font-semibold text-ink">{{ $qty }}</span>
                                    <x-btn variant="neutral" size="icon" outline wire:click="stepQty({{ $id }}, 1)" aria-label="Mai mult">+</x-btn>
                                    <x-btn variant="danger" size="icon" outline tooltip="Scoate" wire:click="removeItem({{ $id }})">×</x-btn>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="clearCart" class="text-xs font-medium text-ink-soft hover:text-danger">Golește bonul</button>
                @else
                    <p class="text-sm text-ink-soft">Bonul e gol. Alege un produs sau apasă pe unul din cele mai vândute.</p>
                @endif
            </div>

            <div class="rounded-xl bg-bg px-4 py-3 flex items-baseline justify-between gap-3">
                <span class="text-sm text-ink-soft">Total</span>
                <span class="text-right">
                    <span class="block text-2xl font-semibold text-primary">{{ $money($total) }} <span class="text-sm font-normal text-ink-soft">lei</span></span>
                    @if ($totalTokens !== null && $total > 0)
                        <span class="block text-sm text-ink-soft">≈ {{ rtrim(rtrim(number_format($totalTokens, 1, ',', '.'), '0'), ',') }} tokeni</span>
                    @endif
                </span>
            </div>

            {{-- Plata pe total --}}
            <div>
                <label class="block text-sm font-medium text-ink mb-1.5">Plată (pe total)</label>

                <div class="flex flex-wrap gap-2 mb-3">
                    @foreach ($topMethods as $key => $label)
                        <button type="button" wire:key="payall-{{ $key }}" wire:click="payAll('{{ $key }}')"
                                class="rounded-full border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink-soft hover:bg-bg">Tot cu {{ $label }}</button>
                    @endforeach
                </div>

                <div class="space-y-2">
                    @foreach ($payments as $i => $p)
                        <div wire:key="pay-{{ $i }}" class="flex items-center gap-2">
                            <x-select wire:model="payments.{{ $i }}.method" :live="true" :options="$methods" class="flex-1 min-w-0" />
                            @if (($p['method'] ?? '') === 'token')
                                <input type="number" min="0" step="1" inputmode="numeric" wire:model.live.debounce.300ms="payments.{{ $i }}.tokens" placeholder="Tokeni" class="w-28 {{ $input }}">
                            @else
                                <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="payments.{{ $i }}.amount" placeholder="Sumă" class="w-28 {{ $input }}">
                            @endif
                            <button type="button" wire:click="fillRemaining({{ $i }})" class="shrink-0 text-xs font-medium text-primary hover:underline">Restul</button>
                            @if (count($payments) > 1)
                                <x-btn variant="danger" size="icon" outline tooltip="Scoate" wire:click="removePayment({{ $i }})">×</x-btn>
                            @endif
                        </div>
                        @if (($p['method'] ?? '') === 'token' && $tokenRate > 0)
                            <p class="-mt-1 text-xs text-ink-soft">1 token = {{ $money($tokenRate) }} lei @if ((int) ($p['tokens'] ?? 0) > 0) · {{ (int) $p['tokens'] }} tokeni = {{ $money((int) $p['tokens'] * $tokenRate) }} lei @endif</p>
                        @endif
                    @endforeach
                </div>

                <div class="mt-2 flex items-center justify-between gap-3">
                    <button type="button" wire:click="addPayment" class="text-sm text-primary hover:underline">+ Altă metodă</button>
                    @if ($total <= 0)
                        <span class="text-sm text-ink-soft">Adaugă produse</span>
                    @elseif (abs($rest) < 0.005)
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

            @if ($error)
                <x-alert type="error">{{ $error }}</x-alert>
            @endif

            <x-btn variant="primary" wire:click="sell" wire:loading.attr="disabled" wire:target="sell" class="w-full">Înregistrează vânzarea</x-btn>
        </div>
    @endif

    @if ($done)
        @include('livewire.reception._done', ['title' => $done['title'], 'line' => $done['line'], 'total' => $done['total'], 'note' => $done['note'], 'links' => []])
    @endif
</div>
