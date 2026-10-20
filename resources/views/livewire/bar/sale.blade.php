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
                    {{-- DXA: runda 72. Butoane mari pentru cele mai vândute; numărul din bon apare pe buton, cele epuizate sunt gri --}}
                    <div class="space-y-1.5" data-quick-items>
                        <span class="block text-xs text-ink-soft">Cele mai vândute</span>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($quickItems as $q)
                                @php $out = in_array($q->id, $soldOutIds, true); $inCart = (int) ($cart[$q->id] ?? 0); @endphp
                                <button type="button" wire:key="quick-{{ $q->id }}" {!! $out ? 'disabled' : 'wire:click="addItem('.$q->id.')"' !!}
                                        data-quick-item="{{ $q->id }}"
                                        class="relative min-h-[3.5rem] rounded-xl border px-3 py-2 text-left text-sm font-semibold leading-tight transition-colors
                                               {{ $out ? 'border-border bg-bg text-ink-soft/60' : 'border-primary/30 bg-primary-soft text-primary active:bg-primary/20' }}">
                                    <span class="block {{ $out ? 'line-through' : '' }}">{{ $q->name }}</span>
                                    <span class="block text-xs font-normal {{ $out ? '' : 'text-primary/70' }}">{{ $out ? 'epuizat' : number_format((float) $q->price, 2, ',', '.').' lei' }}</span>
                                    @if ($inCart > 0)
                                        <span class="absolute top-1.5 right-1.5 min-w-6 h-6 px-1.5 rounded-full bg-primary text-white text-xs font-semibold flex items-center justify-center" data-quick-qty>{{ $inCart }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <x-select wire:model="pick" :live="true" :options="$productOptions" placeholder="Alege un produs…" :searchable="true" :large="true" />

                {{-- DXA: runda 72. Produse epuizate: marcate din bar, valabile doar la această petrecere --}}
                <div x-data="{ soldOutOpen: false }" data-sold-out>
                    <button type="button" @click="soldOutOpen = ! soldOutOpen" class="text-sm font-medium text-primary hover:underline">
                        Produse epuizate @if (count($soldOutIds) > 0)<span>· {{ count($soldOutIds) }}</span>@endif
                    </button>
                    <div x-show="soldOutOpen" x-cloak style="display: none" class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($allItems as $m)
                            @php $out = in_array($m->id, $soldOutIds, true); @endphp
                            <button type="button" wire:key="so-{{ $m->id }}" wire:click="toggleSoldOut({{ $m->id }})" data-sold-out-toggle="{{ $m->id }}"
                                    class="rounded-full border px-4 py-2.5 text-sm font-medium transition-colors {{ $out ? 'border-danger bg-danger/10 text-danger' : 'border-border bg-surface text-ink-soft hover:text-ink' }}">
                                {{ $m->name }}@if ($out) · epuizat @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                @if ($cartItems->isNotEmpty())
                    <div class="rounded-xl border border-border divide-y divide-border">
                        @foreach ($cart as $id => $qty)
                            @php $item = $cartItems->get((int) $id); @endphp
                            @continue(! $item)
                            <div wire:key="cart-{{ $id }}" class="flex items-center gap-2 px-3 py-2.5"
                                 x-data="{ q: {{ (int) $qty }}, price: {{ (float) $item->price }}, t: null,
                                           fmt(n) { return n.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                                           set(n) { this.q = Math.max(1, Math.min({{ 99 }}, n)); clearTimeout(this.t); this.t = setTimeout(() => { this.t = null; $wire.setQty({{ $id }}, this.q); }, 300); } }"
                                 x-init="$watch('$wire.cart', (c) => { if (t === null && c && c[{{ $id }}]) { q = parseInt(c[{{ $id }}], 10); } })">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-ink truncate">{{ $item->name }}</div>
                                    <div class="text-xs text-ink-soft">{{ $money($item->price) }} lei × <span x-text="q">{{ $qty }}</span> = <span x-text="fmt(q * price)">{{ $money($qty * (float) $item->price) }}</span> lei</div>
                                </div>
                                <div class="inline-flex items-center gap-1.5 shrink-0">
                                    <x-btn variant="neutral" size="icon-lg" outline x-on:click="set(q - 1)" x-bind:disabled="q <= 1" aria-label="Mai puțin">−</x-btn>
                                    <span class="w-8 text-center text-base font-semibold text-ink" x-text="q" data-cart-qty>{{ $qty }}</span>
                                    <x-btn variant="neutral" size="icon-lg" outline x-on:click="set(q + 1)" x-bind:disabled="q >= 99" aria-label="Mai mult">+</x-btn>
                                    <x-btn variant="danger" size="icon-lg" outline aria-label="Șterge" wire:click="removeItem({{ $id }})"><x-icon-trash class="w-5 h-5" /></x-btn>
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
                                class="rounded-full border border-border bg-surface px-4 py-2.5 text-sm font-medium text-ink-soft hover:bg-bg active:bg-bg">Tot cu {{ $label }}</button>
                    @endforeach
                </div>

                <div class="space-y-2">
                    @foreach ($payments as $i => $p)
                        <div wire:key="pay-{{ $i }}" class="flex items-center gap-2">
                            <x-select wire:model="payments.{{ $i }}.method" :live="true" :options="$methods" :large="true" class="flex-1 min-w-0" />
                            @if (($p['method'] ?? '') === 'token')
                                <input type="number" min="0" step="1" inputmode="numeric" wire:model.live.debounce.300ms="payments.{{ $i }}.tokens" placeholder="Tokeni" class="w-28 {{ $input }}">
                            @else
                                <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="payments.{{ $i }}.amount" placeholder="Sumă" class="w-28 {{ $input }}">
                            @endif
                            <button type="button" wire:click="fillRemaining({{ $i }})" class="shrink-0 rounded-lg border border-primary/40 px-3 py-3 text-sm font-medium text-primary active:bg-primary-soft">Restul</button>
                            @if (count($payments) > 1)
                                <x-btn variant="danger" size="icon-lg" outline aria-label="Șterge" wire:click="removePayment({{ $i }})"><x-icon-trash class="w-5 h-5" /></x-btn>
                            @endif
                        </div>
                        @if (($p['method'] ?? '') === 'token' && $tokenRate > 0)
                            <p class="-mt-1 text-xs text-ink-soft">1 token = {{ $money($tokenRate) }} lei @if ((int) ($p['tokens'] ?? 0) > 0) · {{ (int) $p['tokens'] }} tokeni = {{ $money((int) $p['tokens'] * $tokenRate) }} lei @endif</p>
                        @endif
                    @endforeach
                </div>

                <div class="mt-2 flex items-center justify-between gap-3">
                    <button type="button" wire:click="addPayment" class="rounded-lg border border-primary/40 px-4 py-3 text-sm font-medium text-primary active:bg-primary-soft">+ Altă metodă</button>
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

            <x-btn variant="primary" wire:click="sell" wire:loading.attr="disabled" wire:target="sell" class="w-full h-14! text-base!">Înregistrează vânzarea</x-btn>
        </div>
    @endif

    @if ($done)
        @include('livewire.reception._done', ['title' => $done['title'], 'line' => $done['line'], 'total' => $done['total'], 'note' => $done['note'], 'links' => []])
    @endif
</div>
