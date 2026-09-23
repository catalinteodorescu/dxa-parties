{{-- Vânzări înregistrate (sesiune sau vânzări simple), doar pentru citire. Anulările nu apar aici: se văd în pagina Vânzări.
     Variabile: $data (vezi Form::salesSourceData), $fmt, $money, $methodLabels --}}
<div class="mt-3 space-y-2">
    @forelse ($data['lines'] as $g)
        <div wire:key="reg-{{ $loop->index }}-{{ $g->menu_item_id }}" class="rounded-lg border border-border bg-surface px-3 py-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
            <span class="text-sm text-ink min-w-0">{{ $g->menuItem->name }}
                @unless ($g->menuItem->isTracked())
                    <span class="text-xs text-warning">· fără rețetă, doar venit</span>
                @endunless
            </span>
            <span class="text-sm text-ink-soft shrink-0">
                <span class="text-ink">{{ $fmt($g->qty) }} ×</span>
                <span class="text-ink-soft/50">=</span> <span class="text-ink font-medium">{{ $money($g->revenue) }} lei</span>
            </span>
        </div>
    @empty
        <p class="text-sm text-ink-soft/60 italic">Nicio vânzare finalizată de adus.</p>
    @endforelse
</div>

<div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-soft">
    <span><span class="font-medium text-ink">{{ $data['completed'] }}</span> {{ $data['completed'] === 1 ? 'vânzare' : 'vânzări' }}</span>
    <span class="text-ink font-medium">{{ $money($data['revenue']) }} lei</span>
    @foreach ($data['payments'] as $method => $p)
        <span class="inline-flex items-center rounded-full bg-white border border-border px-2 py-0.5">
            {{ $methodLabels[$method] ?? $method }}:
            @if ($method === 'token') {{ $p['tokens'] }} tk · @endif
            {{ $money($p['amount']) }} lei
        </span>
    @endforeach
</div>
