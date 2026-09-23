<div class="max-w-3xl">
    @php
        $money = fn ($n) => number_format((float) $n, 2, ',', '.');
        $groupOptions = ['' => 'Fără sesiune (vânzare simplă)'] + $groups->mapWithKeys(fn ($g) => [$g->id => $g->label()])->all();
        $menuItemOptions = $menuItems->mapWithKeys(fn ($m) => [$m->id => $m->name.' · '.number_format((float) $m->price, 2, ',', '.').' lei'])->all();
        $inputClass = 'w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
    @endphp

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">Vânzare nouă</h2>
        <p class="mt-1 text-sm text-ink-soft">Înregistrare manuală din admin. Stocul nu se modifică acum, ci la finalizarea raportării.</p>
    </div>

    @error('form') <x-alert type="error" class="mb-4">{{ $message }}</x-alert> @enderror

    {{-- Grup --}}
    <section class="mb-5 rounded-2xl border border-border bg-surface p-4">
        <label class="block text-sm font-medium text-ink mb-1.5">Sesiune de vânzări</label>
        <x-select wire:model="sales_group_id" placeholder="Fără sesiune (vânzare simplă)" :options="$groupOptions" />
        <p class="mt-1.5 text-xs text-ink-soft">Alege sesiunea petrecerii, dacă vânzarea ține de una; altfel las-o „Fără sesiune". Sesiuni noi se deschid din pagina <a href="{{ route('admin.sales.index') }}" wire:navigate class="text-primary hover:underline">Vânzări</a>.</p>
    </section>

    {{-- Produse --}}
    <section class="mb-5 rounded-2xl border border-border border-l-2 border-l-info bg-surface p-4">
        <h3 class="text-base font-semibold text-ink mb-3">Produse</h3>
        <div class="space-y-3">
            @foreach ($lines as $i => $line)
                <div wire:key="line-{{ $i }}" class="flex items-start gap-2">
                    <x-dropdown-select path="lines.{{ $i }}.menu_item_id" :options="$menuItemOptions" :selected="$line['menu_item_id'] ?? ''" placeholder="Alege produsul…" class="flex-1" />
                    <div class="w-24 shrink-0">
                        <input type="number" step="1" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.qty" placeholder="Cant." class="{{ $inputClass }}">
                    </div>
                    @if (! empty($line['menu_item_id']))
                        <button type="button" wire:click="removeLine({{ $i }})" title="Elimină"
                                class="shrink-0 inline-flex items-center justify-center w-[42px] h-[42px] rounded-lg text-ink-soft/60 hover:text-danger hover:bg-danger/10">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4 flex items-center justify-between border-t border-border pt-3">
            <span class="text-sm text-ink-soft">Total</span>
            <span class="text-lg font-semibold text-ink">{{ $money($total) }} lei</span>
        </div>
    </section>

    {{-- Plăți --}}
    <section class="mb-5 rounded-2xl border border-border border-l-2 border-l-success bg-surface p-4">
        <h3 class="text-base font-semibold text-ink mb-3">Plată <span class="text-xs font-normal text-ink-soft">— poate fi mixtă</span></h3>
        <div class="space-y-3">
            @foreach ($payments as $i => $p)
                <div wire:key="pay-{{ $i }}" class="flex flex-wrap items-start gap-2">
                    <x-select wire:model="payments.{{ $i }}.method" live :options="$methods" class="w-40 shrink-0" />

                    @if (($p['method'] ?? '') === 'token')
                        <div class="w-28 shrink-0">
                            <input type="number" step="1" min="0" wire:model.live.debounce.400ms="payments.{{ $i }}.tokens" placeholder="Tokeni" class="{{ $inputClass }}">
                        </div>
                        <span class="self-center text-xs text-ink-soft">
                            @if (is_numeric($p['tokens'] ?? null) && (int) $p['tokens'] > 0) = {{ $money((int) $p['tokens'] * $tokenRate) }} lei @else {{ $money($tokenRate) }} lei / token @endif
                        </span>
                    @else
                        <div class="w-32 shrink-0">
                            <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="payments.{{ $i }}.amount" placeholder="Lei" class="{{ $inputClass }}">
                        </div>
                    @endif

                    <button type="button" wire:click="fillRemaining({{ $i }})" class="self-center text-xs font-medium text-primary hover:underline">Restul</button>

                    @if (count($payments) > 1)
                        <button type="button" wire:click="removePayment({{ $i }})" title="Elimină plata"
                                class="ml-auto shrink-0 inline-flex items-center justify-center w-[42px] h-[42px] rounded-lg text-ink-soft/60 hover:text-danger hover:bg-danger/10">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        <button type="button" wire:click="addPayment" class="mt-3 text-sm font-medium text-primary hover:underline">+ Altă metodă de plată</button>

        <div class="mt-4 flex items-center justify-between border-t border-border pt-3 text-sm">
            <span class="text-ink-soft">Plătit {{ $money($paid) }} lei</span>
            @if (abs($remaining) < 0.01 && $total > 0)
                <span class="font-medium text-success">Acoperit integral</span>
            @else
                <span class="font-medium {{ $remaining < 0 ? 'text-danger' : 'text-warning' }}">
                    {{ $remaining < 0 ? 'Depășit cu' : 'Rest de plată' }} {{ $money(abs($remaining)) }} lei
                </span>
            @endif
        </div>
    </section>

    <div class="flex items-center gap-3">
        <x-btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">Înregistrează vânzarea</x-btn>
        <a href="{{ route('admin.sales.index') }}" wire:navigate class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</a>
    </div>
</div>
