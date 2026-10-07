@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $signed = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs((float) $n), 2, ',', '.');
    $hasFilters = $status !== 'all' || $party !== '';
    $partyOptions = ['' => 'Toate petrecerile'] + $parties->mapWithKeys(fn ($p) => [$p->id => $p->name])->all();
@endphp
<div class="max-w-5xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">Bar · Raportări casă</h2>
        <p class="mt-1 text-sm text-ink-soft">Raportările trimise de barmani din aplicație: cash și tokeni numărați față de cei așteptați. Închiderea sesiunii rămâne la Raportările de stoc.</p>
    </div>

    @if ($flashMessage)<x-alert type="success" class="mb-4">{{ $flashMessage }}</x-alert>@endif
    @if ($flashError)<x-alert type="error" class="mb-4">{{ $flashError }}</x-alert>@endif

    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <x-select wire:model="status" live class="sm:w-48" :options="['all' => 'Toate stările'] + $statuses" />
        <x-select wire:model="party" live class="sm:w-52" :options="$partyOptions" />
        @if ($hasFilters)
            <button type="button" wire:click="clearFilters" class="text-sm text-ink-soft hover:text-ink px-2 py-2 self-start">Resetează</button>
        @endif
    </div>

    <div class="space-y-2.5">
        @forelse ($reports as $r)
            @php
                $fig = $r->figures();
                $open = $r->group?->isOpen() ?? false;
                $awaiting = $r->isSubmitted() && $open;
                $diff = $fig->cash_diff;
            @endphp
            <div wire:key="br-{{ $r->id }}" class="rounded-2xl border {{ $awaiting ? 'border-warning/50' : 'border-border' }} bg-surface px-4 py-3 md:flex md:items-center md:justify-between md:gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('admin.bar.reports.show', $r) }}" wire:navigate class="font-medium text-ink hover:text-primary">{{ $r->title() }}</a>
                        @if ($awaiting)
                            <span class="inline-flex rounded-full bg-warning/10 text-warning text-[11px] font-medium px-2 py-0.5">Așteaptă adminul</span>
                        @elseif ($r->isSubmitted())
                            <span class="inline-flex rounded-full bg-success-soft text-success text-[11px] font-medium px-2 py-0.5">Trimisă · sesiune închisă</span>
                        @elseif (! $open)
                            <span class="inline-flex rounded-full bg-bg text-ink-soft text-[11px] font-medium px-2 py-0.5">Sesiune închisă</span>
                        @else
                            <span class="inline-flex rounded-full bg-bg text-ink-soft text-[11px] font-medium px-2 py-0.5">În lucru</span>
                        @endif
                    </div>
                    <div class="mt-0.5 text-xs text-ink-soft">
                        {{ $r->party?->name ?? '—' }}
                        @if ($r->submitted_at) · trimisă {{ $r->submitted_at->format('d.m.Y H:i') }}@if ($r->submitter) de {{ $r->submitter->name }}@endif @endif
                    </div>
                    <div class="mt-1 text-xs text-ink-soft">
                        Așteptat {{ $money($fig->expected_cash) }} lei
                        · numărat {{ $fig->counted_cash === null ? '—' : $money($fig->counted_cash).' lei' }}
                        @if ($diff !== null)· <span class="{{ abs($diff) < 0.005 ? 'text-success' : 'text-warning' }}">{{ abs($diff) < 0.005 ? 'casă corectă' : $signed($diff).' lei' }}</span>@endif
                        @if ($fig->tokens_diff !== null)· tokeni <span class="{{ $fig->tokens_diff === 0 ? 'text-success' : 'text-warning' }}">{{ $fig->tokens_diff === 0 ? 'corecți' : ($fig->tokens_diff > 0 ? '+' : '−').abs($fig->tokens_diff) }}</span>@endif
                    </div>
                </div>
                <div class="mt-3 md:mt-0 shrink-0 flex items-center gap-2">
                    <x-btn variant="neutral" size="sm" :href="route('admin.bar.reports.show', $r)" wire:navigate>Detaliu</x-btn>
                    @if ($r->isSubmitted() || ! $open)
                        <x-btn variant="neutral" size="sm" :href="route('admin.bar.reports.pdf', $r)">PDF</x-btn>
                    @endif
                    @if ($awaiting)
                        @permitsAction('reopen_bar_reports')
                        <x-btn variant="warning" size="sm" outline wire:click="askReopen({{ $r->id }})">Redeschide</x-btn>
                        @endpermitsAction
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">Nicio raportare de bar {{ $hasFilters ? 'pentru filtrele alese' : 'încă' }}.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $reports->links() }}</div>

    @include('livewire.admin.bar-reports._reopen-dialog')
</div>
