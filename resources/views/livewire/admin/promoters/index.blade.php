@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $money = fn ($n) => number_format((float) $n, 2, ',', '.').' lei';
    $fmtInt = fn ($n) => number_format((float) $n, 0, ',', '.');
    $in = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
    $lbl = 'block text-sm font-medium text-ink';
    $err = 'mt-1.5 text-sm text-danger';
    $maxPart = max(1, (int) $ranking->max('participants'));
    $maxTickets = max(1, (int) $ranking->max('tickets'));
@endphp
<div class="max-w-4xl space-y-4">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-xl font-semibold text-ink">Promotori</h2>
            <p class="mt-1 text-sm text-ink-soft">Cine aduce lume la petreceri, cu codurile lui. Clasamentul se face din intrările valabile cu cod, la toate petrecerile.</p>
        </div>
        <x-btn variant="primary" wire:click="openForm(0)">Promotor nou</x-btn>
    </div>

    <x-flash />

    {{-- Clasament --}}
    <div class="{{ $card }}">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h3 class="text-sm font-semibold text-ink">Clasament promotori</h3>
                <p class="mt-1 text-xs text-ink-soft">După participanții identificați aduși. „Noi" = fără nicio intrare la o petrecere anterioară.</p>
            </div>
            <x-select wire:model="period" :live="true" class="w-64" :options="$periodOptions" />
        </div>

        @if ($stats->totals->tickets === 0)
            <p class="mt-3 text-sm text-ink-soft">Niciun cod folosit încă în perioada aleasă.</p>
        @else
            <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-2 text-sm">
                <div class="rounded-xl border border-border px-3.5 py-2.5"><div class="text-[11px] text-ink-soft">Bilete cu reducere</div><div class="text-lg font-semibold text-ink">{{ $fmtInt($stats->totals->tickets) }}</div><div class="text-[11px] text-ink-soft">la {{ $stats->parties }} {{ $stats->parties === 1 ? 'petrecere' : 'petreceri' }}</div></div>
                <div class="rounded-xl border border-border px-3.5 py-2.5"><div class="text-[11px] text-ink-soft">Participanți aduși</div><div class="text-lg font-semibold text-ink">{{ $fmtInt($stats->totals->participants) }}</div><div class="text-[11px] text-ink-soft">{{ $fmtInt($stats->totals->new) }} noi · {{ $fmtInt($stats->totals->returning) }} reveniți</div></div>
                <div class="rounded-xl border border-border px-3.5 py-2.5"><div class="text-[11px] text-ink-soft">Reducere acordată</div><div class="text-lg font-semibold text-ink">{{ $money($stats->totals->discount) }}</div></div>
                <div class="rounded-xl border border-border px-3.5 py-2.5"><div class="text-[11px] text-ink-soft">Încasat cu cod</div><div class="text-lg font-semibold text-ink">{{ $money($stats->totals->revenue) }}</div></div>
            </div>

            <div class="mt-4 space-y-2">
                @foreach ($ranking as $r)
                    <div wire:key="rk-{{ $r->promoter_id ?? 'none' }}" class="rounded-xl border border-border px-3.5 py-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex items-baseline gap-2">
                                <span class="w-5 shrink-0 text-xs text-ink-soft">{{ $loop->iteration }}.</span>
                                <span class="truncate text-sm font-medium text-ink">{{ $r->promoter }}</span>
                                <span class="text-xs text-ink-soft">{{ $r->parties }} {{ $r->parties === 1 ? 'petrecere' : 'petreceri' }}</span>
                            </div>
                            <div class="shrink-0 text-right text-sm font-semibold text-ink">{{ $fmtInt($r->participants) }} {{ $r->participants === 1 ? 'participant' : 'participanți' }}</div>
                        </div>
                        <div class="mt-1.5 ml-7 h-1.5 rounded-full bg-bg overflow-hidden"><div class="h-full rounded-full bg-primary" style="width: {{ round($r->participants / $maxPart * 100) }}%"></div></div>
                        <div class="mt-1 pl-7 text-xs text-ink-soft">
                            {{ $fmtInt($r->new) }} noi · {{ $fmtInt($r->returning) }} reveniți
                            · {{ $fmtInt($r->tickets) }} {{ $r->tickets === 1 ? 'bilet' : 'bilete' }}@if ($r->anonymous > 0) ({{ $fmtInt($r->anonymous) }} fără participant)@endif
                            · reducere {{ $money($r->discount) }} · încasat {{ $money($r->revenue) }}
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Cele mai folosite coduri --}}
            <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-ink-soft">Cele mai folosite coduri</h4>
            <div class="mt-2 space-y-1.5">
                @foreach ($stats->codes->take(10) as $c)
                    <div wire:key="tc-{{ $c->code }}-{{ $c->party_id }}" class="flex items-center justify-between gap-3 rounded-xl border border-border px-3.5 py-2 text-sm">
                        <div class="min-w-0">
                            <span class="font-mono font-medium text-ink">{{ $c->code }}</span>
                            @if ($c->promoter)<span class="text-xs text-ink-soft"> · {{ $c->promoter }}</span>@endif
                            @if ($c->party)<div class="text-xs text-ink-soft truncate">{{ $c->party }}</div>@endif
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-semibold text-ink">{{ $fmtInt($c->tickets) }} {{ $c->tickets === 1 ? 'bilet' : 'bilete' }}</div>
                            <div class="text-xs text-ink-soft">{{ $fmtInt($c->participants) }} participanți · reducere {{ $money($c->discount) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Evidență --}}
    <div class="{{ $card }}">
        <h3 class="text-sm font-semibold text-ink">Evidență promotori</h3>
        @forelse ($promoters as $row)
            @php $p = $row->model; @endphp
            <div wire:key="pr-{{ $p->id }}" class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-border px-4 py-2.5 {{ $p->is_active ? '' : 'opacity-60' }}">
                <div class="min-w-0">
                    <span class="text-sm font-medium text-ink">{{ $p->name }}</span>
                    @unless ($p->is_active)<span class="ml-1 inline-flex items-center rounded-full bg-bg text-ink-soft text-[11px] px-2 py-0.5">inactiv</span>@endunless
                    <div class="text-xs text-ink-soft">
                        {{ $row->codes }} {{ $row->codes === 1 ? 'cod' : 'coduri' }}
                        @if ($p->phone) · {{ $p->phone }} @endif
                        @if ($p->note) · {{ $p->note }} @endif
                        @if ($row->stat) · {{ $fmtInt($row->stat->participants) }} participanți în perioada aleasă @endif
                    </div>
                </div>
                <div class="shrink-0 flex items-center gap-1.5">
                    <button type="button" wire:click="openForm({{ $p->id }})" class="text-xs font-medium text-primary hover:underline px-2 py-1">Editează</button>
                    <button type="button" wire:click="toggleActive({{ $p->id }})" class="text-xs font-medium text-ink-soft hover:text-ink px-2 py-1">{{ $p->is_active ? 'Dezactivează' : 'Activează' }}</button>
                    @if ($row->codes === 0)
                        <button type="button" wire:click="askDelete({{ $p->id }})" class="text-xs font-medium text-danger hover:underline px-2 py-1">Șterge</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-ink-soft">Niciun promotor încă. Adaugă unul de aici sau direct din formularul petrecerii (butonul + de lângă „Promotor").</p>
        @endforelse
    </div>

    {{-- Popup centrat: adăugare / editare --}}
    @if ($editing !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="absolute inset-0 bg-ink/40" wire:click="closeForm"></div>
            <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6" role="dialog" aria-modal="true">
                <h3 class="text-base font-semibold text-ink">{{ $editing ? 'Editează promotorul' : 'Promotor nou' }}</h3>
                <form wire:submit="save" class="mt-4 space-y-3">
                    <div>
                        <label class="{{ $lbl }} mb-1.5" for="pr-name">Nume</label>
                        <input type="text" id="pr-name" wire:model="name" autofocus autocomplete="off" class="{{ $in }}">
                        @error('name') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }} mb-1.5" for="pr-phone">Telefon <span class="font-normal text-ink-soft">(opțional)</span></label>
                        <input type="text" id="pr-phone" wire:model="phone" autocomplete="off" class="{{ $in }}">
                        @error('phone') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }} mb-1.5" for="pr-note">Notă <span class="font-normal text-ink-soft">(opțional)</span></label>
                        <input type="text" id="pr-note" wire:model="note" autocomplete="off" class="{{ $in }}">
                        @error('note') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button type="button" wire:click="closeForm" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                        <x-btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">Salvează</x-btn>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Popup centrat: confirmare ștergere --}}
    @if ($deleting !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.cancelDelete()">
            <div class="absolute inset-0 bg-ink/40" wire:click="cancelDelete"></div>
            <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6" role="dialog" aria-modal="true">
                <h3 class="text-base font-semibold text-ink">Ștergi promotorul?</h3>
                <p class="mt-2 text-sm text-ink-soft">Promotorul nu are coduri la nicio petrecere. Ștergerea nu se poate anula.</p>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" wire:click="cancelDelete" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                    <x-btn variant="danger" wire:click="delete">Șterge</x-btn>
                </div>
            </div>
        </div>
    @endif
</div>
