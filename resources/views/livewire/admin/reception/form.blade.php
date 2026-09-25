@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    // Coloana din dreapta: ~5 randuri vizibile, apoi scroll intern.
    $listBox = 'max-h-[19rem] overflow-y-auto space-y-2 pr-1';
@endphp
<div
    class="max-w-6xl"
    x-data="{
        cancelOpen: false,
        cancelKind: 'entry',
        cancelTarget: null,
        cancelInfo: '',
        cancelReason: '',
        askCancel(kind, target, info) { this.cancelKind = kind; this.cancelTarget = target; this.cancelInfo = info; this.cancelReason = ''; this.cancelOpen = true; },
        tryCancel() {
            if (this.cancelReason.trim().length < 3) { return; }
            this.$wire.call(this.cancelKind === 'token' ? 'cancelTokenSale' : 'cancel', this.cancelTarget, this.cancelReason);
            this.cancelOpen = false;
        },
    }"
>
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">Recepție · Intrări</h2>
        <p class="mt-1 text-sm text-ink-soft">Înregistrează intrările și vinde tokeni la petrecere. Prețul se calculează la ora intrării.</p>
    </div>

    <x-flash class="mb-4" />

    @if (! $partyOptions)
        <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
            Nu există nicio petrecere publicată și neîncheiată. Publică o petrecere ca să poți înregistra intrări.
        </div>
    @else
        {{-- Doua coloane pe ecrane late; coloana din dreapta ramane vizibila la scroll (fara items-start pe grid, ca sticky sa functioneze) --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="xl:col-span-2 space-y-4 min-w-0">
                {{-- Petrecerea --}}
                <div class="{{ $card }}">
                    <label class="block text-sm font-medium text-ink mb-1.5">Petrecere</label>
                    <x-select wire:model="partyId" live :options="$partyOptions" />
                </div>

                {{-- Contoare --}}
                @if ($stats)
                    <div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
                                <div class="text-[11px] text-ink-soft">Intrați</div>
                                <div class="text-lg font-semibold text-ink">{{ $stats->count }}</div>
                            </div>
                            <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
                                <div class="text-[11px] text-ink-soft">Plătite</div>
                                <div class="text-lg font-semibold text-ink">{{ $stats->paid }}</div>
                            </div>
                            <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
                                <div class="text-[11px] text-ink-soft">Gratuite</div>
                                <div class="text-lg font-semibold text-ink">{{ $stats->free }}</div>
                            </div>
                            <div class="rounded-xl border border-border bg-surface px-3 py-2.5">
                                <div class="text-[11px] text-ink-soft">Încasat</div>
                                <div class="text-lg font-semibold text-ink">{{ $money($stats->revenue) }} <span class="text-xs font-normal text-ink-soft">lei</span></div>
                            </div>
                        </div>
                        @if ($stats->payments)
                            <p class="mt-2 text-xs text-ink-soft">
                                @foreach ($stats->payments as $method => $amount)
                                    {{ $methodLabels[$method] ?? $method }} {{ $money($amount) }} lei @if (! $loop->last) · @endif
                                @endforeach
                            </p>
                        @endif
                    </div>
                @endif

                {{-- Intrare nouă --}}
                <div class="{{ $card }} space-y-5">
                    <h3 class="text-sm font-semibold text-ink">Intrare nouă</h3>

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

                        {{-- Total + plată pe total --}}
                        <div class="rounded-xl bg-bg px-4 py-3">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm text-ink-soft">Total ({{ (int) $count > 0 ? (int) $count : 1 }} × {{ $money($unit) }} lei)</span>
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

                        <x-btn variant="primary" wire:click="save" class="w-full sm:w-auto">Înregistrează intrarea</x-btn>
                    @endif
                </div>

                {{-- DXA: adaugat (Recepție - tokeni): vânzare de tokeni pentru petrecerea aleasă --}}
                @if ($party)
                    @livewire(\App\Livewire\Admin\Reception\TokenSale::class, ['partyId' => $party->id], key('token-sale-'.$party->id))
                @endif
            </div>

            {{-- Coloana din dreapta: ultimele intrări și ultimele vânzări de tokeni (~5 rânduri, apoi scroll) --}}
            <div class="min-w-0">
                <div class="xl:sticky xl:top-4 space-y-4">
                    <div class="{{ $card }}">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-ink">Ultimele intrări</h3>
                            <a href="{{ route('admin.reception.index') }}" wire:navigate class="text-xs text-primary hover:underline shrink-0">Toate intrările →</a>
                        </div>
                        @if ($entryCancelMessage)
                            <x-alert type="success" class="mt-3">{{ $entryCancelMessage }}</x-alert>
                        @endif
                        @if ($entryCancelError)
                            <x-alert type="error" class="mt-3">{{ $entryCancelError }}</x-alert>
                        @endif
                        @if ($recent->isEmpty())
                            <p class="mt-2 text-sm text-ink-soft">Nicio intrare înregistrată încă.</p>
                        @else
                            <div class="mt-3 {{ $listBox }}">
                                @foreach ($recent as $r)
                                    @php $entryInfo = $r->count.' × '.$r->ticket; @endphp
                                    <div wire:key="entry-{{ $r->batch }}" class="rounded-xl border border-border px-3 py-2 {{ $r->cancelled ? 'bg-bg' : 'bg-surface' }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium {{ $r->cancelled ? 'text-ink-soft line-through' : 'text-ink' }}">
                                                    {{ $r->count }} × {{ $r->ticket }} · {{ $r->total > 0 ? $money($r->total).' lei' : 'gratuit' }}
                                                </div>
                                                <div class="mt-0.5 text-xs text-ink-soft">
                                                    {{ $r->at->format('H:i') }}
                                                    @if ($r->by) · {{ $r->by }} @endif
                                                    @foreach ($r->payments as $method => $amount)
                                                        · {{ $methodLabels[$method] ?? $method }} {{ $money($amount) }}
                                                    @endforeach
                                                    @if ($r->grace && ! $r->override_reason) · în toleranță @endif
                                                </div>
                                                @if ($r->participants)
                                                    <div class="mt-0.5 text-xs text-ink">{{ implode(', ', array_slice($r->participants, 0, 3)) }}@if (count($r->participants) > 3) +{{ count($r->participants) - 3 }} @endif</div>
                                                @endif
                                                @if ($r->override_reason)
                                                    <div class="mt-0.5 text-xs text-warning">Preț schimbat: {{ $r->override_reason }}</div>
                                                @endif
                                                @if ($r->cancelled)
                                                    <div class="mt-0.5 text-xs text-danger">Anulat: {{ $r->cancel_reason }}</div>
                                                @endif
                                            </div>
                                            @unless ($r->cancelled)
                                                <x-btn variant="danger" size="sm" outline
                                                       x-on:click="askCancel('entry', '{{ $r->batch }}', '{{ addslashes($entryInfo) }}')">Anulează</x-btn>
                                            @endunless
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="{{ $card }}">
                        <h3 class="text-sm font-semibold text-ink">Ultimele vânzări de tokeni</h3>
                        @if ($tokenCancelMessage)
                            <x-alert type="success" class="mt-3">{{ $tokenCancelMessage }}</x-alert>
                        @endif
                        @if ($tokenCancelError)
                            <x-alert type="error" class="mt-3">{{ $tokenCancelError }}</x-alert>
                        @endif
                        @if ($recentTokens->isEmpty())
                            <p class="mt-2 text-sm text-ink-soft">Nicio vânzare de tokeni încă.</p>
                        @else
                            <div class="mt-3 {{ $listBox }}">
                                @foreach ($recentTokens as $tx)
                                    @php $tokenInfo = number_format($tx->tokens, 0, ',', '.').' tokeni'; @endphp
                                    <div wire:key="tsale-{{ $tx->id }}" class="rounded-xl border border-border px-3 py-2 {{ $tx->isCancelled() ? 'bg-bg' : 'bg-surface' }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium {{ $tx->isCancelled() ? 'text-ink-soft line-through' : 'text-ink' }}">
                                                    {{ $tokenInfo }} · {{ $money($tx->amount) }} lei
                                                </div>
                                                <div class="mt-0.5 text-xs text-ink-soft">
                                                    {{ $tx->occurred_at->format('H:i') }}
                                                    @if ($tx->creator) · {{ $tx->creator->name }} @endif
                                                    @foreach ($tx->payments as $pay)
                                                        · {{ $methodLabels[$pay->method] ?? $pay->method }} {{ $money($pay->amount) }}
                                                    @endforeach
                                                </div>
                                                @if ($tx->participant)
                                                    <div class="mt-0.5 text-xs text-ink">{{ $tx->participant->name }}</div>
                                                @endif
                                                @if ($tx->isCancelled())
                                                    <div class="mt-0.5 text-xs text-danger">Anulat: {{ $tx->cancel_reason }}</div>
                                                @endif
                                            </div>
                                            @unless ($tx->isCancelled())
                                                <x-btn variant="danger" size="sm" outline
                                                       x-on:click="askCancel('token', {{ $tx->id }}, '{{ addslashes($tokenInfo) }}')">Anulează</x-btn>
                                            @endunless
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Anulare (intrare sau vânzare de tokeni): motiv obligatoriu --}}
    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="cancelOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink" x-text="cancelKind === 'token' ? 'Anulează vânzarea de tokeni' : 'Anulează intrarea'"></h3>
            <p class="mt-2 text-sm text-ink-soft">
                Anulezi <span class="font-medium text-ink" x-text="cancelInfo"></span>.
                <span x-show="cancelKind !== 'token'">Intrarea rămâne în istoric, marcată ca anulată.</span>
                <span x-show="cancelKind === 'token'">Se poate doar dacă tokenii nu au fost deja folosiți la bar.</span>
            </p>
            <input type="text" x-model="cancelReason" maxlength="255" placeholder="Motiv (obligatoriu)" x-on:keydown.enter.prevent="tryCancel()"
                   class="mt-3 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            <div class="mt-5 flex items-center justify-end gap-3">
                <button type="button" @click="cancelOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Renunță</button>
                <x-btn variant="danger" x-on:click="tryCancel()">Anulează</x-btn>
            </div>
        </div>
    </div>
</div>
