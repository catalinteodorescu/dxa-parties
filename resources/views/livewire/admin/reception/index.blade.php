<div
    x-data="{
        cancelOpen: false,
        cancelBatch: null,
        cancelInfo: '',
        cancelReason: '',
        askCancel(batch, info) { this.cancelBatch = batch; this.cancelInfo = info; this.cancelReason = ''; this.cancelOpen = true; },
        tryCancel() {
            if (this.cancelReason.trim().length < 3) { return; }
            this.$wire.call('cancel', this.cancelBatch, this.cancelReason);
            this.cancelOpen = false;
        },
    }"
>
    @php
        $money = fn ($n) => number_format((float) $n, 2, ',', '.');
        $hasFilters = $party !== '' || $state !== 'all' || $method !== 'all' || $dateFrom !== '' || $dateTo !== '';

        $partyOptions = ['' => 'Toate petrecerile'] + $parties->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->starts_at?->format('d.m.Y')])->all();
        $methodOptions = ['all' => 'Toate plățile'] + $methodLabels;
        $stateOptions = ['all' => 'Toate stările', 'active' => 'Active', 'cancelled' => 'Anulate'];
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-4">
        <div class="min-w-0 max-w-2xl">
            <h2 class="text-lg font-semibold text-ink">Recepție · Intrări</h2>
            <p class="mt-1 text-sm text-ink-soft">Toate intrările înregistrate la recepție. Un rând = un grup (1-50 persoane), înregistrat odată, cu plata lui.</p>
        </div>
        <x-btn variant="primary" :href="route('admin.reception.create')" wire:navigate class="shrink-0">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Intrare nouă
        </x-btn>
    </div>

    {{-- Filtre — pe ecrane mici (<md) sunt ascunse într-un toggle (deschis automat dacă există filtre active); de la md în sus sunt mereu vizibile, fără buton. --}}
    <div x-data="{ filtersOpen: {{ $hasFilters ? 'true' : 'false' }} }" class="mb-4">
        <button type="button" @click="filtersOpen = !filtersOpen"
                class="md:hidden inline-flex items-center gap-1.5 text-sm font-medium text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/></svg>
            Filtre
            @if ($hasFilters)
                <span class="inline-flex items-center rounded-full bg-primary-soft text-primary text-[10px] font-semibold px-1.5 py-0.5">active</span>
            @endif
            <svg class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div class="hidden md:block mt-2 md:mt-0" :class="filtersOpen ? 'max-md:block' : ''">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <x-select wire:model="party" live class="sm:w-64" :options="$partyOptions" />
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
        </div>
    </div>

    @if ($cancelMessage)
        <x-alert type="success" class="mb-4" dismiss-prop="cancelMessage" :dismiss-value="null">{{ $cancelMessage }}</x-alert>
    @endif
    @if ($cancelError)
        <x-alert type="error" class="mb-4" dismiss-prop="cancelError" :dismiss-value="null">{{ $cancelError }}</x-alert>
    @endif

    {{-- Rezumat pe ce e filtrat (doar intrari active; cele anulate nu conteaza) --}}
    <div class="mb-4 rounded-xl border border-border bg-surface px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-ink-soft">
        <span><span class="font-semibold text-ink">{{ $summary['entries'] }}</span> {{ $summary['entries'] === 1 ? 'persoană' : 'persoane' }}</span>
        <span>în <span class="font-semibold text-ink">{{ $summary['batches'] }}</span> {{ $summary['batches'] === 1 ? 'grup' : 'grupuri' }}</span>
        <span class="font-semibold text-ink">{{ $money($summary['revenue']) }} lei</span>
    </div>

    <div class="space-y-3">
        @forelse ($batches as $b)
            <div wire:key="batch-{{ $b->batch }}" class="rounded-2xl border border-border bg-surface p-3 md:p-4 {{ $b->cancelled ? 'opacity-60' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-sm font-semibold text-ink">{{ $b->at->format('d.m.Y H:i') }}</span>
                            <span class="inline-flex items-center rounded-full border border-border text-ink-soft text-xs px-2 py-0.5">{{ $b->party?->name ?? '—' }}</span>
                            <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">{{ $b->ticket }}</span>
                            @if ($b->grace)
                                <span class="inline-flex items-center rounded-full bg-info-soft text-info text-xs px-2 py-0.5">toleranță</span>
                            @endif
                            @if ($b->override_reason)
                                <span class="inline-flex items-center rounded-full bg-warning/10 text-warning text-xs px-2 py-0.5">preț suprascris</span>
                            @endif
                            @if ($b->cancelled)
                                <span class="inline-flex items-center rounded-full bg-danger/10 text-danger text-xs font-medium px-2 py-0.5">Anulată</span>
                            @endif
                        </div>

                        <p class="mt-1.5 text-sm text-ink">
                            {{ $b->count }} {{ $b->count === 1 ? 'persoană' : 'persoane' }}
                            @if ($b->by) <span class="text-ink-soft/40">·</span> <span class="text-ink-soft">{{ $b->by }}</span> @endif
                        </p>

                        @if ($b->participants)
                            <p class="mt-1 text-xs text-ink">{{ implode(', ', array_slice($b->participants, 0, 3)) }}@if (count($b->participants) > 3) +{{ count($b->participants) - 3 }} @endif</p>
                        @endif

                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            @foreach ($b->payments as $m => $amount)
                                <span class="inline-flex items-center rounded-full bg-bg text-ink-soft text-xs px-2 py-0.5">{{ $methodLabels[$m] ?? $m }}: {{ $money($amount) }} lei</span>
                            @endforeach
                        </div>

                        @if ($b->override_reason)
                            <p class="mt-1.5 text-xs text-ink-soft">Motiv suprascriere: {{ $b->override_reason }}</p>
                        @endif
                        @if ($b->cancelled)
                            <p class="mt-1.5 text-xs text-danger">Motiv anulare: {{ $b->cancel_reason }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-base font-semibold text-ink whitespace-nowrap">{{ $money($b->total) }} lei</span>
                        @if (! $b->cancelled)
                            @php $cancelInfo = $b->at->format('d.m.Y H:i').' · '.$b->count.' persoane · '.$money($b->total).' lei'; @endphp
                            <x-btn variant="danger" size="icon" outline tooltip="Anulează grupul"
                                   x-on:click="askCancel('{{ $b->batch }}', '{{ $cancelInfo }}')">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                            </x-btn>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
                @if ($hasFilters)
                    Nicio intrare care să corespundă filtrelor.
                @else
                    Nicio intrare încă. Adaugă prima intrare.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $paginator->onEachSide(1)->links('pagination.dxa') }}
    </div>

    {{-- Popup: anulare grup (motiv obligatoriu) --}}
    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="cancelOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Anulează grupul</h3>
            <p class="mt-1 text-sm text-ink-soft" x-text="cancelInfo"></p>
            <p class="mt-2 text-xs text-ink-soft/80">Grupul rămâne în istoric (marcat ca anulat), dar nu mai intră în statistici. Nu se șterge nimic.</p>

            <div class="mt-4">
                <label class="block text-sm font-medium text-ink mb-1.5">Motiv</label>
                <input type="text" x-model="cancelReason" maxlength="250" placeholder="ex. greșeală la înregistrare"
                       @keydown.enter.prevent="tryCancel()"
                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="cancelOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Renunță</button>
                <x-btn variant="danger" x-on:click="tryCancel()">Anulează grupul</x-btn>
            </div>
        </div>
    </div>
</div>
