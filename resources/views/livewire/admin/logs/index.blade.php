<div>
    @php
        $cols = 'md:grid-cols-[150px_minmax(0,1fr)_minmax(0,2fr)]';
    @endphp

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">Jurnal activitate</h2>
        <p class="mt-1 text-sm text-ink-soft">Acțiunile importante din panoul de administrare, cele mai recente primele.</p>
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
                    Niciun eveniment înregistrat încă.
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        {{ $logs->onEachSide(1)->links('pagination.dxa') }}
    </div>
</div>
