{{--
    DXA: adaugat (runda 15). Biletele online scanate la Recepție: deținător, tip, de încasat și „EXPIRAT" (cu diferența).
    Folosit în formularele „Intrare" (PWA și admin). Datele vin din $this->ticketRows() (trait HandlesEntryForm).
--}}
@php $rows = $this->ticketRows(); $fmtLei = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
@if ($rows->isNotEmpty())
    <div class="space-y-2" data-entry-tickets>
        <div class="text-sm font-medium text-ink">Bilete online ({{ $rows->count() }})</div>
        @foreach ($rows as $r)
            @php $t = $r['ticket']; @endphp
            <div wire:key="entry-ticket-{{ $t->id }}" class="flex items-start justify-between gap-3 rounded-xl border {{ $r['expired'] ? 'border-warning' : 'border-border' }} bg-surface px-3.5 py-2.5">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-ink truncate">{{ $r['holder'] }}</div>
                    <div class="text-xs text-ink-soft">{{ $t->ticket_type }} · {{ (float) $t->price > 0 ? $fmtLei($t->price).' lei' : 'gratuit' }}@if ($t->valid_until) · până {{ $t->valid_until->format('d.m H:i') }} @endif</div>
                    @if ($r['expired'])
                        <div class="mt-1 text-xs font-semibold text-warning">EXPIRAT · prețul curent {{ $r['current'] !== null ? $fmtLei($r['current']).' lei' : '—' }}: de încasat {{ $fmtLei($r['due']) }} lei</div>
                    @else
                        <div class="mt-1 text-xs {{ $r['due'] > 0 ? 'text-ink' : 'text-success' }}">{{ $r['due'] > 0 ? 'De încasat '.$fmtLei($r['due']).' lei' : 'Intră gratuit' }}</div>
                    @endif
                </div>
                <button type="button" wire:click="removeTicket({{ $t->id }})" class="shrink-0 h-7 w-7 inline-flex items-center justify-center rounded-lg text-ink-soft hover:bg-bg" aria-label="Scoate biletul">×</button>
            </div>
        @endforeach
        @if ((int) $count > $rows->count())
            <p class="text-xs text-ink-soft">Ceilalți {{ (int) $count - $rows->count() }} intră la prețul de acum.</p>
        @endif
    </div>
@endif
