<div>
    @php
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
        $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    @endphp

    {{-- ============================================================= --}}
    {{-- RAPORT FINALIZAT — read-only, din miscarile reale             --}}
    {{-- ============================================================= --}}
    @if ($readOnlyView)
        <div class="max-w-3xl">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold text-ink">{{ $report->title() }}</h2>
                        <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-info-soft text-info">Finalizat</span>
                    </div>
                    <p class="mt-1 text-sm text-ink-soft">
                        Finalizată {{ $report->finalized_at?->format('d.m.Y H:i') }}
                        @if ($report->finalizer) de {{ $report->finalizer->name }} @endif
                        @if ($report->party) <span class="text-ink-soft/50">·</span> {{ $report->party->name }} @endif
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <x-btn variant="neutral" size="sm" outline wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export PDF
                    </x-btn>
                    <a href="{{ route('admin.stock-reports.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">← Înapoi la Raportări</a>
                </div>
            </div>

            <x-flash class="mb-4" />

            {{-- Totaluri --}}
            <div class="grid grid-cols-3 gap-3 mb-5">
                <x-stat-card label="Venit" :value="$money($revenue).' lei'" accent="primary" />
                <x-stat-card label="Cost" :value="$money($cost).' lei'" accent="neutral" hint="vânzări + pierderi cunoscute" />
                <x-stat-card label="Profit" :value="$money($profit).' lei'" :accent="$profit >= 0 ? 'info' : 'danger'" />
            </div>

            @if ($report->note)
                <div class="mb-5 rounded-xl border border-border bg-surface p-4">
                    <span class="block text-[11px] uppercase tracking-wide text-ink-soft/60 mb-1">Notă</span>
                    <p class="text-sm text-ink whitespace-pre-line">{{ $report->note }}</p>
                </div>
            @endif

            {{-- Intrari --}}
            <div class="mb-5">
                <h3 class="text-sm font-semibold text-ink mb-2">Intrări</h3>
                @if ($finalEntries->isEmpty())
                    <p class="text-sm text-ink-soft/60 italic">Nicio intrare.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($finalEntries as $m)
                            <div class="rounded-xl border border-border bg-surface px-4 py-2.5 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                <div class="min-w-0">
                                    <span class="text-sm text-ink">{{ $m->stockItem->name }}</span>
                                    @if ($m->requisitionItem)
                                        <span class="ml-1 text-xs text-primary">· din necesar {{ $m->requisitionItem->requisition->numberedLabel() }}</span>
                                    @endif
                                </div>
                                <div class="text-sm text-ink-soft shrink-0 text-right">
                                    <div>
                                        <span class="text-ink">{{ $fmt($m->qty) }} {{ $m->stockItem->unit }}</span>
                                        @if ($m->unit_cost !== null)
                                            <span class="text-ink-soft/50">·</span> {{ number_format((float) $m->unit_cost, 4, ',', '.') }} lei/{{ $m->stockItem->unit }}
                                        @else
                                            <span class="text-ink-soft/50">·</span> <span class="italic">cost necunoscut</span>
                                        @endif
                                    </div>
                                    @if ($m->stockItem->hasPackage())
                                        <span class="block text-xs text-ink-soft/70">{{ $m->stockItem->packageDisplayFor((float) $m->qty) }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Vanzari --}}
            <div class="mb-5">
                <h3 class="text-sm font-semibold text-ink mb-2">Vânzări</h3>
                @if ($finalSales->isEmpty())
                    <p class="text-sm text-ink-soft/60 italic">Nicio vânzare.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($finalSales as $s)
                            <div class="rounded-xl border border-border bg-surface px-4 py-2.5 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                <span class="text-sm text-ink min-w-0">{{ $s->menuItem?->name ?? '—' }}
                                    @if ($s->salesGroup) <span class="ml-1 inline-flex items-center rounded-full bg-info-soft text-info text-[11px] font-medium px-1.5 py-0.5">sesiunea: {{ $s->salesGroup->title() }}</span>
                                    @elseif ($s->is_loose) <span class="ml-1 inline-flex items-center rounded-full bg-info-soft text-info text-[11px] font-medium px-1.5 py-0.5">vânzări simple (fără sesiune)</span> @endif
                                </span>
                                <div class="text-sm text-ink-soft shrink-0">
                                    <span class="text-ink">{{ $fmt($s->qty) }} ×</span> {{ $money($s->unit_price) }} lei
                                    <span class="text-ink-soft/50">=</span> <span class="text-ink font-medium">{{ $money($s->total_price) }} lei</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Pierderi --}}
            <div class="mb-2">
                <h3 class="text-sm font-semibold text-ink mb-2">Pierderi</h3>
                @if ($finalLosses->isEmpty())
                    <p class="text-sm text-ink-soft/60 italic">Nicio pierdere.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($finalLosses as $l)
                            <div class="rounded-xl border border-border bg-surface px-4 py-2.5">
                                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                    <span class="text-sm text-ink min-w-0">{{ $l->stockItem->name }}</span>
                                    <div class="text-right shrink-0">
                                        <span class="text-sm text-ink">{{ $fmt($l->qty) }} {{ $l->stockItem->unit }}</span>
                                        @if ($l->stockItem->hasPackage())
                                            <span class="block text-xs text-ink-soft/70">{{ $l->stockItem->packageDisplayFor((float) $l->qty) }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if ($l->note)
                                    <p class="mt-0.5 text-xs text-ink-soft">Motiv: {{ $l->note }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- DXA: adaugat (Bar - numaratoare de final de seara) --}}
            @if ($closing)
                @include('livewire.admin.stock-reports._closing-summary', ['closing' => $closing, 'finalCounts' => $finalCounts])
            @endif
        </div>

    {{-- ============================================================= --}}
    {{-- DRAFT — formular editabil cu autosave                         --}}
    {{-- ============================================================= --}}
    @else
        @php
            $partyOptions = ['' => 'Fără petrecere'] + $parties->mapWithKeys(fn ($p) => [
                $p->id => $p->name.($p->starts_at ? ' — '.$p->starts_at->format('d.m.Y') : ''),
            ])->all();

            $stockItemOptions = $stockItems->mapWithKeys(fn ($s) => [$s->id => $s->name.' ('.$s->unit.')'])->all();

            $menuItemOptions = $menuItems->mapWithKeys(fn ($m) => [$m->id => $m->name])->all();

            $reqOptions = ['' => 'Alege un necesar…'] + $openRequisitions->mapWithKeys(fn ($r) => [$r->id => $r->label])->all();

            $isEditing = $report && $report->exists;

            // Intrari: separam randurile aduse dintr-un necesar (grupate) de cele libere.
            // preserveKeys=true — critic: fara el, groupBy reindexeaza randurile in
            // fiecare grup (0,1,2...) si wire:model="entries.{{ $i }}.qty" ar lega
            // inputul de POZITIA GRESITA din $entries (bug confirmat: cantitatile
            // aparute pe randul urmator, ca un shift).
            $grouped = collect($entries)->filter(fn ($r) => ! empty($r['requisition_item_id']))->groupBy('req_id', true);
            $freeEntries = collect($entries)->filter(fn ($r) => empty($r['requisition_item_id']));
        @endphp

        <div class="max-w-6xl">
            <div class="mb-5">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-ink">{{ $isEditing ? 'Editează raportarea nr. '.$report->number() : 'Raportare nouă' }}</h2>
                    <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-primary-soft text-primary">Draft</span>
                </div>
                <p class="mt-1 text-sm text-ink-soft leading-relaxed">
                    Completează intrările, vânzările (poți aduce și o sesiune de vânzări din bar), pierderile și, la final de seară, numărătoarea. Se salvează automat pe măsură ce completezi — poți reveni oricând. Stocul nu se modifică până la <span class="font-medium text-ink">Finalizează</span>.
                </p>
            </div>

            <x-flash class="mb-4" />

            <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-6">
            <div class="min-w-0 max-w-3xl">

            {{-- Antet: dată + petrecere + notă --}}
            <div class="bg-surface border border-border rounded-2xl p-5 space-y-4 mb-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="date" class="block text-sm font-medium text-ink mb-1.5">Data raportării</label>
                        <input type="date" id="date" wire:model.live="date"
                               class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5">Petrecere <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                        <x-select wire:model="party_id" live placeholder="Fără petrecere" :options="$partyOptions" />
                    </div>
                </div>
                <div>
                    <label for="note" class="block text-sm font-medium text-ink mb-1.5">Notă <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                    <textarea id="note" wire:model.blur="note" rows="2" placeholder="ex. observații despre raportare…"
                              class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"></textarea>
                </div>
            </div>

            {{-- ===================== INTRĂRI ===================== --}}
            <section class="mb-5 rounded-2xl border border-border border-l-2 border-l-success bg-surface p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-success-soft text-success shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </span>
                    <h3 class="text-base font-semibold text-ink">Intrări</h3>
                    <span class="text-xs text-ink-soft">aprovizionare — produs de stoc + cantitate + cost/unitate (opțional — dacă lipsește, CMP rămâne neschimbat)</span>
                </div>

                {{-- Aducere din necesar deschis --}}
                @if ($openRequisitions->isNotEmpty())
                    <div class="rounded-xl border border-dashed border-primary/40 bg-primary-soft/30 p-3.5 mb-3">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <span class="text-sm font-medium text-ink shrink-0">Adaugă dintr-un necesar:</span>
                            <div class="sm:flex-1 sm:max-w-xs">
                                <x-select wire:model="importReqId" live placeholder="Alege un necesar…" :options="$reqOptions" />
                            </div>
                        </div>

                        @if ($importRequisition)
                            @php
                                $remainingItems = $importRequisition->items
                                    ->filter(fn ($it) => $it->remainingQty() > 0 && ! in_array($it->id, $importedItemIds, true))
                                    ->sortBy(fn ($it) => mb_strtolower($it->stockItem->name));
                            @endphp

                            <div class="mt-3 border-t border-primary/20 pt-3">
                                @if ($remainingItems->isEmpty())
                                    <p class="text-sm text-ink-soft">Tot ce lipsea din necesarul {{ $importRequisition->numberedLabel() }} a fost deja adus sau primit.</p>
                                @else
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-medium text-ink-soft">{{ $remainingItems->count() }} {{ $remainingItems->count() === 1 ? 'produs de adus' : 'produse de adus' }}</span>
                                        <x-btn variant="primary" size="sm" wire:click="importRequisition({{ $importRequisition->id }})">Adaugă toate</x-btn>
                                    </div>
                                    <div class="space-y-1">
                                        @foreach ($remainingItems as $it)
                                            <div wire:key="import-item-{{ $it->id }}" class="flex items-center justify-between gap-3 rounded-lg bg-white border border-border px-3 py-2">
                                                <div class="min-w-0">
                                                    <span class="text-sm text-ink">{{ $it->stockItem->name }}</span>
                                                    <span class="block text-xs text-ink-soft/70">cerut {{ $fmt($it->qty_requested) }} · rămas {{ $fmt($it->remainingQty()) }} {{ $it->stockItem->unit }}</span>
                                                </div>
                                                <button type="button" wire:click="importRequisitionItem({{ $it->id }})"
                                                        class="shrink-0 inline-flex items-center gap-1 rounded-md bg-primary-soft text-primary text-xs font-medium px-2.5 py-1.5 hover:bg-primary hover:text-white transition-colors">
                                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                    Adaugă
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                @if ($importMessage)
                    <x-alert type="info" dismiss-prop="importMessage" :dismiss-value="null" class="mb-3">{{ $importMessage }}</x-alert>
                @endif

                {{-- Grupuri aduse din necesare --}}
                @foreach ($grouped as $reqId => $groupRows)
                    @php $reqLabel = $groupRows->first()['req_label'] ?? ''; @endphp
                    <div wire:key="entry-group-{{ $reqId }}" class="rounded-2xl border border-primary/30 bg-surface mb-3 overflow-visible">
                        <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-primary/20 bg-primary-soft/30 rounded-t-2xl">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="w-4 h-4 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><rect x="9" y="2" width="6" height="4" rx="1"/></svg>
                                <span class="text-sm font-medium text-ink truncate">din necesar {{ $reqLabel }}</span>
                            </div>
                            <button type="button" wire:click="removeRequisitionGroup({{ $reqId }})"
                                    class="shrink-0 text-xs text-ink-soft/70 hover:text-danger">Șterge tot grupul</button>
                        </div>

                        <div class="p-3 space-y-3">
                            @foreach ($groupRows as $i => $line)
                                @php $si = ! empty($line['stock_item_id']) ? $stockItems->firstWhere('id', (int) $line['stock_item_id']) : null; @endphp
                                <div wire:key="entry-req-{{ $line['requisition_item_id'] }}">
                                    <div class="flex flex-wrap items-start gap-2">
                                        <div class="flex-1 min-w-0 rounded-lg border border-border bg-bg px-3.5 py-2.5 text-sm text-ink truncate">
                                            {{ $si?->name ?? 'Produs' }} @if ($si)<span class="text-ink-soft/60">({{ $si->unit }})</span>@endif
                                        </div>
                                        <div class="w-32 shrink-0">
                                            <div class="relative">
                                                <input type="number" step="0.001" min="0" wire:model.live.debounce.500ms="entries.{{ $i }}.qty" placeholder="Cant."
                                                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary {{ $si ? 'pr-10' : '' }}">
                                                @if ($si)<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-ink-soft/60">{{ $si->unit }}</span>@endif
                                            </div>
                                        </div>
                                        @if ($si)
                                            <div class="w-40 shrink-0">
                                                <div class="relative">
                                                    <input type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="entries.{{ $i }}.unit_cost" placeholder="Cost/unit."
                                                           class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary pr-12">
                                                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-ink-soft/60">lei/{{ $si->unit }}</span>
                                                </div>
                                            </div>
                                        @endif
                                        <button type="button" wire:click="removeEntry({{ $i }})" title="Elimină"
                                                class="shrink-0 inline-flex items-center justify-center w-[42px] h-[42px] rounded-lg text-ink-soft/60 hover:text-danger hover:bg-danger/10">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </div>

                                    @if ($si && $si->unit !== 'buc')
                                        <x-cost-helper wire:key="entry-cost-req-{{ $line['requisition_item_id'] }}" path="entries.{{ $i }}.unit_cost" :unit="$si->unit" :default-qty="$si->hasPackage() ? $si->package_qty : null" trigger-class="w-40 shrink-0" class="!mt-1.5">
                                            <x-slot:prefix>
                                                <div class="flex-1 min-w-0 flex items-center">
                                                    <span class="text-xs text-ink-soft">cerut {{ $fmt($line['req_requested'] ?? 0) }} · rămas {{ $fmt($line['req_remaining'] ?? 0) }} {{ $si->unit }}</span>
                                                </div>
                                                <div class="w-32 shrink-0"></div>
                                            </x-slot:prefix>
                                            <x-slot:suffix>
                                                <div class="w-[42px] shrink-0"></div>
                                            </x-slot:suffix>
                                        </x-cost-helper>
                                    @else
                                        <div class="mt-1.5 text-xs text-ink-soft">
                                            cerut {{ $fmt($line['req_requested'] ?? 0) }} · rămas {{ $fmt($line['req_remaining'] ?? 0) }} {{ $si?->unit }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Randuri libere de intrare --}}
                <div class="space-y-3">
                    @foreach ($freeEntries as $i => $line)
                        @php $si = ! empty($line['stock_item_id']) ? $stockItems->firstWhere('id', (int) $line['stock_item_id']) : null; @endphp
                        <div wire:key="entry-free-{{ $i }}">
                            <div class="flex flex-wrap items-start gap-2">
                                <x-dropdown-select path="entries.{{ $i }}.stock_item_id" :options="$stockItemOptions" :selected="$line['stock_item_id'] ?? ''" placeholder="Alege produsul…" class="flex-1" />
                                <div class="w-32 shrink-0">
                                    <div class="relative">
                                        <input type="number" step="0.001" min="0" wire:model.live.debounce.500ms="entries.{{ $i }}.qty" placeholder="Cant."
                                               class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary {{ $si ? 'pr-10' : '' }}">
                                        @if ($si)<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-ink-soft/60">{{ $si->unit }}</span>@endif
                                    </div>
                                </div>
                                @if ($si)
                                    <div class="w-40 shrink-0">
                                        <div class="relative">
                                            <input type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="entries.{{ $i }}.unit_cost" placeholder="Cost/unit."
                                                   class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary pr-12">
                                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-ink-soft/60">lei/{{ $si->unit }}</span>
                                        </div>
                                    </div>
                                @endif
                                @if (! empty($line['stock_item_id']))
                                    <button type="button" wire:click="removeEntry({{ $i }})" title="Elimină"
                                            class="shrink-0 inline-flex items-center justify-center w-[42px] h-[42px] rounded-lg text-ink-soft/60 hover:text-danger hover:bg-danger/10">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                @endif
                            </div>
                            @if ($si && $si->unit !== 'buc')
                                <x-cost-helper wire:key="entry-cost-free-{{ $i }}-{{ $line['stock_item_id'] }}" path="entries.{{ $i }}.unit_cost" :unit="$si->unit" :default-qty="$si->hasPackage() ? $si->package_qty : null" trigger-class="w-40 shrink-0">
                                    <x-slot:prefix>
                                        <div class="flex-1 min-w-0"></div>
                                        <div class="w-32 shrink-0"></div>
                                    </x-slot:prefix>
                                    <x-slot:suffix>
                                        <div class="w-[42px] shrink-0"></div>
                                    </x-slot:suffix>
                                </x-cost-helper>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ===================== VÂNZĂRI ===================== --}}
            <section class="mb-5 rounded-2xl border border-border border-l-2 border-l-info bg-surface p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-info-soft text-info shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    </span>
                    <h3 class="text-base font-semibold text-ink">Vânzări</h3>
                    <span class="text-xs text-ink-soft">produs de meniu + cantitate — prețul e cel din meniu</span>
                </div>

                {{-- Vânzări înregistrate în aplicație / admin (sesiune de vânzări + vânzări simple), agregate pe produs, doar pentru citire --}}
                @php
                    $groupOptions = ['' => 'Fără sesiune'] + $salesGroups->mapWithKeys(fn ($g) => [$g->id => $g->label()])->all();
                    $methodLabels = \App\Models\SalePayment::METHODS;
                @endphp
                <div class="mb-4 rounded-xl border border-border bg-bg/50 p-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-ink shrink-0">Sesiune de vânzări</span>
                        <x-select wire:model="sales_group_id" live placeholder="Alege o sesiune…" :options="$groupOptions" class="flex-1 min-w-0" />
                        <button type="button" wire:click="$refresh" title="Reîmprospătează vânzările" aria-label="Reîmprospătează vânzările"
                                class="shrink-0 inline-flex items-center justify-center w-[42px] h-[42px] rounded-lg border border-border bg-white text-ink-soft hover:bg-bg hover:text-ink">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        </button>
                    </div>

                    {{-- Sesiuni deschise, dar deja alese intr-un ALT draft (o sesiune se poate consuma intr-o singura raportare) --}}
                    @foreach ($takenGroups as $taken)
                        <p class="mt-2 text-xs text-ink-soft/70">
                            Sesiunea „{{ $taken->group->label() }}" e deja aleasă în
                            <a href="{{ route('admin.stock-reports.edit', $taken->report) }}" wire:navigate class="text-primary hover:underline">raportarea nr. {{ $taken->report->number() }}</a> (draft).
                        </p>
                    @endforeach

                    @if ($salesGroups->isEmpty() && ! $selectedGroup && $takenGroups->isEmpty())
                        <p class="mt-2 text-xs text-ink-soft/70">Nu există sesiuni de vânzări deschise.</p>
                    @endif

                    @if ($groupData)
                        @include('livewire.admin.stock-reports._registered-sales', ['data' => $groupData])
                        <p class="mt-2 text-[11px] text-ink-soft/70">
                            Rândurile din sesiune sunt doar pentru citire. Sesiunea se închide la finalizarea raportării — vânzările noi din aplicație nu mai intră după aceea.
                        </p>
                    @endif

                    {{-- Vânzări simple (fără sesiune), neraportate --}}
                    @if ($looseData)
                        <div class="mt-3 pt-3 border-t border-border">
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model.live="include_loose_sales"
                                       class="mt-0.5 w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                                <span class="text-sm text-ink">
                                    Include vânzările simple (fără sesiune) neraportate
                                    <span class="block text-xs text-ink-soft">{{ $looseData['completed'] }} {{ $looseData['completed'] === 1 ? 'vânzare' : 'vânzări' }} · {{ $money($looseData['revenue']) }} lei — se postează toate cele existente la finalizare</span>
                                </span>
                            </label>

                            @if ($include_loose_sales)
                                @include('livewire.admin.stock-reports._registered-sales', ['data' => $looseData])
                            @endif
                        </div>
                    @endif
                </div>

                @if ($selectedGroup || ($looseData && $include_loose_sales))
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
                        Vânzări suplimentare <span class="normal-case font-normal text-ink-soft/60">— cele care nu au trecut prin aplicație</span>
                    </div>
                @endif

                <div class="space-y-3">
                    @foreach ($sales as $i => $line)
                        @php $mi = ! empty($line['menu_item_id']) ? $menuItems->firstWhere('id', (int) $line['menu_item_id']) : null; @endphp
                        <div wire:key="sale-{{ $i }}">
                            <div class="flex items-start gap-2">
                                <x-dropdown-select path="sales.{{ $i }}.menu_item_id" :options="$menuItemOptions" :selected="$line['menu_item_id'] ?? ''" placeholder="Alege produsul de meniu…" class="flex-1" />
                                <div class="w-28 shrink-0">
                                    <input type="number" step="0.001" min="0" wire:model.live.debounce.500ms="sales.{{ $i }}.qty" placeholder="Cant."
                                           class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                                </div>
                                @if (! empty($line['menu_item_id']))
                                    <button type="button" wire:click="removeSale({{ $i }})" title="Elimină"
                                            class="shrink-0 inline-flex items-center justify-center w-[42px] h-[42px] rounded-lg text-ink-soft/60 hover:text-danger hover:bg-danger/10">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                @endif
                            </div>
                            @if ($mi)
                                <div class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-ink-soft">
                                    <span>{{ number_format((float) $mi->price, 2, ',', '.') }} lei/buc</span>
                                    @if (is_numeric($line['qty']) && (float) $line['qty'] > 0)
                                        <span class="text-ink font-medium">= {{ $money((float) $line['qty'] * (float) $mi->price) }} lei</span>
                                    @endif
                                    @unless ($mi->isTracked())
                                        <span class="text-warning">nu e legat de stoc (fără rețetă) — doar venit</span>
                                    @endunless
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ===================== PIERDERI ===================== --}}
            <section class="mb-5 rounded-2xl border border-border border-l-2 border-l-danger bg-surface p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-danger/10 text-danger shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </span>
                    <h3 class="text-base font-semibold text-ink">Pierderi</h3>
                    <span class="text-xs text-ink-soft">consum/stricăciune — produs de stoc + cantitate + motiv</span>
                </div>

                <div class="space-y-3">
                    @foreach ($losses as $i => $line)
                        @php
                            $si = ! empty($line['stock_item_id']) ? $stockItems->firstWhere('id', (int) $line['stock_item_id']) : null;
                        @endphp
                        <div wire:key="loss-{{ $i }}">
                            <div class="flex items-start gap-2">
                                <x-dropdown-select path="losses.{{ $i }}.stock_item_id" :options="$stockItemOptions" :selected="$line['stock_item_id'] ?? ''" placeholder="Alege produsul…" class="flex-1" />
                                <div class="w-28 shrink-0">
                                    <div class="relative">
                                        <input type="number" step="0.001" min="0" wire:model.live.debounce.500ms="losses.{{ $i }}.qty" placeholder="Cant."
                                               class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary {{ $si ? 'pr-10' : '' }}">
                                        @if ($si)<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-ink-soft/60">{{ $si->unit }}</span>@endif
                                    </div>
                                </div>
                                @if (! empty($line['stock_item_id']))
                                    <button type="button" wire:click="removeLoss({{ $i }})" title="Elimină"
                                            class="shrink-0 inline-flex items-center justify-center w-[42px] h-[42px] rounded-lg text-ink-soft/60 hover:text-danger hover:bg-danger/10">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                @endif
                            </div>
                            @if ($si)
                                @if ($si->unit !== 'buc')
                                    <x-qty-helper wire:key="loss-qty-{{ $i }}-{{ $line['stock_item_id'] }}" path="losses.{{ $i }}.qty" :unit="$si->unit" :default-size="$si->hasPackage() ? $si->package_qty : null" trigger-class="w-28 shrink-0">
                                        <x-slot:prefix>
                                            <div class="flex-1 min-w-0"></div>
                                        </x-slot:prefix>
                                        <x-slot:suffix>
                                            <div class="w-[42px] shrink-0"></div>
                                        </x-slot:suffix>
                                    </x-qty-helper>
                                @endif
                                <div class="mt-1.5">
                                    <input type="text" wire:model.blur="losses.{{ $i }}.note" placeholder="Motiv (opțional) — ex. spart, testare rețetă…"
                                           class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ===================== NUMĂRĂTOARE DE FINAL DE SEARĂ ===================== --}}
            {{-- DXA: adaugat (Bar - numaratoare). Optionala; se salveaza automat ca restul draftului. Asteptat vs numarat, live. --}}
            @php
                $signedMoney = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).$money(abs($n));
                $diffTone = fn ($n) => $n === null ? 'text-ink-soft' : (abs($n) < 0.005 ? 'text-success' : ($n < 0 ? 'text-danger' : 'text-warning'));
                $showTokens = $closing['usesTokens'] || $counted_tokens !== '';
                $inputCls = 'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 pr-12 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
            @endphp
            <section class="mb-5 rounded-2xl border border-border border-l-2 border-l-purple bg-surface p-4">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mb-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-purple/10 text-purple shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><polyline points="9 14 11 16 15 12"/></svg>
                    </span>
                    <h3 class="text-base font-semibold text-ink">Numărătoare de final de seară</h3>
                    <span class="text-xs text-ink-soft">opțional — compară ce ai numărat cu ce arată aplicația</span>
                </div>

                {{-- Cash + tokeni --}}
                <div class="grid gap-3 {{ $showTokens ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
                    <div>
                        <label for="opening_float" class="block text-sm font-medium text-ink mb-1.5">Fond de casă <span class="text-ink-soft/60 font-normal">(la început)</span></label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" inputmode="decimal" id="opening_float" wire:model.live.debounce.700ms="opening_float" class="{{ $inputCls }}">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-ink-soft/60">lei</span>
                        </div>
                        @error('opening_float') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="counted_cash" class="block text-sm font-medium text-ink mb-1.5">Cash numărat</label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" inputmode="decimal" id="counted_cash" wire:model.live.debounce.700ms="counted_cash" class="{{ $inputCls }}">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-ink-soft/60">lei</span>
                        </div>
                        @error('counted_cash') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    @if ($showTokens)
                        <div>
                            <label for="counted_tokens" class="block text-sm font-medium text-ink mb-1.5">Tokeni numărați</label>
                            <div class="relative">
                                <input type="number" step="1" min="0" inputmode="numeric" id="counted_tokens" wire:model.live.debounce.700ms="counted_tokens" class="{{ $inputCls }}">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-ink-soft/60">tk</span>
                            </div>
                            @error('counted_tokens') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="mt-3 grid gap-2 {{ $showTokens ? 'sm:grid-cols-2' : '' }}">
                    <div class="rounded-xl border border-border bg-bg/50 px-3 py-2.5">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5">
                            <span class="text-sm font-medium text-ink">Cash</span>
                            <span class="text-sm text-ink-soft">așteptat <span class="text-ink font-medium">{{ $money($closing['expectedCash']) }} lei</span></span>
                        </div>
                        <span class="block text-xs text-ink-soft/70">fond {{ $money($closing['float']) }} + încasat în vânzări {{ $money($closing['cashIn']) }}</span>
                        @if ($closing['cashDiff'] !== null)
                            <div class="mt-1 text-sm text-ink-soft">
                                numărat <span class="text-ink">{{ $money($closing['countedCash']) }} lei</span> ·
                                <span class="font-semibold {{ $diffTone($closing['cashDiff']) }}">{{ abs($closing['cashDiff']) < 0.005 ? 'corect' : ($closing['cashDiff'] < 0 ? 'lipsă ' : 'surplus ').$money(abs($closing['cashDiff'])).' lei' }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($showTokens)
                        <div class="rounded-xl border border-border bg-bg/50 px-3 py-2.5">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5">
                                <span class="text-sm font-medium text-ink">Tokeni</span>
                                <span class="text-sm text-ink-soft">așteptat <span class="text-ink font-medium">{{ $closing['expectedTokens'] }} tk</span></span>
                            </div>
                            <span class="block text-xs text-ink-soft/70">tokeni încasați în vânzări</span>
                            @if ($closing['tokensDiff'] !== null)
                                <div class="mt-1 text-sm text-ink-soft">
                                    numărat <span class="text-ink">{{ $closing['countedTokens'] }} tk</span> ·
                                    <span class="font-semibold {{ $diffTone($closing['tokensDiff']) }}">{{ $closing['tokensDiff'] === 0 ? 'corect' : ($closing['tokensDiff'] < 0 ? 'lipsă ' : 'surplus ').abs($closing['tokensDiff']).' tk' }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                @unless ($closing['hasSource'])
                    <p class="mt-2 text-xs text-ink-soft/70">Alege o sesiune de vânzări (sau include vânzările simple) în secțiunea Vânzări ca să se calculeze încasările așteptate.</p>
                @endunless
                @if ($closing['creditIn'] > 0 || $closing['benefitIn'] > 0)
                    <p class="mt-2 text-xs text-ink-soft/70">
                        Nu se numără fizic:
                        @if ($closing['creditIn'] > 0) credit (aplicație) {{ $money($closing['creditIn']) }} lei @endif
                        @if ($closing['creditIn'] > 0 && $closing['benefitIn'] > 0) · @endif
                        @if ($closing['benefitIn'] > 0) beneficii {{ $money($closing['benefitIn']) }} lei @endif
                    </p>
                @endif
                @if ($closing['manualRevenue'] > 0)
                    <p class="mt-2 text-xs text-warning">
                        Ai {{ $money($closing['manualRevenue']) }} lei în vânzări suplimentare (introduse manual, fără metodă de plată). Dacă au fost încasate cash, la cash te aștepți la un surplus de aceeași valoare.
                    </p>
                @endif

                {{-- Inventar --}}
                <div class="mt-4 pt-4 border-t border-border" x-data="{ open: {{ $closing['countedItems'] > 0 ? 'true' : 'false' }}, q: '' }">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 text-left">
                            <svg class="w-4 h-4 text-ink-soft transition-transform" :class="open ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            <span class="text-sm font-semibold text-ink">Inventar</span>
                            <span class="text-xs text-ink-soft">{{ $closing['countedItems'] }} numărate din {{ count($closing['rows']) }}</span>
                        </button>
                        <span class="text-xs text-ink-soft/70">așteptat = stocul după această raportare</span>
                    </div>

                    <div x-show="open" x-cloak class="mt-3">
                        @if (empty($closing['rows']))
                            <p class="text-sm text-ink-soft/60 italic">Nu există produse de stoc active.</p>
                        @else
                            <input type="search" x-model="q" placeholder="Caută produs…"
                                   class="mb-3 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">

                            <div class="space-y-2">
                                @foreach ($closing['rows'] as $row)
                                    @php
                                        $rid = $row['id'];
                                        $cPath = 'counts.'.$rid;
                                        $cUnit = $row['unit'];
                                        $cSize = $row['package_qty'];
                                        $needle = mb_strtolower($row['name']);
                                    @endphp
                                    <div wire:key="count-{{ $rid }}" x-show="q === '' || @js($needle).includes(q.toLowerCase())" class="rounded-xl border border-border bg-bg/40 px-3 py-2.5">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <span class="block text-sm text-ink">{{ $row['name'] }}</span>
                                                <span class="block text-xs text-ink-soft">
                                                    așteptat: {{ $fmt($row['expected']) }} {{ $row['unit'] }}
                                                    @if ($row['package']) <span class="text-ink-soft/70">· {{ $row['package'] }}</span> @endif
                                                </span>
                                            </div>
                                            <div class="w-32 shrink-0">
                                                <div class="relative">
                                                    <input type="number" step="0.001" min="0" inputmode="decimal" wire:model.live.debounce.700ms="counts.{{ $rid }}" placeholder="Numărat"
                                                           class="w-full rounded-lg border border-border bg-white px-3 py-2.5 pr-10 text-sm text-ink placeholder:text-ink-soft/50 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                                                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-ink-soft/60">{{ $row['unit'] }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        @error($cPath) <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror

                                        @if ($row['counted'])
                                            @php
                                                $d = $row['diff'];
                                                $dOk = abs($d) < 0.0005;
                                                $dCls = $dOk ? 'bg-success-soft text-success' : ($d < 0 ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning');
                                                $dTxt = $dOk ? 'corect' : (($d < 0 ? 'lipsă ' : 'surplus ').$fmt(abs($d)).' '.$row['unit']);
                                            @endphp
                                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $dCls }}">{{ $dTxt }}</span>
                                                @if (! $dOk)
                                                    @if ($row['value'] !== null)
                                                        <span class="text-xs text-ink-soft">≈ {{ $signedMoney($row['value']) }} lei</span>
                                                    @else
                                                        <span class="text-xs text-ink-soft/70 italic">cost necunoscut</span>
                                                    @endif
                                                @endif
                                            </div>
                                        @endif

                                        @if ($row['has_package'] && $row['unit'] !== 'buc')
                                            <x-qty-helper wire:key="count-qty-{{ $rid }}" :path="$cPath" :unit="$cUnit" :default-size="$cSize" trigger-class="w-32 shrink-0">
                                                <x-slot:prefix>
                                                    <div class="flex-1 min-w-0"></div>
                                                </x-slot:prefix>
                                            </x-qty-helper>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @if ($closing['countedItems'] > 0)
                                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-soft">
                                    <span>Lipsuri estimate: <span class="font-medium text-danger">{{ $money($closing['shortageValue']) }} lei</span></span>
                                    <span>Surplus estimat: <span class="font-medium text-warning">{{ $signedMoney($closing['surplusValue']) }} lei</span></span>
                                </div>
                                <p class="mt-1 text-[11px] text-ink-soft/70">La finalizare, diferențele se recalculează față de stocul real de atunci.</p>
                            @endif
                        @endif
                    </div>
                </div>
            </section>

            {{-- ===================== ACȚIUNI ===================== --}}
            <div
                x-data="{ finalizeOpen: false, confirmed: false }"
                class="flex flex-wrap items-center gap-3 pt-1"
            >
                <x-btn variant="primary" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Salvează draftul
                </x-btn>

                <x-btn variant="info" @click="finalizeOpen = true; confirmed = false">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Finalizează
                </x-btn>

                <a href="{{ route('admin.stock-reports.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">Înapoi la listă</a>

                @error('finalize') <p class="w-full text-sm text-danger">{{ $message }}</p> @enderror

                {{-- Dialog de finalizare (confirmare explicită, ireversibilă) --}}
                <div x-show="finalizeOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-ink/40" @click="finalizeOpen = false"></div>
                    <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-md w-full p-6">
                        <h3 class="text-base font-semibold text-ink">Finalizează raportarea</h3>
                        <p class="mt-2 text-sm text-ink-soft leading-relaxed">
                            La finalizare se creează mișcările reale de stoc: intrările cresc stocul (și recalculează CMP), vânzările și pierderile îl scad. <span class="font-medium text-ink">Acțiunea nu mai poate fi anulată</span> — raportarea nu va mai putea fi modificată sau ștearsă.
                        </p>
                        {{-- DXA: adaugat (Bar - numaratoare) — apare doar daca exista produse numarate --}}
                        @if ($closing['countedItems'] > 0)
                            <label class="mt-4 flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model="alignStock" class="mt-0.5 w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                                <span class="text-sm text-ink">
                                    Aliniez stocul la cantitățile numărate
                                    <span class="block text-xs text-ink-soft">Lipsurile se postează ca pierderi „Diferență la numărătoare" (intră în cost și profit), surplusul ca ajustare de stoc. Debifat: diferențele doar se salvează în raportare.</span>
                                </span>
                            </label>
                        @endif
                        <label class="mt-4 flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" x-model="confirmed" class="mt-0.5 w-4 h-4 rounded border-border accent-primary" style="accent-color: var(--color-primary);">
                            <span class="text-sm text-ink">Am înțeles — finalizez, fără posibilitate de modificare ulterioară.</span>
                        </label>
                        <div class="mt-6 flex items-center justify-end gap-3">
                            <button type="button" @click="finalizeOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                            <button type="button" :disabled="!confirmed"
                                    @click="$wire.set('finalizeConfirm', true).then(() => $wire.finalize()); finalizeOpen = false"
                                    class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-info hover:bg-info/90 disabled:opacity-40 disabled:cursor-not-allowed">
                                Finalizează
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            </div>{{-- /min-w-0 (coloană formular) --}}

            <div class="mt-5 lg:mt-0">
                <section class="lg:sticky lg:top-6">
                    <div class="rounded-2xl border border-border bg-surface">
                        <div class="px-4 py-3 border-b border-border">
                            <h3 class="text-sm font-semibold text-ink">Impact asupra stocului</h3>
                            <p class="text-xs text-ink-soft mt-0.5">Efectul cumulat al liniilor de mai sus, calculat live. Nu se scrie nimic până la finalizare.</p>
                        </div>

                        @if (empty($impact))
                            <p class="px-4 py-5 text-sm text-ink-soft/60 italic">Adaugă intrări, vânzări sau pierderi ca să vezi impactul.</p>
                        @else
                            {{-- Sub lg: aici e loc pe lățime, tabel complet cu antet de coloane --}}
                            <div class="lg:hidden">
                                <div class="hidden sm:grid grid-cols-[1fr_8rem_7rem_8rem] gap-3 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-ink-soft/60">
                                    <span>Produs</span>
                                    <span class="text-right">Stoc curent</span>
                                    <span class="text-right">Din raport</span>
                                    <span class="text-right">Stoc estimat</span>
                                </div>
                                <div class="divide-y divide-border/60">
                                    @foreach ($impact as $row)
                                        <div class="px-4 py-2 sm:grid sm:grid-cols-[1fr_8rem_7rem_8rem] sm:gap-3 sm:items-center flex flex-wrap justify-between gap-x-3 {{ $row['result'] < 0 ? 'bg-danger/5' : '' }}">
                                            <span class="text-sm text-ink min-w-0">{{ $row['name'] }}</span>
                                            <span class="text-sm text-ink-soft sm:text-right"><span class="sm:hidden text-[11px] uppercase text-ink-soft/50">Curent: </span>{{ $fmt($row['current']) }} {{ $row['unit'] }}</span>
                                            <span class="text-sm sm:text-right {{ $row['net'] < 0 ? 'text-danger' : 'text-success' }}">
                                                <span class="sm:hidden text-[11px] uppercase text-ink-soft/50">Δ: </span>{{ $row['net'] >= 0 ? '+' : '−' }}{{ $fmt(abs($row['net'])) }} {{ $row['unit'] }}
                                            </span>
                                            <div class="text-sm font-medium sm:text-right {{ $row['result'] < 0 ? 'text-danger' : 'text-ink' }}">
                                                <span class="sm:hidden text-[11px] uppercase text-ink-soft/50">Estimat: </span>{{ $fmt($row['result']) }} {{ $row['unit'] }}
                                                @if ($row['package'])
                                                    <span class="block text-[11px] font-normal text-ink-soft/70">{{ $row['package'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- lg+: coloană laterală îngustă — un rând compact per produs --}}
                            <div class="hidden lg:block divide-y divide-border/60">
                                @foreach ($impact as $row)
                                    <div class="px-4 py-2.5 {{ $row['result'] < 0 ? 'bg-danger/5' : '' }}">
                                        <span class="block text-sm text-ink truncate">{{ $row['name'] }}</span>
                                        <div class="mt-0.5 flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-xs text-ink-soft">
                                            <span>{{ $fmt($row['current']) }} {{ $row['unit'] }}</span>
                                            <span class="text-ink-soft/40">·</span>
                                            <span class="{{ $row['net'] < 0 ? 'text-danger' : 'text-success' }}">{{ $row['net'] >= 0 ? '+' : '−' }}{{ $fmt(abs($row['net'])) }}</span>
                                            <span class="text-ink-soft/40">·</span>
                                            <div class="font-medium {{ $row['result'] < 0 ? 'text-danger' : 'text-ink' }}">
                                                = {{ $fmt($row['result']) }} {{ $row['unit'] }}
                                                @if ($row['package'])
                                                    <span class="block text-[11px] font-normal text-ink-soft/70">{{ $row['package'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if (collect($impact)->contains(fn ($r) => $r['result'] < 0))
                                <div class="px-4 py-2.5 border-t border-border">
                                    <p class="text-xs text-danger">Unele produse ar ajunge pe stoc negativ (roșu). Poți finaliza oricum — e doar un avertisment.</p>
                                </div>
                            @endif
                        @endif
                    </div>
                </section>
            </div>

            </div>{{-- /grid --}}
        </div>
    @endif
</div>
