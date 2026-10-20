@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $int = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-ink">Vânzare tokeni</h1>
        </div>
        @include('livewire.reception._sale-nav', ['current' => 'tokens'])
    </div>
    @if ($party)<p class="-mt-1 text-sm text-ink-soft">{{ $party->name }}</p>@endif

    <x-flash />

    @if (! $party)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Alege mai întâi petrecerea.</div>
    @elseif (! $enabled)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Tokenii sunt opriți în Setări › Metode de plată.</div>
    @elseif (! $sellable)
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Tokenii sunt în starea „Doar încasare”: se acceptă la bar, dar nu se mai vând.</div>
    @else
        <div class="rounded-2xl border border-border bg-surface p-4 space-y-5">
            <div>
                <label class="block text-sm font-medium text-ink mb-1.5">Număr de tokeni</label>
                <div class="flex flex-wrap items-center gap-2" wire:key="tokens-{{ (int) $tokens }}"
                     x-data="{ q: {{ max(1, (int) $tokens) }}, max: {{ \App\Services\TokenLedger::MAX_SALE }}, t: null,
                               set(n) { n = parseInt(n, 10); if (isNaN(n)) { n = 1; } this.q = Math.max(1, Math.min(this.max, n)); clearTimeout(this.t); this.t = setTimeout(() => { this.t = null; $wire.set('tokens', this.q); }, 300); } }"
                     x-init="$watch('$wire.tokens', (v) => { if (t === null) { q = Math.max(1, parseInt(v, 10) || 1); } })">
                    <x-btn variant="neutral" size="icon-lg" x-on:click="set(q - 1)" x-bind:disabled="q <= 1" aria-label="Mai puțin">−</x-btn>
                    <input type="number" min="1" max="1000" x-bind:value="q" x-on:change="set($event.target.value)" inputmode="numeric" data-tokens
                           class="w-24 h-12 text-center rounded-lg border border-border bg-white px-3 text-xl font-semibold text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                    <x-btn variant="neutral" size="icon-lg" x-on:click="set(q + 1)" x-bind:disabled="q >= max" aria-label="Mai mult">+</x-btn>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ([5, 10, 20, 50] as $n)
                        <button type="button" wire:key="qty-{{ $n }}" wire:click="setTokens({{ $n }})"
                                class="rounded-full border border-border bg-surface px-4 py-2.5 text-sm font-medium text-ink-soft hover:bg-bg active:bg-bg">{{ $n }}</button>
                    @endforeach
                </div>
            </div>

            @include('livewire.reception._quick-picks')

            @include('livewire.admin._participant-picker', ['single' => true, 'label' => 'Participant', 'hint' => 'opțional', 'scannable' => true])

            <div class="rounded-xl bg-bg px-4 py-3 flex items-baseline justify-between gap-3">
                <span class="text-sm text-ink-soft">Total ({{ $int($qty) }} × {{ $money($rate) }} lei)</span>
                <span class="text-2xl font-semibold text-primary">{{ $money($total) }} <span class="text-sm font-normal text-ink-soft">lei</span></span>
            </div>

            @include('livewire.reception._sale-payment', ['prefix' => 't'])

            @if ($error)
                <x-alert type="error">{{ $error }}</x-alert>
            @endif

            <x-btn variant="primary" wire:click="sell" wire:loading.attr="disabled" wire:target="sell" class="w-full h-14! text-base!">Vinde tokenii</x-btn>
        </div>
    @endif

    @if ($done)
        @include('livewire.reception._done', ['title' => $done['title'], 'line' => $done['line'], 'total' => $done['total'], 'note' => $done['note'], 'links' => []])
    @endif
</div>
