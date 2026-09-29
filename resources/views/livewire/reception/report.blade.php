@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $signed = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs((float) $n), 2, ',', '.');
    $input = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm font-normal text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary disabled:bg-bg disabled:text-ink-soft';
    $card = 'rounded-2xl border border-border bg-surface p-4';
    $diff = $fig?->cash_diff;
    $diffOk = $diff !== null && abs($diff) < 0.005;
    $lock = $submitted;
@endphp
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-ink">Raportare</h1>
        </div>
        @include('livewire.reception._sale-nav', ['current' => 'report'])
    </div>
    @if ($party)<p class="-mt-1 text-sm text-ink-soft">{{ $party->name }}</p>@endif

    <x-flash />

    @if (! $party)
        <div class="{{ $card }} text-sm text-ink-soft">Alege mai întâi petrecerea.</div>
    @elseif (! $session)
        <div class="{{ $card }} text-sm text-ink-soft">Nicio sesiune de recepție deschisă la această petrecere: nu ai ce raporta încă.</div>
    @else
        @if ($submitted)
            <div class="rounded-2xl bg-primary text-white p-4">
                <div class="text-base font-semibold">Raportare trimisă</div>
                <p class="mt-1 text-sm text-white/85">Așteaptă un admin care o verifică și închide casa. Până atunci nu se mai poate înregistra sau anula nimic.</p>
            </div>
        @endif

        {{-- Totaluri sesiune --}}
        <div class="{{ $card }}">
            <h2 class="text-sm font-semibold text-ink">Sesiunea de azi</h2>
            <div class="mt-3 grid grid-cols-2 gap-2.5">
                <div class="rounded-xl border border-border px-3 py-2.5">
                    <div class="text-[11px] text-ink-soft">Intrări</div>
                    <div class="text-xl font-semibold text-ink">{{ $fig->entries_count }}</div>
                    <div class="text-[11px] text-ink-soft">{{ $money($fig->entries_revenue) }} lei @if ($fig->entries_free > 0)· {{ $fig->entries_free }} gratuite @endif</div>
                </div>
                <div class="rounded-xl border border-border px-3 py-2.5">
                    <div class="text-[11px] text-ink-soft">Tokeni vânduți</div>
                    <div class="text-xl font-semibold text-ink">{{ $fig->tokens_sold }}</div>
                    <div class="text-[11px] text-ink-soft">{{ $money($fig->tokens_amount) }} lei</div>
                </div>
                <div class="rounded-xl border border-border px-3 py-2.5 col-span-2">
                    <div class="text-[11px] text-ink-soft">Credite vândute</div>
                    <div class="text-xl font-semibold text-ink">{{ $money($fig->credits_amount) }} lei</div>
                </div>
            </div>
            @if ($fig->methods)
                <div class="mt-3 space-y-1 text-sm">
                    <div class="text-[11px] uppercase tracking-wide text-ink-soft/70">Încasat pe metodă</div>
                    @foreach ($fig->methods as $m)
                        <div class="flex items-baseline justify-between gap-3"><span class="text-ink-soft">{{ $m['label'] }}</span><span class="font-medium text-ink">{{ $money($m['amount']) }} lei</span></div>
                    @endforeach
                </div>
            @endif
            @if ($fig->entries_cancelled + $fig->token_sales_cancelled + $fig->credit_sales_cancelled > 0)
                <p class="mt-3 text-xs text-ink-soft">Anulate în sesiune: {{ $fig->entries_cancelled }} intrări, {{ $fig->token_sales_cancelled }} vânzări de tokeni, {{ $fig->credit_sales_cancelled }} vânzări de credite (nu intră în totaluri).</p>
            @endif
        </div>

        {{-- Casa (cash) --}}
        <div class="{{ $card }} space-y-4">
            <h2 class="text-sm font-semibold text-ink">Casa (cash)</h2>

            <div>
                <label class="block text-sm font-medium text-ink mb-1.5">Fond de casă (la început)</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="opening_float" @disabled($lock) placeholder="0" class="{{ $input }}">
            </div>

            <div class="rounded-xl bg-bg px-4 py-3 space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-ink-soft">+ Cash din intrări</span><span class="text-ink">{{ $money($fig->cash_entries) }} lei</span></div>
                <div class="flex justify-between"><span class="text-ink-soft">+ Cash din tokeni</span><span class="text-ink">{{ $money($fig->cash_tokens) }} lei</span></div>
                <div class="flex justify-between"><span class="text-ink-soft">+ Cash din credite</span><span class="text-ink">{{ $money($fig->cash_credits) }} lei</span></div>
                @if ($fig->handed_over > 0)
                    <div class="flex justify-between"><span class="text-ink-soft">− Scos din casă</span><span class="text-ink">{{ $money($fig->handed_over) }} lei</span></div>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-ink mb-1">Bani scoși din casă în timpul serii</label>
                <p class="mb-1.5 text-xs text-ink-soft">Cât cash ai scos din casă pe parcurs (predat cuiva, depus, cheltuieli). Se scade din cash-ul așteptat. Dacă n-ai scos nimic, lasă gol.</p>
                <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="handed_over" @disabled($lock) placeholder="0" class="{{ $input }}">
                <input type="text" wire:model="handed_note" maxlength="255" @disabled($lock) placeholder="Cui ai dat banii / pentru ce (opțional)" class="{{ $input }} mt-2">
            </div>

            <div class="flex items-baseline justify-between gap-3 rounded-xl bg-bg px-4 py-3">
                <span class="text-sm text-ink-soft">Cash așteptat</span>
                <span class="text-xl font-semibold text-primary">{{ $money($fig->expected_cash) }} <span class="text-sm font-normal text-ink-soft">lei</span></span>
            </div>

            <div>
                <label class="block text-sm font-medium text-ink mb-1.5">Cash numărat</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="counted_cash" @disabled($lock) placeholder="Cât ai numărat în casă" class="{{ $input }}">
            </div>

            @if ($diff !== null)
                <div class="rounded-xl border px-4 py-3 {{ $diffOk ? 'border-success/30 bg-success-soft' : 'border-warning/40 bg-warning/10' }}">
                    <div class="text-[11px] text-ink-soft">Diferență</div>
                    <div class="text-xl font-semibold {{ $diffOk ? 'text-success' : 'text-warning' }}">
                        {{ $diffOk ? 'Casă corectă' : $signed($diff).' lei' }}
                    </div>
                    @unless ($diffOk)
                        <div class="text-xs text-ink-soft">{{ $diff < 0 ? 'Lipsesc '.$money(abs($diff)).' lei' : 'În plus '.$money($diff).' lei' }}</div>
                    @endunless
                </div>
            @endif
        </div>

        {{-- Tokeni --}}
        @if (\App\Support\PaymentMethods::tokenMode() !== \App\Support\PaymentMethods::TOKEN_OFF || $fig->tokens_sold > 0 || $fig->opening_tokens > 0 || $fig->counted_tokens !== null)
            @php $tdiff = $fig->tokens_diff; @endphp
            <div class="{{ $card }} space-y-4">
                <h2 class="text-sm font-semibold text-ink">Tokeni</h2>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1.5">Fond de tokeni (la început)</label>
                    <input type="text" inputmode="numeric" wire:model.live.debounce.400ms="opening_tokens" @disabled($lock) placeholder="0" class="{{ $input }}">
                </div>
                <div class="rounded-xl bg-bg px-4 py-3 space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-ink-soft">Fond</span><span class="text-ink">{{ $fig->opening_tokens }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-soft">− Tokeni vânduți</span><span class="text-ink">{{ $fig->tokens_sold }}</span></div>
                    <div class="flex justify-between font-medium"><span class="text-ink-soft">= Așteptați rămași</span><span class="text-primary">{{ $fig->expected_tokens }}</span></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1.5">Tokeni numărați</label>
                    <input type="text" inputmode="numeric" wire:model.live.debounce.400ms="counted_tokens" @disabled($lock) placeholder="Câți tokeni au rămas" class="{{ $input }}">
                </div>
                @if ($tdiff !== null)
                    <div class="rounded-xl border px-4 py-3 {{ $tdiff === 0 ? 'border-success/30 bg-success-soft' : 'border-warning/40 bg-warning/10' }}">
                        <div class="text-[11px] text-ink-soft">Diferență tokeni</div>
                        <div class="text-xl font-semibold {{ $tdiff === 0 ? 'text-success' : 'text-warning' }}">{{ $tdiff === 0 ? 'Tokeni corecți' : ($tdiff > 0 ? '+' : '−').abs($tdiff) }}</div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Note --}}
        <div class="{{ $card }} space-y-3">
            <h2 class="text-sm font-semibold text-ink">Note</h2>
            @foreach ($fig->other_methods as $m)
                <div wire:key="mn-{{ $m['key'] }}">
                    <label class="block text-sm font-medium text-ink mb-1.5">{{ $m['label'] }} · {{ $money($m['amount']) }} lei</label>
                    <input type="text" wire:model="method_notes.{{ $m['key'] }}" maxlength="255" @disabled($lock) placeholder="Notă (ex. total din POS)" class="{{ $input }}">
                </div>
            @endforeach
            <div>
                <label class="block text-sm font-medium text-ink mb-1.5">Observații</label>
                <textarea wire:model="note" rows="2" maxlength="2000" @disabled($lock) placeholder="Orice e de știut despre seara asta (opțional)" class="{{ $input }}"></textarea>
            </div>
        </div>

        {{-- Mesajele stau lângă butoane și se aduc în vizor (altfel nu se văd când pagina e derulată jos) --}}
        @if ($error)
            <div wire:key="rp-error-{{ md5($error) }}" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })">
                <x-alert type="error">{{ $error }}</x-alert>
            </div>
        @endif
        @if ($message && ! $submitted)
            <div wire:key="rp-msg-{{ md5($message) }}" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })">
                <x-alert type="success">{{ $message }}</x-alert>
            </div>
        @endif

        @unless ($lock)
            <div class="grid grid-cols-2 gap-3">
                <x-btn variant="neutral" wire:click="save" class="w-full">Salvează</x-btn>
                <x-btn variant="primary" wire:click="askSubmit" class="w-full">Trimite raportarea</x-btn>
            </div>
            <p class="text-xs text-ink-soft text-center">Trimiterea nu închide casa: un admin o verifică și o finalizează.</p>
        @endunless

        {{-- Confirmare --}}
        @if ($confirming)
            <div class="fixed inset-0 z-[70] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-ink/50" wire:click="cancelSubmit"></div>
                <div class="relative w-full max-w-sm rounded-2xl bg-surface border border-border shadow-lg p-5">
                    <h3 class="text-base font-semibold text-ink">Trimiți raportarea?</h3>
                    <div class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-ink-soft">Așteptat</span><span class="text-ink">{{ $money($fig->expected_cash) }} lei</span></div>
                        <div class="flex justify-between"><span class="text-ink-soft">Numărat</span><span class="text-ink">{{ $money($fig->counted_cash) }} lei</span></div>
                        <div class="flex justify-between font-semibold {{ $diffOk ? 'text-success' : 'text-warning' }}"><span>Diferență</span><span>{{ $diffOk ? 'casă corectă' : $signed($diff).' lei' }}</span></div>
                    </div>
                    <p class="mt-3 text-xs text-ink-soft">După trimitere nu mai poți înregistra sau anula nimic în sesiune, până decide un admin.</p>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <x-btn variant="neutral" wire:click="cancelSubmit">Renunț</x-btn>
                        <x-btn variant="primary" wire:click="submit">Trimite</x-btn>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
