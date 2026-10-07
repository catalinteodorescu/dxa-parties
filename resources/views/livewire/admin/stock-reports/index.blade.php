<div
    x-data="{
        confirmOpen: false,
        confirmTitle: '',
        confirmMessage: '',
        confirmMethod: '',
        confirmArgs: [],
        askConfirm(title, message, method, args) {
            this.confirmTitle = title;
            this.confirmMessage = message;
            this.confirmMethod = method;
            this.confirmArgs = args;
            this.confirmOpen = true;
        },
        runConfirm() {
            this.$wire.call(this.confirmMethod, ...this.confirmArgs);
            this.confirmOpen = false;
        },
    }"
>
    @php
        $hasFilters = $status !== 'all' || $party !== '' || $dateFrom !== '' || $dateTo !== '';
        $money = fn ($n) => number_format((float) $n, 2, ',', '.');
        $partyOptions = ['' => 'Toate petrecerile'] + $parties->mapWithKeys(fn ($p) => [$p->id => $p->name])->all();
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-ink">Bar — Raportări</h2>
            <p class="mt-1 text-sm text-ink-soft">Intrări, vânzări și pierderi. Cât e draft nu se atinge stocul; la finalizare se creează mișcările reale.</p>
        </div>
        @permits('stock_reports', 'edit')
        <x-btn variant="primary" :href="route('admin.stock-reports.create')" wire:navigate class="self-start">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Raportare nouă
        </x-btn>
        @endpermits
    </div>

    {{-- DXA: adaugat — Sesiuni deschise (ca la Vanzari), cu "Adu in raportare" per sesiune --}}
    <div class="rounded-2xl border border-border bg-surface p-5 mb-5">
        <h3 class="text-sm font-semibold text-ink">Sesiuni deschise</h3>

        @forelse ($openSessions as $s)
            <div wire:key="open-session-{{ $s->id }}" class="mt-3 rounded-xl border border-border px-4 py-3 md:flex md:items-center md:justify-between md:gap-4">
                <div class="min-w-0">
                    <span class="block font-medium text-ink truncate">{{ $s->party?->name ?? 'Fără petrecere' }}</span>
                    <span class="block text-xs text-ink-soft">
                        Sesiune {{ $s->session_number }} · deschisă din {{ $s->created_at->format('d.m.Y H:i') }}
                        <span class="text-ink-soft/40">·</span> {{ $s->sales_count }} {{ $s->sales_count === 1 ? 'vânzare' : 'vânzări' }}
                        <span class="text-ink-soft/40">·</span> {{ $money($s->sales_revenue ?? 0) }} lei
                    </span>
                </div>
                <div class="mt-3 md:mt-0 shrink-0">
                    @if ($openDraft && (int) $openDraft->sales_group_id === $s->id)
                        @permits('stock_reports', 'edit')
                        <x-btn variant="warning" size="sm" :href="route('admin.stock-reports.edit', $openDraft)" wire:navigate>Continuă raportarea</x-btn>
                        @endpermits
                    @else
                        @permits('stock_reports', 'edit')
                        <x-btn variant="primary" size="sm" outline :href="route('admin.stock-reports.create', array_filter(['party' => $s->party_id, 'sales_group' => $s->id]))" wire:navigate>Adu în raportare</x-btn>
                        @endpermits
                    @endif
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-ink-soft">Nicio sesiune deschisă. Se deschide singură la prima vânzare a unei petreceri.</p>
        @endforelse
    </div>

    {{-- Filtre — sub md ascunse în toggle (auto-deschise dacă active); de la md mereu vizibile. --}}
    <div x-data="{ filtersOpen: {{ $hasFilters ? 'true' : 'false' }} }" class="mb-4">
        <button type="button" @click="filtersOpen = !filtersOpen"
                class="md:hidden inline-flex items-center gap-1.5 text-sm font-medium text-ink-soft hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/></svg>
            Filtre
            @if ($hasFilters)<span class="inline-flex items-center rounded-full bg-primary-soft text-primary text-[10px] font-semibold px-1.5 py-0.5">active</span>@endif
            <svg class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div class="hidden md:block mt-2 md:mt-0" :class="filtersOpen ? 'max-md:block' : ''">
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <x-select wire:model="status" live class="sm:w-44" :options="['all' => 'Toate stările'] + $statuses" />
                <x-select wire:model="party" live class="sm:w-52" :options="$partyOptions" />
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

    <x-flash class="mb-4" />

    <div class="hidden md:grid grid-cols-[1fr_16rem_7rem_10rem] gap-3 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-ink-soft/70">
        <span>Raportare</span>
        <span>Rezumat</span>
        <span>Stare</span>
        <span class="text-right">Acțiuni</span>
    </div>

    <div class="space-y-3">
        @forelse ($reports as $report)
            <div wire:key="report-{{ $report->id }}" class="rounded-2xl border border-border bg-surface">
                <div class="p-3 md:px-4 md:py-2.5 md:grid md:grid-cols-[1fr_16rem_7rem_10rem] md:items-center md:gap-3">

                    {{-- Raportare (dată + petrecere) --}}
                    <div class="min-w-0">
                        <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Raportare</span>
                        <span class="block font-medium text-ink">Nr. {{ $report->number() }}</span>
                        <span class="block text-xs text-ink-soft/70 truncate">
                            @if ($report->party){{ $report->party->name }}@else fără petrecere @endif
                            @if ($report->creator) <span class="text-ink-soft/40">·</span> {{ $report->creator->name }} @endif
                        </span>
                    </div>

                    {{-- Rezumat --}}
                    <div class="mt-2 md:mt-0">
                        <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Rezumat</span>
                        @if ($report->isFinalized())
                            @php $profit = $report->totalProfit(); @endphp
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm">
                                <span class="text-ink">{{ $money($report->totalRevenue()) }} <span class="text-ink-soft/60 text-xs">venit</span></span>
                                <span class="{{ $profit > 0 ? 'text-success' : ($profit < 0 ? 'text-danger' : 'text-ink') }}">{{ $money($profit) }} <span class="text-ink-soft/60 text-xs">profit</span></span>
                                {{-- DXA: adaugat (Bar - numaratoare de final de seara) --}}
                                @php $countSummary = $report->closingSummary(); @endphp
                                @if ($countSummary)
                                    <span class="inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $countSummary->clean ? 'bg-success-soft text-success' : 'bg-warning/10 text-warning' }}">{{ $countSummary->clean ? 'inventar ok' : 'diferențe la inventar' }}</span>
                                @endif
                            </div>
                        @else
                            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                <span class="inline-flex items-center rounded-full bg-success-soft text-success px-2 py-0.5">{{ $report->entry_count }} intrări</span>
                                <span class="inline-flex items-center rounded-full bg-info-soft text-info px-2 py-0.5">{{ $report->sale_count }} vânzări</span>
                                <span class="inline-flex items-center rounded-full bg-danger/10 text-danger px-2 py-0.5">{{ $report->loss_count }} pierderi</span>
                            </div>
                        @endif
                    </div>

                    {{-- Stare --}}
                    <div class="mt-2 md:mt-0">
                        <span class="md:hidden block text-[11px] uppercase tracking-wide text-ink-soft/60">Stare</span>
                        @if ($report->isFinalized())
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-info-soft text-info">Finalizat</span>
                        @else
                            <span class="inline-flex items-center rounded-full text-xs font-medium px-2 py-0.5 bg-primary-soft text-primary">Draft</span>
                        @endif
                    </div>

                    {{-- Acțiuni --}}
                    <div class="mt-3 md:mt-0 flex items-center gap-2 md:justify-end">
                        @if ($report->isFinalized())
                            <x-btn variant="info" size="icon" outline tooltip="Vezi detalii" :href="route('admin.stock-reports.edit', $report)" wire:navigate>
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            </x-btn>
                            @permitsAction('export_stock_reports')
                            <x-btn variant="neutral" size="icon" outline tooltip="Export PDF" wire:click="exportPdf({{ $report->id }})" wire:loading.attr="disabled" wire:target="exportPdf({{ $report->id }})">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/></svg>
                            </x-btn>
                            @endpermitsAction
                        @else
                            @permits('stock_reports', 'edit')
                            <x-btn variant="warning" size="icon" outline tooltip="Continuă editarea" :href="route('admin.stock-reports.edit', $report)" wire:navigate>
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                            </x-btn>
                            @endpermits
                            @permits('stock_reports', 'delete')
                            <x-btn variant="danger" size="icon" outline tooltip="Șterge draftul"
                                   x-on:click="askConfirm('Șterge draftul', 'Sigur vrei să ștergi draftul nr. {{ $report->number() }}? Nu a atins stocul, deci nu se pierde nimic real. Acțiunea nu poate fi anulată.', 'delete', [{{ $report->id }}])">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </x-btn>
                            @endpermits
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-ink-soft">
                @if ($hasFilters)
                    Nicio raportare care să corespundă filtrelor.
                @else
                    Nicio raportare încă. Apasă „Raportare nouă" ca să creezi prima.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $reports->onEachSide(1)->links('pagination.dxa') }}
    </div>

    {{-- Modal de confirmare (ștergere draft) --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink" x-text="confirmTitle"></h3>
            <p class="mt-2 text-sm text-ink-soft" x-text="confirmMessage"></p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="confirmOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <button type="button" @click="runConfirm()" class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-danger hover:bg-danger/90">Șterge</button>
            </div>
        </div>
    </div>
</div>
