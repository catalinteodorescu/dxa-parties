<div>
    @php
        $cols = 'md:grid-cols-[150px_minmax(0,1fr)_minmax(0,2fr)]';
    @endphp

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">Jurnal activitate</h2>
        <p class="mt-1 text-sm text-ink-soft">Acțiunile importante din panoul de administrare, cele mai recente primele.</p>
    </div>

    {{-- Filtre: pe ecrane mici sunt într-un toggle (deschis automat dacă există filtre active), de la md în sus mereu vizibile. --}}
    <div x-data="{ filtersOpen: {{ $hasFilters ? 'true' : 'false' }} }" class="mb-4" data-log-filters>
        <button type="button" @click="filtersOpen = !filtersOpen"
                class="md:hidden inline-flex items-center gap-1.5 text-sm font-medium text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/></svg>
            Filtre
            @if ($hasFilters)
                <span class="inline-flex items-center rounded-full bg-primary-soft text-primary text-[10px] font-semibold px-1.5 py-0.5">active</span>
            @endif
        </button>

        <div class="hidden md:block mt-2 md:mt-0" :class="filtersOpen ? 'max-md:block' : ''">
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Caută în jurnal…"
                       class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary sm:w-56">
                <x-select wire:model="category" live class="sm:w-48" :options="$categoryOptions" />
                <x-select wire:model="actor" live class="sm:w-48" :options="$actorOptions" />
                <x-date-range from="dateFrom" to="dateTo" class="sm:w-60" placeholder="Interval de date" />
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters" class="text-sm text-ink-soft hover:text-ink px-2 py-2 self-start">Resetează</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Lista: antet de coloane slim (doar desktop) + fiecare eveniment = card propriu, spațiat --}}
    <div>

        <div class="hidden md:grid {{ $cols }} gap-4 px-5 py-2
                    text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
            <div>Când</div>
            <div>Cine</div>
            <div>Ce s-a întâmplat</div>
        </div>

        <div class="space-y-3">
            @forelse ($logs as $log)
                <div class="rounded-2xl border border-border bg-surface p-4 space-y-3
                            md:px-5 md:py-3 md:space-y-0 md:grid {{ $cols }} md:items-start md:gap-4">

                    {{-- Când --}}
                    <div class="flex items-center justify-between gap-3 md:block">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Când</span>
                        <span class="text-ink-soft whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i') }}</span>
                    </div>

                    {{-- Cine --}}
                    <div class="flex items-center justify-between gap-3 md:block">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Cine</span>
                        <span class="text-ink">{{ $log->actor_label ?? 'Sistem' }}</span>
                    </div>

                    {{-- Ce s-a întâmplat --}}
                    <div class="flex flex-col gap-0.5 md:block">
                        <span class="text-xs font-medium text-ink-soft md:hidden">Ce s-a întâmplat</span>
                        <span class="text-ink">{{ $log->description }}</span>
                        <span class="block mt-0.5 text-xs text-ink-soft/60">{{ $log->action }}</span>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-border bg-surface px-5 py-8 text-center text-ink-soft">
                    {{ $hasFilters ? 'Niciun eveniment pentru filtrele alese.' : 'Niciun eveniment înregistrat încă.' }}
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        {{ $logs->onEachSide(1)->links('pagination.dxa') }}
    </div>
</div>
