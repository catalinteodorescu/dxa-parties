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
        $hasFilters = $search !== '' || $state !== 'all';

        // Cantitati fara zerouri de prisos (12,500 -> 12,5 ; 3,000 -> 3).
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-ink">Bar — Necesare</h2>
            <p class="mt-1 text-sm text-ink-soft">Listele de produse de stoc de cumpărat. Se rezolvă automat din Raportări, pe măsură ce intră marfa.</p>
        </div>
        <x-btn variant="primary" :href="route('admin.stock-requisitions.create')" wire:navigate class="self-start">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Necesar nou
        </x-btn>
    </div>

    {{-- Filtre + căutare — pe ecrane mici (<md) sunt ascunse într-un toggle (deschis automat dacă există filtre active); de la md în sus sunt mereu vizibile, fără buton. --}}
    <div x-data="{ filtersOpen: {{ $hasFilters ? 'true' : 'false' }} }" class="mb-4">
        <button type="button" @click="filtersOpen = !filtersOpen"
                class="md:hidden inline-flex items-center gap-1.5 text-sm font-medium text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            Filtre
            @if ($hasFilters)
                <span class="inline-flex items-center rounded-full bg-primary-soft text-primary text-[10px] font-semibold px-1.5 py-0.5">active</span>
            @endif
            <svg class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>

        <div class="hidden md:block mt-2 md:mt-0" :class="filtersOpen ? 'max-md:block' : ''">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="relative sm:flex-1 sm:min-w-48">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Caută după denumire…"
                       class="w-full rounded-lg border border-border bg-white pl-9 pr-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>
            <x-select wire:model="state" live class="sm:w-44"
                      :options="['all' => 'Toate stările'] + $statuses" />
            @if ($hasFilters)
                <button type="button" wire:click="clearFilters" class="text-sm text-ink-soft hover:text-ink px-2 py-2 self-start">
                    Resetează
                </button>
            @endif
        </div>
        </div>
    </div>

    <x-flash class="mb-4" />

    {{-- Antet de coloane (doar desktop). Fiecare necesar = card propriu, spatiat. --}}
    <div class="hidden md:grid grid-cols-[1fr_13rem_6rem_20rem] gap-3 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
        <span>Necesar</span>
        <span>Progres</span>
        <span>Stare</span>
        <span class="text-right">Acțiuni</span>
    </div>

    <div class="space-y-3">
        @forelse ($requisitions as $req)
            @php
                $lines = $req->items;
                $totalLines = $lines->count();
                $doneLines = $lines->filter(fn ($l) => $l->isFullyReceived())->count();

                // Progres pe cantitati (nu doar pe linii complete): fiecare linie
                // contribuie cu min(primit/cerut, 1), ca o linie primita pe jumatate
                // sa se vada si ea in bara.
                $progress = $totalLines > 0
                    ? $lines->avg(fn ($l) => (float) $l->qty_requested > 0
                        ? min(1, (float) $l->qty_received / (float) $l->qty_requested)
                        : 1)
                    : 0;
                $progressPct = (int) round($progress * 100);
            @endphp

            <div wire:key="requisition-{{ $req->id }}" class="rounded-2xl border border-border bg-surface">
                <div class="p-3 md:px-4 md:py-2.5 md:grid md:grid-cols-[1fr_13rem_6rem_20rem] md:items-center md:gap-3">

                    {{-- Denumire --}}
                    <div class="min-w-0">
                        <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Necesar</span>
                        <span class="block font-medium text-ink truncate">{{ $req->label }}</span>
                        <span class="block text-xs text-ink-soft/70 truncate">
                            Creat {{ $req->created_at->format('d.m.Y') }}
                            @if ($req->party)
                                <span class="text-ink-soft/50">—</span> {{ $req->party->name }}
                            @endif
                        </span>
                    </div>

                    {{-- Progres --}}
                    <div class="mt-2 md:mt-0">
                        <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Progres</span>
                        <span class="block text-sm text-ink">{{ $doneLines }} din {{ $totalLines }} {{ $totalLines === 1 ? 'produs primit complet' : 'produse primite complet' }}</span>
                        <div class="mt-1 h-1.5 w-full rounded-full bg-border overflow-hidden">
                            <div class="h-full rounded-full bg-primary" style="width: {{ $progressPct }}%"></div>
                        </div>
                    </div>

                    {{-- Stare --}}
                    <div class="mt-2 md:mt-0">
                        <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Stare</span>
                        @if ($req->status === 'open')
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-primary-soft text-primary">{{ $req->statusLabel() }}</span>
                        @elseif ($req->status === 'fulfilled')
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-info-soft text-info">{{ $req->statusLabel() }}</span>
                        @else
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-surface border border-border text-ink-soft">{{ $req->statusLabel() }}</span>
                        @endif
                    </div>

                    {{-- Acțiuni --}}
                    <div class="mt-3 md:mt-0 flex flex-wrap items-center gap-2 md:justify-end">
                        {{-- DXA: adaugat (Bar - raportari) — doar pt. necesare deschise: aduce grupul in Raportare --}}
                        @if ($req->status === 'open')
                            <x-btn variant="primary" size="icon" outline tooltip="Raportează din acest necesar"
                                   :href="route('admin.stock-reports.create', ['requisition' => $req->id])" wire:navigate>
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                                </svg>
                            </x-btn>
                        @endif

                        <x-btn variant="neutral" size="icon" outline
                               tooltip="{{ $expandedId === $req->id ? 'Ascunde detalii' : 'Detalii' }}"
                               wire:click="toggleExpand({{ $req->id }})">
                            <svg class="w-3 h-3 transition-transform {{ $expandedId === $req->id ? 'rotate-180' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </x-btn>

                        <x-btn variant="warning" size="icon" outline tooltip="Editează" :href="route('admin.stock-requisitions.edit', $req)" wire:navigate>
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                            </svg>
                        </x-btn>

                        <x-btn variant="purple" size="icon" outline tooltip="Duplică" wire:click="duplicate({{ $req->id }})">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="12" height="12" rx="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </x-btn>

                        <x-btn variant="neutral" size="icon" outline tooltip="Export PDF" wire:click="exportPdf({{ $req->id }})" wire:loading.attr="disabled" wire:target="exportPdf({{ $req->id }})">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                        </x-btn>

                        <x-btn variant="success" size="icon" outline tooltip="Trimite pe WhatsApp" :href="$req->whatsAppUrl()" target="_blank" rel="noopener">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 11.5a8.5 8.5 0 0 1-11.98 7.76L3 21l1.83-6.02A8.5 8.5 0 1 1 21 11.5z"/><path d="M8.5 10.5c.3 2.5 2.5 4.7 5 5"/>
                            </svg>
                        </x-btn>

                        @if ($req->status !== 'closed')
                            <x-btn variant="info" size="icon" outline tooltip="Închide necesarul"
                                   x-on:click="askConfirm('Închide necesarul', 'Sigur vrei să închizi „{{ addslashes($req->label) }}”? Îl marchezi ca încheiat, fără să mai aștepți restul produselor. Stocul nu se modifică și îl poți redeschide oricând.', 'close', [{{ $req->id }}], 'Închide', 'info')">
                                {{-- arhivă --}}
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>
                                </svg>
                            </x-btn>
                        @else
                            <x-btn variant="info" size="icon" outline tooltip="Redeschide" wire:click="reopen({{ $req->id }})">
                                {{-- înapoi --}}
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                                </svg>
                            </x-btn>
                        @endif

                        <x-btn variant="danger" size="icon" outline tooltip="Șterge"
                               x-on:click="askConfirm('Șterge necesarul', 'Sigur vrei să ștergi „{{ addslashes($req->label) }}”? Acțiunea nu poate fi anulată.', 'delete', [{{ $req->id }}], 'Șterge', 'danger')">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                            </svg>
                        </x-btn>
                    </div>
                </div>

                {{-- Panou expandat: campuri apropiate (flex-wrap) + tabel de linii --}}
                @if ($expandedId === $req->id)
                    @php
                        // Cost estimat = cantitatea cerută x costul mediu curent (CMP) — nu
                        // pretul ultimei achizitii. Liniile fara cost cunoscut se exclud si se semnaleaza.
                        $estimated = 0.0;
                        $unknownCostLines = 0;
                        foreach ($lines as $l) {
                            if ($l->stockItem->hasKnownCost()) {
                                $estimated += (float) $l->qty_requested * (float) $l->stockItem->avg_cost;
                            } else {
                                $unknownCostLines++;
                            }
                        }
                    @endphp

                    <div class="px-4 pb-4 pt-1 border-t border-border">
                        <div class="flex flex-wrap gap-x-10 gap-y-3 py-3">
                            <div>
                                <span class="block text-[11px] uppercase tracking-wide text-ink-soft/60 mb-0.5">Petrecere</span>
                                @if ($req->party)
                                    <span class="text-sm text-ink">{{ $req->party->name }}</span>
                                @else
                                    <span class="text-sm text-ink-soft/40">—</span>
                                @endif
                            </div>
                            <div>
                                <span class="block text-[11px] uppercase tracking-wide text-ink-soft/60 mb-0.5">Creat de</span>
                                <span class="text-sm text-ink">{{ $req->creator?->name ?: '—' }}</span>
                                <span class="block text-xs text-ink-soft/60">{{ $req->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                            <div>
                                <span class="block text-[11px] uppercase tracking-wide text-ink-soft/60 mb-0.5">Cost estimat</span>
                                @if ($totalLines > 0 && $unknownCostLines < $totalLines)
                                    <span class="text-sm font-medium text-ink">{{ number_format($estimated, 2, ',', '.') }} lei</span>
                                    @if ($unknownCostLines > 0)
                                        <span class="block text-xs text-ink-soft/60">+ {{ $unknownCostLines }} {{ $unknownCostLines === 1 ? 'produs cu cost necunoscut, exclus' : 'produse cu cost necunoscut, excluse' }}</span>
                                    @else
                                        <span class="block text-xs text-ink-soft/60">la costul mediu curent</span>
                                    @endif
                                @else
                                    <span class="text-sm text-ink-soft/60 italic">necunoscut</span>
                                @endif
                            </div>
                        </div>

                        <div class="border-t border-border pt-3">
                            <div class="hidden sm:grid grid-cols-[1fr_9rem_8rem_8rem] gap-3 text-[11px] font-semibold uppercase tracking-wide text-ink-soft/60 pb-2">
                                <span>Produse necesare</span>
                                <span class="text-right">Cerut</span>
                                <span class="text-right">Primit</span>
                                <span class="text-right">Rămas</span>
                            </div>

                            @if ($lines->isEmpty())
                                <p class="text-sm text-ink-soft">Niciun produs în acest necesar.</p>
                            @else
                                <div class="space-y-0.5">
                                    @foreach ($lines->sortBy(fn ($l) => mb_strtolower($l->stockItem->name)) as $line)
                                        @php
                                            $si = $line->stockItem;
                                            $remaining = $line->remainingQty();
                                        @endphp
                                        <div wire:key="req-{{ $req->id }}-line-{{ $line->id }}"
                                             class="py-2 sm:py-1.5 border-b border-border/60 last:border-0 sm:grid sm:grid-cols-[1fr_9rem_8rem_8rem] sm:gap-3 sm:items-start">
                                            <div class="min-w-0">
                                                <span class="text-sm text-ink">{{ $si->name }}</span>
                                                @unless ($si->is_active)
                                                    <span class="ml-1 text-[10px] text-ink-soft/60">(inactiv)</span>
                                                @endunless
                                            </div>

                                            <div class="mt-0.5 sm:mt-0 sm:text-right text-sm text-ink-soft">
                                                <span class="sm:hidden text-[11px] uppercase text-ink-soft/50">Cerut: </span>{{ $fmt($line->qty_requested) }} {{ $si->unit }}
                                                @if ($si->hasPackage())
                                                    <span class="block text-xs text-ink-soft/60">{{ $si->packageDisplayFor((float) $line->qty_requested) }}</span>
                                                @endif
                                            </div>

                                            <div class="mt-0.5 sm:mt-0 sm:text-right text-sm text-ink-soft">
                                                <span class="sm:hidden text-[11px] uppercase text-ink-soft/50">Primit: </span>
                                                @if ((float) $line->qty_received > 0)
                                                    {{ $fmt($line->qty_received) }} {{ $si->unit }}
                                                @else
                                                    —
                                                @endif
                                            </div>

                                            <div class="mt-0.5 sm:mt-0 sm:text-right text-sm">
                                                <span class="sm:hidden text-[11px] uppercase text-ink-soft/50">Rămas: </span>
                                                @if ($line->isFullyReceived())
                                                    <span class="inline-flex items-center rounded-full bg-primary-soft text-primary text-[10px] font-semibold px-1.5 py-0.5">complet</span>
                                                @else
                                                    <span class="text-ink">{{ $fmt($remaining) }} {{ $si->unit }}</span>
                                                    @if ($si->hasPackage())
                                                        <span class="block text-xs text-ink-soft/60">{{ $si->packageDisplayFor($remaining) }}</span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
                @if ($hasFilters)
                    Niciun necesar care să corespundă filtrelor.
                @else
                    Niciun necesar încă. Apasă „Necesar nou" ca să creezi primul.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $requisitions->onEachSide(1)->links('pagination.dxa') }}
    </div>

    {{-- Modal de confirmare generic (închidere / ștergere) --}}
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
