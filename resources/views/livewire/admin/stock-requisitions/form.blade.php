<div>
    <div class="max-w-2xl">
        @php
            $isEditing = $requisition && $requisition->exists;

            $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');

            $partyOptions = ['' => 'Fără petrecere'] + $parties->mapWithKeys(fn ($p) => [
                $p->id => $p->name.($p->starts_at ? ' — '.$p->starts_at->format('d.m.Y') : ''),
            ])->all();

            $stockItemOptions = $stockItems->mapWithKeys(fn ($s) => [$s->id => $s->name.' ('.$s->unit.')'])->all();
        @endphp

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-ink">{{ $isEditing ? 'Editează necesarul' : 'Necesar nou' }}</h2>
            <p class="mt-1 text-sm text-ink-soft leading-relaxed">
                Lista de produse de stoc pe care trebuie să le cumperi. Când intră marfa, o treci în Raportări, iar necesarul se bifează singur.
            </p>
        </div>

        <form wire:submit="save" class="bg-surface border border-border rounded-2xl p-6 space-y-5">

            {{-- Denumire --}}
            <div>
                <label for="label" class="block text-sm font-medium text-ink">Denumire</label>
                <input type="text" id="label" wire:model="label" placeholder="ex. Necesar 12.10.2026"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('label') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Petrecere (opțional) --}}
            <div>
                <label class="block text-sm font-medium text-ink mb-1.5">Petrecere <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                <x-select wire:model="party_id" placeholder="Fără petrecere" :options="$partyOptions" />
                @error('party_id') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Produse --}}
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <label class="block text-sm font-medium text-ink">Produse</label>
                    <x-btn variant="neutral" size="sm" wire:click="addLowStock" wire:loading.attr="disabled" wire:target="addLowStock"
                           title="Adaugă produsele la/sub pragul minim, cu cantitatea egală cu pragul">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Adaugă produsele sub minim
                    </x-btn>
                </div>

                @if ($lowStockMessage)
                    <x-alert type="info" dismiss-prop="lowStockMessage" :dismiss-value="null" class="mb-3">{{ $lowStockMessage }}</x-alert>
                @endif

                <div class="space-y-4">
                    @foreach ($lines as $i => $line)
                        @php
                            $sid = ! empty($line['stock_item_id']) ? (int) $line['stock_item_id'] : null;
                            $si = $sid ? $stockItems->firstWhere('id', $sid) : null;
                            $got = $sid ? ($received[$sid] ?? 0) : 0;
                            $locked = $got > 0; // a primit deja marfa din Raportari: produsul nu se mai schimba/scoate

                            // Un produs poate aparea o singura data: excludem din optiuni ce e ales pe alte randuri.
                            $otherIds = collect($lines)->except($i)->pluck('stock_item_id')->filter()->map(fn ($v) => (int) $v)->all();
                            $rowOptions = array_diff_key($stockItemOptions, array_flip($otherIds));
                        @endphp

                        <div wire:key="line-{{ $i }}">
                            <div class="flex items-start gap-2">
                                @if ($locked && $si)
                                    <div class="flex-1 min-w-0 rounded-lg border border-border bg-bg px-3.5 py-2.5 text-sm text-ink truncate">
                                        {{ $si->name }} <span class="text-ink-soft/60">({{ $si->unit }})</span>
                                    </div>
                                @else
                                    <x-dropdown-select path="lines.{{ $i }}.stock_item_id" :options="$rowOptions" :selected="$line['stock_item_id'] ?? ''" placeholder="Alege produsul…" class="flex-1" />
                                @endif

                                <div class="w-32 shrink-0">
                                    <div class="relative">
                                        <input type="number" step="0.001" min="0" wire:model="lines.{{ $i }}.qty" placeholder="Cant."
                                               class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary {{ $si ? 'pr-10' : '' }}">
                                        @if ($si)
                                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-ink-soft/60">{{ $si->unit }}</span>
                                        @endif
                                    </div>
                                </div>

                                @if ($sid && ! $locked)
                                    <button type="button" wire:click="removeLine({{ $i }})" title="Elimină linia"
                                            class="shrink-0 inline-flex items-center justify-center w-[42px] h-[42px] rounded-lg text-ink-soft/60 hover:text-danger hover:bg-danger/10">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                @endif
                            </div>

                            @if ($si)
                                {{-- Context pt. a decide cantitatea: stoc curent, prag, cat s-a primit deja --}}
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-ink-soft">
                                    <span>
                                        În stoc: <span class="{{ $si->isBelowMinStock() ? 'text-danger font-semibold' : 'text-ink' }}">{{ $fmt($si->stock_qty) }} {{ $si->unit }}</span>
                                        @if ($si->hasPackage())
                                            <span class="text-ink-soft/60">({{ $si->packageDisplay() }})</span>
                                        @endif
                                    </span>
                                    @if ($si->min_stock !== null)
                                        <span>Prag: {{ $fmt($si->min_stock) }} {{ $si->unit }}</span>
                                    @endif
                                    @if ($si->isBelowMinStock())
                                        <span class="inline-flex items-center rounded-full bg-danger/10 text-danger text-[10px] font-semibold px-1.5 py-0.5">sub minim</span>
                                    @endif
                                    @if ($locked)
                                        <span class="text-primary font-medium">Primit deja: {{ $fmt($got) }} {{ $si->unit }}</span>
                                    @endif
                                </div>

                                {{-- Calculator de cantitate (bucati x cant./bucata). wire:key include produsul ales, ca
                                     sa se recreeze (inchis, cu ambalajul noului produs) cand se schimba produsul de pe rand. --}}
                                @if ($si->unit !== 'buc')
                                    <x-qty-helper wire:key="qty-helper-line-{{ $i }}-{{ $sid }}" path="lines.{{ $i }}.qty" :unit="$units[$si->unit] ?? $si->unit" :default-size="$si->hasPackage() ? $si->package_qty : null" />
                                @endif
                            @endif

                            @error('lines.'.$i.'.stock_item_id') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                            @error('lines.'.$i.'.qty') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>

                @error('lines') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Acțiuni --}}
            <div class="flex items-center gap-4 pt-2">
                <x-btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                    {{ $isEditing ? 'Salvează modificările' : 'Creează necesarul' }}
                </x-btn>
                <a href="{{ route('admin.stock-requisitions.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">
                    Anulează
                </a>
            </div>

        </form>
    </div>
</div>
