{{-- DXA: adaugat (runda 66). Pagina „Bilete”: lista biletelor cumpărate din aplicație, cu căutare și filtre. Variabile: $tickets (paginat), $partyOptions, $statusOptions, $paymentOptions, $hasFilters. --}}
@php
    $lei = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $statusClass = [
        \App\Models\Ticket::VALID => 'bg-success-soft text-success',
        \App\Models\Ticket::USED => 'bg-info-soft text-info',
        \App\Models\Ticket::PENDING => 'bg-bg text-warning',
        \App\Models\Ticket::VOID => 'bg-bg text-ink-soft line-through',
    ];
@endphp
<div class="max-w-4xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">Bilete</h2>
        <p class="mt-1 text-sm text-ink-soft">Biletele cumpărate din aplicație, peste toate petrecerile. Caută după telefon, nume, cod de bilet sau <span class="font-mono">#</span>număr de comandă.</p>
    </div>

    <div x-data="{ filtersOpen: {{ $hasFilters ? 'true' : 'false' }} }" class="mb-4" data-ticket-filters>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Telefon, nume, cod bilet sau #comandă…" data-ticket-search
                   class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary sm:flex-1">
            <button type="button" @click="filtersOpen = !filtersOpen"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-soft hover:text-ink self-start sm:self-auto">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/></svg>
                Filtre
                @if ($hasFilters)
                    <span class="inline-flex items-center rounded-full bg-primary-soft text-primary text-[10px] font-semibold px-1.5 py-0.5">active</span>
                @endif
            </button>
        </div>
        <div x-show="filtersOpen" x-cloak class="mt-2 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <x-select wire:model="filterParty" live class="sm:w-56" :options="$partyOptions" />
            <x-select wire:model="filterStatus" live class="sm:w-52" :options="$statusOptions" />
            <x-select wire:model="filterPayment" live class="sm:w-40" :options="$paymentOptions" />
            <x-date-range from="dateFrom" to="dateTo" class="sm:w-60" placeholder="Interval de date" />
            @if ($hasFilters)
                <button type="button" wire:click="clearFilters" class="text-sm text-ink-soft hover:text-ink px-2 py-2 self-start" data-ticket-clear>Resetează</button>
            @endif
        </div>
    </div>

    @if ($tickets->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft" data-tickets-empty>
            {{ $hasFilters ? 'Niciun bilet pentru căutarea sau filtrele alese.' : 'Nu s-a cumpărat încă niciun bilet din aplicație.' }}
        </div>
    @else
        <div class="space-y-2">
            @foreach ($tickets as $t)
                @php
                    $who = $t->holder ?? $t->owner;
                    $whoName = $who && ! $who->isAnonymized() ? $who->name : ($t->holder_phone ?: 'Bilet fără nume');
                @endphp
                <a wire:key="tk-{{ $t->id }}" href="{{ route('admin.tickets.show', $t->id) }}" wire:navigate data-ticket-row
                   class="block rounded-2xl border border-border bg-surface px-4 py-3 hover:border-primary/40 hover:bg-primary-soft/40 transition-colors">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-semibold text-ink truncate">{{ $t->ticket_type }}</span>
                                <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 {{ $statusClass[$t->status] ?? 'bg-bg text-ink-soft' }}">{{ \App\Services\AdminTickets::STATUS_LABELS[$t->status] ?? $t->status }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-ink-soft truncate">{{ $t->party?->name ?? 'Petrecere ștearsă' }} · {{ $whoName }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-sm font-semibold text-ink">{{ (float) $t->price > 0 ? $lei($t->price) : 'Gratuit' }}</div>
                            <div class="text-xs text-ink-soft">{{ \App\Services\AdminTickets::paymentLabel($t) }}</div>
                        </div>
                    </div>
                    <div class="mt-0.5 text-xs text-ink-soft/70">Cod <span class="font-mono" data-ticket-row-code>{{ $t->shortCode() }}</span> · Comanda #{{ $t->order_id }} · {{ $t->created_at->format('d.m.Y H:i') }}</div>
                </a>
            @endforeach
        </div>
        <div class="mt-3">
            {{ $tickets->onEachSide(1)->links('pagination.dxa') }}
        </div>
    @endif
</div>
