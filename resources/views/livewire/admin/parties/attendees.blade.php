@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $badge = [
        \App\Services\PartyAttendees::ENTERED => ['A intrat', 'bg-success-soft text-success'],
        \App\Services\PartyAttendees::WAITING => ['Cu bilet, neintrat', 'bg-warning/10 text-warning'],
        \App\Services\PartyAttendees::PHONE => ['Doar telefon', 'bg-bg text-ink-soft'],
    ];
@endphp
<div class="max-w-3xl">
    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="{{ route('admin.parties.show', $party) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Înapoi la petrecere
        </a>
    </div>

    <div class="mb-4">
        <h2 class="text-xl font-semibold text-ink">Participanți · {{ $party->name }}</h2>
        <p class="mt-1 text-sm text-ink-soft">Persoanele identificabile (cont sau telefon) cu bilet sau intrare la această petrecere. Biletele și intrările anulate nu se numără.</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4" data-attendees-summary>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Intrări</div>
            <div class="text-2xl font-semibold text-ink" data-sum="entries">{{ $summary->entries }}</div>
            <div class="mt-0.5 text-[11px] text-ink-soft">{{ $summary->identified_entries }} identificate · {{ $summary->anonymous_entries }} anonime</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Persoane intrate</div>
            <div class="text-2xl font-semibold text-ink" data-sum="people_entered">{{ $summary->people_entered }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Cu bilet, neintrați</div>
            <div class="text-2xl font-semibold text-ink" data-sum="people_waiting">{{ $summary->people_waiting }}</div>
            <div class="mt-0.5 text-[11px] text-ink-soft">{{ $summary->tickets_valid }} bilete valabile</div>
        </div>
        <div class="{{ $card }}">
            <div class="text-[11px] text-ink-soft">Bilete fără deținător</div>
            <div class="text-2xl font-semibold text-ink" data-sum="unnamed_tickets">{{ $summary->unnamed_tickets }}</div>
            <div class="mt-0.5 text-[11px] text-ink-soft">fără nume sau telefon</div>
        </div>
    </div>

    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Caută după nume sau telefon…"
               class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary sm:w-64">
        <x-select wire:model="status" live class="sm:w-56" :options="$statusOptions" />
        @if ($hasFilters)
            <button type="button" wire:click="clearFilters" class="text-sm text-ink-soft hover:text-ink px-2 py-2 self-start">Resetează</button>
        @endif
    </div>

    <div class="space-y-2">
        @forelse ($people as $r)
            @php [$label, $cls] = $badge[$r->status]; @endphp
            <div wire:key="att-{{ $r->participant_id ?? 'tel-'.$r->phone }}" data-attendee class="rounded-2xl border border-border bg-surface px-4 py-3 sm:flex sm:items-center sm:justify-between sm:gap-4">
                <div class="min-w-0">
                    @if ($r->participant_id)
                        <a href="{{ route('admin.participants.show', $r->participant_id) }}" wire:navigate class="block truncate font-medium text-ink hover:text-primary">{{ $r->name }}</a>
                    @else
                        <span class="block truncate font-medium text-ink-soft">{{ $r->name }}</span>
                    @endif
                    <span class="block text-xs text-ink-soft">
                        {{ $r->phone ?? 'fără telefon' }}
                        <span class="text-ink-soft/40">·</span> {{ $r->has_account ? 'cu cont' : 'fără cont' }}
                    </span>
                </div>
                <div class="mt-2 sm:mt-0 shrink-0 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft">
                    <span data-tickets>{{ $r->tickets }} {{ $r->tickets === 1 ? 'bilet' : 'bilete' }}@if ($r->tickets > 0) ({{ $r->valid }} valabile, {{ $r->used }} folosite)@endif</span>
                    <span data-entries>{{ $r->entries }} {{ $r->entries === 1 ? 'intrare' : 'intrări' }}</span>
                    <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $cls }}">{{ $label }}</span>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface px-5 py-8 text-center text-ink-soft">
                {{ $hasFilters ? 'Nicio persoană pentru filtrele alese.' : 'Nicio persoană identificată încă la această petrecere.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $people->onEachSide(1)->links('pagination.dxa') }}
    </div>
</div>
