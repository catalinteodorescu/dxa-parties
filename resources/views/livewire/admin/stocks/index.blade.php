<div
    x-data="{
        confirmOpen: false,
        confirmTitle: '',
        confirmMessage: '',
        confirmMethod: '',
        confirmArgs: [],
        confirmActionLabel: 'Confirmă',
        confirmVariant: 'danger',
        askConfirm(title, message, method, args, actionLabel = 'Confirmă', variant = 'danger') {
            this.confirmTitle = title;
            this.confirmMessage = message;
            this.confirmMethod = method;
            this.confirmArgs = args;
            this.confirmActionLabel = actionLabel;
            this.confirmVariant = variant;
            this.confirmOpen = true;
        },
        runConfirm() {
            this.$wire.call(this.confirmMethod, ...this.confirmArgs);
            this.confirmOpen = false;
        },
    }"
>
    @php
        $hasFilters = $search !== '' || $state !== 'all' || $lowOnly;
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-ink">Bar — Stocuri</h2>
            <p class="mt-1 text-sm text-ink-soft">Produsele de stoc (ingrediente/materii prime) folosite în rețetele articolelor de meniu — nu apar direct în meniul barului.</p>
        </div>
        <x-btn variant="primary" wire:click="openCreate" class="self-start">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Produs de stoc nou
        </x-btn>
    </div>

    {{-- Filtre + căutare --}}
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <div class="relative sm:flex-1 sm:min-w-48">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Caută după nume…"
                   class="w-full rounded-lg border border-border bg-white pl-9 pr-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
        </div>
        <x-select wire:model="state" live class="sm:w-40"
                  :options="['all' => 'Toate stările', 'active' => 'Active', 'inactive' => 'Inactive']" />
        <button type="button" wire:click="$set('lowOnly', {{ $lowOnly ? 'false' : 'true' }})"
                class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition-colors
                       {{ $lowOnly ? 'border-danger bg-danger/10 text-danger' : 'border-border text-ink-soft hover:bg-bg' }}">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Sub stoc minim
        </button>
        @if ($hasFilters)
            <button type="button" wire:click="clearFilters" class="text-sm text-ink-soft hover:text-ink px-2 py-2 self-start">
                Resetează
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-primary-soft text-primary text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-danger/10 text-danger text-sm px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    {{-- Listă: pe desktop arată ca tabel, pe mobil casete cu label+valoare --}}
    <div class="rounded-2xl border border-border bg-surface overflow-hidden">
        <div class="hidden md:grid grid-cols-[1fr_14rem_6rem_15rem] gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-ink-soft/70 border-b border-border">
            <span>Denumire</span>
            <span>Stoc curent</span>
            <span>Stare</span>
            <span class="text-right">Acțiuni</span>
        </div>

        @forelse ($items as $item)
            <div wire:key="stock-{{ $item->id }}" class="border-b border-border last:border-0">
                <div class="p-3 md:px-4 md:py-2.5 md:grid md:grid-cols-[1fr_14rem_6rem_15rem] md:items-center md:gap-3">

                    {{-- Denumire --}}
                    <div class="min-w-0">
                        <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Denumire</span>
                        <span class="font-medium text-ink truncate">{{ $item->name }}</span>
                        <span class="ml-1.5 text-xs text-ink-soft/60">({{ $units[$item->unit] ?? $item->unit }})</span>
                    </div>

                    {{-- Stoc curent: cantitate + echivalent + alerta, totul intr-o coloana --}}
                    <div class="mt-2 md:mt-0">
                        <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Stoc curent</span>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-sm text-ink {{ $item->isBelowMinStock() ? 'text-danger font-semibold' : '' }}">
                                {{ rtrim(rtrim(number_format((float) $item->stock_qty, 3, ',', '.'), '0'), ',') }} {{ $item->unit }}
                            </span>
                            @if ($item->isBelowMinStock())
                                <span class="inline-flex items-center rounded-full bg-danger/10 text-danger text-[10px] font-semibold px-1.5 py-0.5">sub minim</span>
                            @endif
                        </div>
                        @if ($item->hasPackage())
                            <span class="block text-xs text-ink-soft/70">{{ $item->packageDisplay() }}</span>
                        @endif
                    </div>

                    {{-- Stare --}}
                    <div class="mt-2 md:mt-0">
                        <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Stare</span>
                        @if ($item->is_active)
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-primary-soft text-primary">Activ</span>
                        @else
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-surface border border-border text-ink-soft">Inactiv</span>
                        @endif
                    </div>

                    {{-- Acțiuni --}}
                    <div class="mt-3 md:mt-0 flex items-center gap-2 md:justify-end">
                        <x-btn variant="neutral" size="icon" outline
                               tooltip="{{ $expandedId === $item->id ? 'Ascunde detalii' : 'Detalii' }}"
                               wire:click="toggleExpand({{ $item->id }})">
                            <svg class="w-3 h-3 transition-transform {{ $expandedId === $item->id ? 'rotate-180' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </x-btn>

                        <x-btn variant="neutral" size="icon" outline tooltip="Corectează stoc" wire:click="openQtyModal({{ $item->id }})">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                            </svg>
                        </x-btn>

                        <x-btn variant="neutral" size="icon" outline tooltip="Corectează cost" wire:click="openCostModal({{ $item->id }})">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"/><line x1="12" y1="7" x2="12" y2="17"/><line x1="9" y1="10" x2="15" y2="10"/>
                            </svg>
                        </x-btn>

                        @if ($item->is_active)
                            <x-btn variant="info" size="icon" outline tooltip="Dezactivează"
                                   x-on:click="askConfirm('Dezactivează produs de stoc', 'Sigur vrei să dezactivezi „{{ addslashes($item->name) }}”? Nu va mai putea fi folosit în rețete noi, dar rămâne în cele existente.', 'toggleActive', [{{ $item->id }}], 'Dezactivează', 'info')">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20C5 20 1 12 1 12a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                    <path d="M1 1l22 22"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                                </svg>
                            </x-btn>
                        @else
                            <x-btn variant="info" size="icon" outline tooltip="Activează" wire:click="toggleActive({{ $item->id }})">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </x-btn>
                        @endif

                        <x-btn variant="warning" size="icon" outline tooltip="Editează" wire:click="openEdit({{ $item->id }})">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                            </svg>
                        </x-btn>

                        <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                               x-on:click="askConfirm('Șterge produs de stoc', 'Sigur vrei să ștergi „{{ addslashes($item->name) }}”? Acțiunea nu poate fi anulată.', 'delete', [{{ $item->id }}], 'Șterge', 'danger')">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                            </svg>
                        </x-btn>
                    </div>
                </div>

                {{-- Panou expandat: prag minim, cost unitar, valoare totala + istoric miscari --}}
                @if ($expandedId === $item->id)
                    <div class="px-4 pb-4 pt-1 bg-bg border-t border-border">
                        <div class="flex flex-wrap gap-x-10 gap-y-3 py-3">
                            <div>
                                <span class="block text-[11px] uppercase tracking-wide text-ink-soft/60 mb-0.5">Prag alertă</span>
                                @if ($item->min_stock !== null)
                                    <span class="text-sm text-ink">{{ rtrim(rtrim(number_format((float) $item->min_stock, 3, ',', '.'), '0'), ',') }} {{ $item->unit }}</span>
                                    @if ($item->hasPackage())
                                        <span class="block text-xs text-ink-soft/60">{{ $item->minStockPackageDisplay() }}</span>
                                    @endif
                                @else
                                    <span class="text-sm text-ink-soft/40">—</span>
                                @endif
                            </div>
                            <div>
                                <span class="block text-[11px] uppercase tracking-wide text-ink-soft/60 mb-0.5">Cost unitar</span>
                                @if ($item->hasKnownCost())
                                    <span class="text-sm text-ink">{{ number_format((float) $item->avg_cost, 4, ',', '.') }} lei</span>
                                @else
                                    <span class="text-sm text-ink-soft/60 italic">necunoscut</span>
                                @endif
                            </div>
                            <div>
                                <span class="block text-[11px] uppercase tracking-wide text-ink-soft/60 mb-0.5">Valoare totală</span>
                                @if ($item->stockValue() !== null)
                                    <span class="text-sm font-medium text-ink">{{ number_format($item->stockValue(), 2, ',', '.') }} lei</span>
                                @else
                                    <span class="text-sm text-ink-soft/40">—</span>
                                @endif
                            </div>
                        </div>

                        @php $movementCols = 'grid-cols-[10rem_10rem_10rem_1fr]'; @endphp
                        <div class="border-t border-border pt-3">
                            <div class="hidden sm:grid {{ $movementCols }} gap-3 text-[11px] font-semibold uppercase tracking-wide text-ink-soft/60 pb-2">
                                <span>Istoric mișcări</span>
                                <span class="text-right">Cantitate</span>
                                <span class="text-right">Cost/unitate</span>
                                <span class="text-right">Dată</span>
                            </div>

                            @php $movements = $item->movements()->latestFirst()->limit(20)->get(); @endphp
                            @if ($movements->isEmpty())
                                <p class="text-sm text-ink-soft">Nicio mișcare înregistrată încă.</p>
                            @else
                                <div class="space-y-0.5 max-h-64 overflow-y-auto">
                                    @foreach ($movements as $m)
                                        <div class="grid {{ $movementCols }} gap-3 items-center text-sm py-1.5 border-b border-border/60 last:border-0">
                                            <span class="inline-flex items-center gap-1.5 min-w-0">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ match($m->type) {
                                                    'in' => 'bg-primary',
                                                    'out' => 'bg-danger',
                                                    'initial' => 'bg-info',
                                                    default => 'bg-warning',
                                                } }}"></span>
                                                <span class="truncate">{{ $m->typeLabel() }}</span>
                                            </span>

                                            <span class="text-right text-ink-soft">
                                                @if ((float) $m->qty === 0.0)
                                                    —
                                                @else
                                                    @php $isNegativeMovement = $m->type === 'out' || (float) $m->qty < 0; @endphp
                                                    {{ $isNegativeMovement ? '−' : '+' }}{{ rtrim(rtrim(number_format(abs((float) $m->qty), 3, ',', '.'), '0'), ',') }} {{ $item->unit }}
                                                @endif
                                            </span>

                                            <span class="text-right text-ink-soft">
                                                @if ($m->unit_cost !== null)
                                                    {{ number_format((float) $m->unit_cost, 4, ',', '.') }} lei/{{ $item->unit }}
                                                @else
                                                    —
                                                @endif
                                            </span>

                                            <span class="text-right text-ink-soft/60 text-xs">{{ $m->created_at->format('d.m.Y H:i') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="px-5 py-10 text-center text-ink-soft">
                @if ($hasFilters)
                    Niciun produs de stoc care să corespundă filtrelor.
                @else
                    Niciun produs de stoc încă. Apasă „Produs de stoc nou" ca să adaugi primul.
                @endif
            </div>
        @endforelse

        @if ($items->total() > 0)
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 bg-bg border-t border-border text-sm">
                <span class="text-ink-soft">
                    Valoare totală stoc{{ $hasFilters ? ' (filtrat)' : '' }}:
                    <span class="font-semibold text-ink">{{ number_format($totalStockValue, 2, ',', '.') }} lei</span>
                </span>
                @if ($unknownCostCount > 0)
                    <span class="text-xs text-ink-soft/70">
                        + {{ $unknownCostCount }} {{ $unknownCostCount === 1 ? 'produs cu cost necunoscut, exclus din total' : 'produse cu cost necunoscut, excluse din total' }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    <div class="mt-4">
        {{ $items->onEachSide(1)->links('pagination.dxa') }}
    </div>

    {{-- Modal: creare/editare produs de stoc --}}
    <div x-show="$wire.modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" wire:click="closeModal"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-popup w-full p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-base font-semibold text-ink">{{ $editingId ? 'Editează produsul de stoc' : 'Produs de stoc nou' }}</h3>

            <div class="mt-4 grid grid-cols-[1fr_9rem] gap-3">
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">Denumire</label>
                    <input type="text" wire:model="name" placeholder="ex. Vodcă"
                           class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                    @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">Unitate</label>
                    <x-select wire:model="unit" live :options="$units" />
                    @error('unit') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($unit !== 'buc')
                <div class="mt-4">
                    <label class="block text-sm font-medium text-ink mb-1">Ambalaj de referință <span class="text-ink-soft/60 font-normal">(opțional — doar afișare, nu schimbă unitatea de bază)</span></label>
                    <div class="grid grid-cols-[1fr_7rem] gap-2">
                        <input type="text" wire:model="package_label" placeholder="ex. sticlă 700ml"
                               class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                        <input type="number" step="0.001" min="0" wire:model.live="package_qty" placeholder="{{ $units[$unit] ?? $unit }}"
                               class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                    </div>
                    @error('package_label') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    @error('package_qty') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-ink-soft">
                        Ex.: denumire "sticlă 700ml" + cantitate 700 — stocul se va afișa și ca "≈ N sticle de 700ml", pe lângă {{ $units[$unit] ?? $unit }} exacți. Nu urmărește sticle individuale (deschise/pline).
                    </p>
                </div>
            @endif

            <div class="mt-4">
                <label class="block text-sm font-medium text-ink mb-1">Prag alertă stoc minim <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                <input type="number" step="0.001" min="0" wire:model="min_stock" placeholder="ex. 5"
                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('min_stock') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                @if ($unit !== 'buc')
                    <x-qty-helper wire:key="qty-helper-min-{{ $formNonce }}" path="min_stock" :unit="$units[$unit] ?? $unit" :default-size="$package_qty !== '' ? $package_qty : null" />
                @endif
            </div>

            @unless ($editingId)
                <label class="mt-4 flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model.live="hasInitialStock"
                           class="w-4 h-4 rounded border-border accent-primary cursor-pointer">
                    <span class="text-sm text-ink">Am deja stoc din acest produs</span>
                </label>

                @if ($hasInitialStock)
                    <div class="mt-3 pl-6">
                        {{-- Toggle: cost unitar introdus direct, sau pret total de pe bon/factura --}}
                        <div class="flex items-center gap-4 text-sm mb-2">
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" wire:model.live="initial_cost_mode" value="unit" class="w-3.5 h-3.5 accent-primary cursor-pointer">
                                <span class="text-ink-soft">Am costul pe unitate</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" wire:model.live="initial_cost_mode" value="total" class="w-3.5 h-3.5 accent-primary cursor-pointer">
                                <span class="text-ink-soft">Am prețul total plătit</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3 items-start">
                            <div>
                                <label class="block text-sm font-medium text-ink mb-1">Cantitate <span class="text-ink-soft/60 font-normal">({{ $units[$unit] ?? $unit }})</span></label>
                                <input type="number" step="0.001" min="0" wire:model.live="initial_qty"
                                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                                @error('initial_qty') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                                @if ($unit !== 'buc')
                                    <x-qty-helper wire:key="qty-helper-initial-{{ $formNonce }}" path="initial_qty" :unit="$units[$unit] ?? $unit" :default-size="$package_qty !== '' ? $package_qty : null" />
                                @endif
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-ink mb-1">
                                    {{ $initial_cost_mode === 'total' ? 'Preț plătit' : 'Cost unitar' }}
                                    <span class="text-ink-soft/60 font-normal">(opțional)</span>
                                </label>
                                <input type="number" step="0.0001" min="0" wire:model.live="initial_cost" placeholder="lei"
                                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                                @error('initial_cost') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                                @if ($initial_cost_mode === 'unit' && $unit !== 'buc')
                                    <x-cost-helper wire:key="cost-helper-initial-{{ $formNonce }}" path="initial_cost" :unit="$units[$unit] ?? $unit" :default-qty="$package_qty !== '' ? $package_qty : null" />
                                @endif
                            </div>
                        </div>

                        @if ($initial_cost_mode === 'total' && $initial_qty !== '' && (float) $initial_qty > 0 && $initial_cost !== '')
                            <p class="mt-1.5 text-xs text-ink-soft">
                                = <span class="font-medium text-ink">{{ number_format(((float) $initial_cost) / ((float) $initial_qty), 4, ',', '.') }} lei/{{ $unit }}</span> cost unitar (calculat automat)
                            </p>
                        @endif

                        <p class="mt-2 text-xs text-ink-soft">
                            Dacă nu știi costul acum, îl poți completa oricând mai târziu din acțiunea „Corectează cost".
                        </p>
                    </div>
                @endif
            @endunless

            <label class="mt-4 flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="is_active"
                       class="w-4 h-4 rounded border-border accent-primary cursor-pointer">
                <span class="text-sm text-ink">Activ</span>
            </label>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" wire:click="closeModal" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="primary" wire:click="save">Salvează</x-btn>
            </div>
        </div>
    </div>

    {{-- Modal: corectare cantitate (ex. gresit stocul initial la creare) --}}
    <div x-show="$wire.qtyModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" wire:click="closeQtyModal"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Corectează stoc — {{ $qtyEditingName }}</h3>
            <p class="mt-1 text-sm text-ink-soft">Suprascrie cantitatea curentă din stoc (ex. ai greșit stocul inițial la creare, sau un inventar fizic arată altă cantitate). Nu afectează costul mediu.</p>

            <div class="mt-4">
                <label class="block text-sm font-medium text-ink mb-1">Cantitate corectă <span class="text-ink-soft/60 font-normal">({{ $units[$qtyEditingUnit] ?? $qtyEditingUnit }})</span></label>
                <input type="number" step="0.001" min="0" wire:model="new_qty"
                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('new_qty') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                @if ($qtyEditingUnit !== 'buc')
                    <x-qty-helper wire:key="qty-helper-correct-{{ $formNonce }}" path="new_qty" :unit="$units[$qtyEditingUnit] ?? $qtyEditingUnit" :default-size="$qtyEditingPackageQty !== '' ? $qtyEditingPackageQty : null" />
                @endif
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-ink mb-1">Motiv (pentru audit)</label>
                <input type="text" wire:model="qty_reason" placeholder="ex. Am greșit cantitatea la stocul inițial"
                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('qty_reason') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" wire:click="closeQtyModal" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="primary" wire:click="saveQtyAdjustment">Salvează</x-btn>
            </div>
        </div>
    </div>

    {{-- Modal: corectare cost --}}
    <div x-show="$wire.costModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" wire:click="closeCostModal"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Corectează cost — {{ $costEditingName }}</h3>
            <p class="mt-1 text-sm text-ink-soft">Suprascrie costul mediu curent. Nu afectează cantitatea din stoc. Folosește pentru produse fără cost inițial sau alinieri ulterioare la o factură.</p>

            <div class="mt-4 flex items-center gap-4 text-sm">
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" wire:model.live="new_cost_mode" value="unit" class="w-3.5 h-3.5 accent-primary cursor-pointer">
                    <span class="text-ink-soft">Cost pe unitate</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" wire:model.live="new_cost_mode" value="total" class="w-3.5 h-3.5 accent-primary cursor-pointer">
                    <span class="text-ink-soft">Valoare totală stoc curent</span>
                </label>
            </div>

            @if ($new_cost_mode === 'total')
                <div class="mt-3">
                    <label class="block text-sm font-medium text-ink mb-1">Valoare totală (lei)</label>
                    <input type="number" step="0.01" min="0" wire:model.live="new_cost_total"
                           class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                    @error('new_cost_total') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-xs text-ink-soft">
                        Stoc curent: <span class="font-medium text-ink">{{ rtrim(rtrim(number_format($costEditingCurrentQty, 3, ',', '.'), '0'), ',') }}</span> unități.
                        @if ($costEditingCurrentQty > 0 && $new_cost_total !== '')
                            = <span class="font-medium text-ink">{{ number_format(((float) $new_cost_total) / $costEditingCurrentQty, 4, ',', '.') }} lei/unitate</span> (calculat automat)
                        @elseif ($costEditingCurrentQty <= 0)
                            <span class="text-danger">Stocul e 0 — nu se poate folosi valoarea totală, folosește costul pe unitate.</span>
                        @endif
                    </p>
                </div>
            @else
                <div class="mt-3">
                    <label class="block text-sm font-medium text-ink mb-1">Cost unitar nou (lei)</label>
                    <input type="number" step="0.0001" min="0" wire:model="new_cost"
                           class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                    @error('new_cost') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    @if ($costEditingUnit !== 'buc')
                        <x-cost-helper wire:key="cost-helper-correct-{{ $formNonce }}" path="new_cost" :unit="$units[$costEditingUnit] ?? $costEditingUnit" :default-qty="$costEditingPackageQty !== '' ? $costEditingPackageQty : null" />
                    @endif
                </div>
            @endif

            <div class="mt-4">
                <label class="block text-sm font-medium text-ink mb-1">Motiv (pentru audit)</label>
                <input type="text" wire:model="cost_reason" placeholder="ex. Aliniere la factura din 12.10"
                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('cost_reason') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" wire:click="closeCostModal" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="primary" wire:click="saveCostAdjustment">Salvează</x-btn>
            </div>
        </div>
    </div>

    {{-- Modal de confirmare generic (stergere / dezactivare) --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink" x-text="confirmTitle"></h3>
            <p class="mt-2 text-sm text-ink-soft" x-text="confirmMessage"></p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="confirmOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <button type="button" @click="runConfirm()" x-text="confirmActionLabel"
                        :class="confirmVariant === 'danger' ? 'bg-danger hover:bg-danger/90' : 'bg-info hover:bg-info/90'"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-white"></button>
            </div>
        </div>
    </div>
</div>