{{-- DXA: adaugat (PWA Recepție - Etapa 3). Sugestii: participanții din ultima intrare, un tap. Variabilă: $quickPicks. --}}
@if ($quickPicks->isNotEmpty())
    <div class="flex flex-wrap items-center gap-1.5">
        <span class="text-xs text-ink-soft">Cumpără:</span>
        @foreach ($quickPicks as $qp)
            <button type="button" wire:key="quick-{{ $qp->id }}" wire:click="pickQuickParticipant({{ $qp->id }})"
                    class="inline-flex items-center rounded-full border border-primary/30 bg-primary-soft text-primary text-sm font-medium px-3 py-1.5 hover:bg-primary/20">
                {{ $qp->name }}
            </button>
        @endforeach
    </div>
@endif
