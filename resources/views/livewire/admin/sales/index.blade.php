<div
    x-data="{
        cancelOpen: false,
        cancelId: null,
        cancelInfo: '',
        cancelReason: '',
        askCancel(id, info) { this.cancelId = id; this.cancelInfo = info; this.cancelReason = ''; this.cancelOpen = true; },
        tryCancel() {
            if (this.cancelReason.trim().length < 3) { return; }
            this.$wire.call('cancel', this.cancelId, this.cancelReason);
            this.cancelOpen = false;
        },
    }"
>
    @php
        $money = fn ($n) => number_format((float) $n, 2, ',', '.');
        $methodLabels = \App\Models\SalePayment::METHODS;
        $hasFilters = $group !== 'all' || $state !== 'all' || $method !== 'all' || $product !== 'all' || $dateFrom !== '' || $dateTo !== '';

        $groupOptions = ['all' => 'Toate sesiunile', 'none' => 'Fără sesiune (vânzări simple)'] + $groups->mapWithKeys(fn ($g) => [$g->id => $g->label()])->all();
        $productOptions = ['all' => 'Toate produsele'] + $menuItems->mapWithKeys(fn ($m) => [$m->id => $m->name])->all();
        $methodOptions = ['all' => 'Toate plățile'] + $methodLabels;
        $stateOptions = ['all' => 'Toate stările', 'completed' => 'Finalizate', 'cancelled' => 'Anulate'];
        $partyOptions = ['' => 'Fără petrecere'] + $parties->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->starts_at?->format('d.m.Y')])->all();
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-4">
        <div class="min-w-0 max-w-2xl">
            <h2 class="text-lg font-semibold text-ink">Bar — Vânzări</h2>
            <p class="mt-1 text-sm text-ink-soft">Toate vânzările din bar. O sesiune de vânzări (ex. o petrecere) e comună tuturor barmanilor; vânzările simple, de exemplu la un curs, nu aparțin niciunei sesiuni. Stocul se actualizează la finalizarea raportării.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <x-btn variant="neutral" wire:click="openNewGroup">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                Sesiune nouă
            </x-btn>
            <x-btn variant="primary" :href="route('admin.sales.create', $group !== 'all' ? ['group' => $group] : [])" wire:navigate>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Vânzare nouă
            </x-btn>
        </div>
    </div>

    {{-- Filtre --}}
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <x-select wire:model="group" live class="sm:w-64" :options="$groupOptions" />
        <x-select wire:model="product" live class="sm:w-48" :options="$productOptions" />
        <x-select wire:model="method" live class="sm:w-40" :options="$methodOptions" />
        <x-select wire:model="state" live class="sm:w-40" :options="$stateOptions" />
        <div class="flex items-center gap-2">
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            <span class="text-ink-soft/60 text-sm">–</span>
            <input type="date" wire:model.live="dateTo" class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
        </div>
        @if ($hasFilters)
            <button type="button" wire:click="clearFilters" class="text-sm text-ink-soft hover:text-ink px-2 py-2 self-start">Resetează</button>
        @endif
    </div>

    <x-flash class="mb-4" />

    {{-- Rezumat pe ce e filtrat --}}
    <div class="mb-4 rounded-xl border border-border bg-surface px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-ink-soft">
        <span><span class="font-semibold text-ink">{{ $summary['count'] }}</span> vânzări finalizate</span>
        <span class="font-semibold text-ink">{{ $money($summary['revenue']) }} lei</span>
        @foreach ($summary['payments'] as $m => $p)
            <span class="inline-flex items-center rounded-full bg-bg px-2 py-0.5 text-xs">
                {{ $methodLabels[$m] ?? $m }}:
                @if ($m === 'token') {{ $p['tokens'] }} tk · @endif
                {{ $money($p['amount']) }} lei
            </span>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($sales as $sale)
            <div wire:key="sale-{{ $sale->id }}"
                 class="rounded-2xl border border-border bg-surface p-3 md:p-4 {{ $sale->isCancelled() ? 'opacity-60' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-sm font-semibold text-ink">{{ $sale->sold_at->format('d.m.Y H:i') }}</span>
                            <span class="inline-flex items-center rounded-full border border-border text-ink-soft text-xs px-2 py-0.5">{{ $sale->group?->label() ?? 'Fără sesiune' }}</span>
                            <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">{{ \App\Models\Sale::SOURCES[$sale->source] ?? $sale->source }}</span>
                            @if ($sale->isCancelled())
                                <span class="inline-flex items-center rounded-full bg-danger/10 text-danger text-xs font-medium px-2 py-0.5">Anulată</span>
                            @endif
                            @if ($sale->isReported())
                                <span class="inline-flex items-center rounded-full bg-info-soft text-info text-xs font-medium px-2 py-0.5">Raportată</span>
                            @endif
                        </div>

                        <p class="mt-1.5 text-sm text-ink">
                            @foreach ($sale->lines as $l)
                                <span class="whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $l->qty, 3, ',', '.'), '0'), ',') }} × {{ $l->menuItem?->name ?? '—' }}</span>@if (! $loop->last)<span class="text-ink-soft/40"> · </span>@endif
                            @endforeach
                        </p>

                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            @foreach ($sale->payments as $p)
                                <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">
                                    {{ $methodLabels[$p->method] ?? $p->method }}:
                                    @if ($p->method === 'token') {{ $p->tokens }} tk · @endif
                                    {{ $money($p->amount) }} lei
                                </span>
                            @endforeach
                        </div>

                        @if ($sale->isCancelled())
                            <p class="mt-1.5 text-xs text-danger">Motiv anulare: {{ $sale->cancel_reason }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-base font-semibold text-ink whitespace-nowrap">{{ $money($sale->total) }} lei</span>
                        @if ($sale->canBeCancelled())
                            @php $cancelInfo = $sale->sold_at->format('d.m.Y H:i').' · '.$money($sale->total).' lei'; @endphp
                            <x-btn variant="danger" size="icon" outline tooltip="Anulează vânzarea"
                                   x-on:click="askCancel({{ $sale->id }}, '{{ $cancelInfo }}')">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.9" y1="4.9" x2="19.1" y2="19.1"/></svg>
                            </x-btn>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
                @if ($hasFilters)
                    Nicio vânzare care să corespundă filtrelor.
                @else
                    Nicio vânzare încă. Adaugă prima vânzare.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $sales->onEachSide(1)->links('pagination.dxa') }}
    </div>

    {{-- Popup: grup nou --}}
    <div x-show="$wire.newGroupOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" wire:click="closeNewGroup"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Sesiune de vânzări nouă</h3>
            <p class="mt-1 text-sm text-ink-soft">O singură sesiune deschisă per petrecere; dacă există deja, o selectăm.</p>

            <div class="mt-4">
                <label class="block text-sm font-medium text-ink mb-1.5">Petrecere</label>
                <x-select wire:model="newGroupParty" :options="$partyOptions" />
                @error('newGroupParty') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" wire:click="closeNewGroup" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="primary" wire:click="createGroup">Deschide sesiunea</x-btn>
            </div>
        </div>
    </div>

    {{-- Popup: anulare vânzare (motiv obligatoriu) --}}
    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="cancelOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Anulează vânzarea</h3>
            <p class="mt-1 text-sm text-ink-soft" x-text="cancelInfo"></p>
            <p class="mt-2 text-xs text-ink-soft/80">Vânzarea rămâne în istoric (marcată ca anulată), dar nu mai intră în totaluri și nici în raportare. Cash-ul și tokenii se returnează fizic.</p>

            <div class="mt-4">
                <label class="block text-sm font-medium text-ink mb-1.5">Motiv</label>
                <input type="text" x-model="cancelReason" maxlength="250" placeholder="ex. produs greșit"
                       @keydown.enter.prevent="tryCancel()"
                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="cancelOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Renunță</button>
                <x-btn variant="danger" x-on:click="tryCancel()">Anulează vânzarea</x-btn>
            </div>
        </div>
    </div>
</div>
