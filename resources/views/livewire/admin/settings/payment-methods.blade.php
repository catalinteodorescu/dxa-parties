@php
    $tokenHelp = [
        'active' => 'Se vând la recepție și se acceptă la bar.',
        'collect_only' => 'Nu se mai vând la recepție; se acceptă în continuare la bar (perioadă de retragere).',
        'off' => 'Nu se mai acceptă și nu se mai vând.',
    ];
    $switch = 'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40';
    $knob = 'inline-block h-[18px] w-[18px] transform rounded-full bg-white shadow transition-transform';
@endphp
<div
    class="bg-surface border border-border rounded-2xl p-6"
    x-data="{
        confirmOpen: false,
        confirmName: '',
        confirmId: null,
        tokensOffOpen: false,
        ask(id, name) { this.confirmId = id; this.confirmName = name; this.confirmOpen = true; },
        run() { this.$wire.call('delete', this.confirmId); this.confirmOpen = false; },
        tokensOff() { this.$wire.call('setTokenMode', 'off'); this.tokensOffOpen = false; },
    }"
>
    <h3 class="text-sm font-semibold text-ink">Metode de plată</h3>
    <p class="mt-1 text-sm text-ink-soft leading-relaxed">
        Metodele acceptate de organizație. La o petrecere nouă se precompletează cu cele active, iar pe fiecare petrecere poți debifa ce nu accepți acolo.
        Metodele custom se adaugă doar de aici.
    </p>

    <div class="mt-4 space-y-3">
        @if ($message)
            <x-alert type="success" dismiss-prop="message" :dismiss-value="null">{{ $message }}</x-alert>
        @endif
        @if ($error)
            <x-alert type="error" dismiss-prop="error" :dismiss-value="null">{{ $error }}</x-alert>
        @endif
    </div>

    <div class="mt-4 space-y-2">
        @foreach ($methods as $m)
            @php $enabled = $m->isEnabled(); @endphp

            <div wire:key="pm-{{ $m->id }}" class="rounded-lg border border-border bg-surface px-3 py-2.5">
                <div class="flex items-center gap-3">
                    @if ($m->is_builtin)
                        <span class="flex-1 min-w-0 text-sm font-medium {{ $enabled ? 'text-ink' : 'text-ink-soft' }}">
                            {{ $m->key === 'credit' ? 'Acceptă credite la plată' : $m->label }}
                        </span>
                    @else
                        {{-- Metodă custom: nume editabil inline (Enter sau părăsirea câmpului = salvează) --}}
                        <input type="text" wire:model="names.{{ $m->id }}" wire:change="rename({{ $m->id }})"
                               x-on:keydown.enter.prevent="$el.blur()" maxlength="60" aria-label="Denumire metodă de plată"
                               class="flex-1 min-w-0 rounded-lg border border-transparent hover:border-border bg-transparent px-2.5 py-1 text-sm font-medium focus:outline-none focus:bg-white focus:ring-2 focus:ring-primary/40 focus:border-primary {{ $enabled ? 'text-ink' : 'text-ink-soft' }}">
                    @endif

                    @if ($m->key === 'cash')
                        <span class="shrink-0 text-xs text-ink-soft/70">mereu activ</span>
                    @elseif ($m->key === 'token')
                        {{-- Tokeni: 3 stări --}}
                        <div class="inline-flex shrink-0 rounded-lg border border-border bg-bg p-0.5 text-xs">
                            @foreach (\App\Support\PaymentMethods::TOKEN_MODES as $mode => $modeLabel)
                                @if ($mode === 'off' && $tokenMode !== 'off')
                                    <button type="button" x-on:click="tokensOffOpen = true" @if ($tokenBlock) disabled @endif
                                            class="rounded-md px-2.5 py-1 font-medium text-ink-soft hover:text-ink disabled:opacity-40 disabled:pointer-events-none">{{ $modeLabel }}</button>
                                @else
                                    <button type="button" wire:click="setTokenMode('{{ $mode }}')"
                                            class="rounded-md px-2.5 py-1 font-medium {{ $tokenMode === $mode ? 'bg-surface text-ink shadow-sm' : 'text-ink-soft hover:text-ink' }}">{{ $modeLabel }}</button>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <button type="button" role="switch" aria-checked="{{ $enabled ? 'true' : 'false' }}"
                                aria-label="{{ $enabled ? 'Dezactivează' : 'Activează' }} {{ $m->label }}"
                                wire:click="toggle('{{ $m->key }}')"
                                @if ($m->key === 'credit' && $enabled && $creditBlock) disabled @endif
                                class="{{ $switch }} {{ $enabled ? 'bg-primary' : 'bg-border' }} disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="{{ $knob }} {{ $enabled ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    @endif

                    @unless ($m->is_builtin)
                        @if ($used[$m->id] ?? false)
                            <x-btn variant="danger" size="icon" outline disabled
                                   tooltip="Nu poți șterge: e folosită. Dezactiveaz-o."
                                   class="opacity-40 cursor-not-allowed">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            </x-btn>
                        @else
                            <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                                   x-on:click="ask({{ $m->id }}, '{{ addslashes($m->label) }}')">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            </x-btn>
                        @endif
                    @endunless
                </div>

                {{-- Tokeni: explicația stării + cursul --}}
                @if ($m->key === 'token')
                    <p class="mt-1.5 text-xs text-ink-soft">{{ $tokenHelp[$tokenMode] }}</p>
                    <p class="mt-1 text-xs text-ink-soft">
                        Tokeni în circulație: <span class="font-medium {{ $circulation > 0 ? 'text-ink' : '' }}">{{ number_format($circulation, 0, ',', '.') }}</span> ·
                        <a href="{{ route('admin.reception.tokens') }}" wire:navigate class="text-primary hover:underline">Recepție › Tokeni</a>
                    </p>
                    @if ($tokenBlock)
                        <p class="mt-1 text-xs text-warning">Nu se pot opri: {{ $tokenBlock }}</p>
                    @endif
                    @if ($tokenMode !== 'off')
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-ink">Curs token → lei</label>
                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                <input type="number" step="0.5" min="0" wire:model="rate" x-on:keydown.enter.prevent="$wire.saveRate()"
                                       class="w-28 rounded-lg border border-border bg-white px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                                <span class="text-sm text-ink-soft whitespace-nowrap">lei / token</span>
                                <x-btn variant="neutral" size="sm" wire:click="saveRate">Salvează cursul</x-btn>
                            </div>
                            <p class="mt-1.5 text-xs text-ink-soft">
                                Se aplică vânzărilor noi; plățile deja înregistrate își păstrează cursul. Tokenii deja vânduți au fost cumpărați la cursul vechi, deci schimbă cursul cu grijă.
                            </p>
                        </div>
                    @endif
                @endif

                {{-- Credite: cumpărare de către participanți --}}
                @if ($m->key === 'credit')
                    @if ($creditsEnabled)
                        @if ($creditBlock)
                            <p class="mt-1.5 text-xs text-warning">Nu se pot dezactiva: {{ $creditBlock }}</p>
                        @endif
                        <div class="mt-3 flex items-center gap-3">
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-ink">Participanții pot cumpăra credite</span>
                                <span class="block text-xs text-ink-soft">În aplicația participanților apare „Cumpără credite”. Îl poți opri oricând; soldurile existente rămân și se pot folosi.</span>
                            </span>
                            <button type="button" role="switch" aria-checked="{{ $creditsPurchasable ? 'true' : 'false' }}"
                                    aria-label="Participanții pot cumpăra credite"
                                    wire:click="toggleCreditsPurchasable"
                                    class="{{ $switch }} {{ $creditsPurchasable ? 'bg-primary' : 'bg-border' }}">
                                <span class="{{ $knob }} {{ $creditsPurchasable ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </div>
                    @else
                        <p class="mt-1.5 text-xs text-ink-soft">Creditele plătesc intrarea și barul. Activate, participanții vor putea cumpăra credite din aplicație.</p>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    {{-- Adaugă metodă custom --}}
    <div class="mt-4">
        <label class="block text-xs font-medium text-ink-soft">Adaugă altă metodă</label>
        <div class="mt-1.5 flex items-center gap-2">
            <input type="text" wire:model="newLabel" x-on:keydown.enter.prevent="$wire.add()" maxlength="60" placeholder="ex. Vouchere partenere"
                   class="flex-1 min-w-0 rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            <x-btn variant="primary" wire:click="add">Adaugă</x-btn>
        </div>
    </div>

    {{-- Confirmare ștergere --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Șterge metoda de plată</h3>
            <p class="mt-2 text-sm text-ink-soft">Sigur vrei să ștergi „<span x-text="confirmName"></span>”? Acțiunea nu poate fi anulată.</p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="confirmOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="danger" x-on:click="run()">Șterge</x-btn>
            </div>
        </div>
    </div>

    {{-- Confirmare oprire tokeni --}}
    <div x-show="tokensOffOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="tokensOffOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Oprești tokenii?</h3>
            <p class="mt-2 text-sm text-ink-soft leading-relaxed">
                Tokenii nu se vor mai accepta la bar. Dacă participanții mai au tokeni cumpărați, ei nu-i vor mai putea folosi.
                Dacă vrei o retragere treptată, alege mai bine „Doar încasare”.
            </p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="tokensOffOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="danger" x-on:click="tokensOff()">Oprește tokenii</x-btn>
            </div>
        </div>
    </div>
</div>
