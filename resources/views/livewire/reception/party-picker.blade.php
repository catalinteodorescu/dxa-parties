@php
    $stateLabel = ['live' => 'În desfășurare', 'upcoming' => 'Urmează', 'past' => 'Încheiată · casa deschisă'];
    $stateClass = ['live' => 'bg-success-soft text-success', 'upcoming' => 'bg-info-soft text-info', 'past' => 'bg-bg text-ink-soft'];
@endphp
<div class="space-y-4">
    <div>
        <h1 class="text-xl font-semibold text-white">Alege petrecerea</h1>
    </div>

    <x-flash />

    @forelse ($parties as $p)
        @php $sel = $currentId === $p->id; @endphp
        <button type="button" wire:key="party-{{ $p->id }}" wire:click="choose({{ $p->id }})"
                class="w-full text-left rounded-2xl border-2 px-4 py-4 shadow-sm transition-colors {{ $sel ? 'border-white bg-primary-dark text-white' : 'border-transparent bg-surface hover:bg-bg' }}">
            <div class="flex items-start justify-between gap-3">
                <span class="flex items-center gap-2 text-base font-semibold {{ $sel ? 'text-white' : 'text-ink' }}">
                    @if ($sel)
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-label="Selectată"><path d="M20 6 9 17l-5-5"/></svg>
                    @endif
                    {{ $p->name }}
                </span>
                <span class="shrink-0 inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $stateClass[$p->state()] ?? 'bg-bg text-ink-soft' }}">
                    {{ $stateLabel[$p->state()] ?? $p->state() }}
                </span>
            </div>
            <div class="mt-1 text-sm {{ $sel ? 'text-white/80' : 'text-ink-soft' }}">
                {{ $p->starts_at?->format('d.m.Y H:i') }}@if ($p->ends_at) – {{ $p->ends_at->format('H:i') }}@endif
                @if ($p->location_name) · {{ $p->location_name }}@endif
            </div>
        </button>
    @empty
        <div class="rounded-2xl bg-surface p-5 text-sm text-ink-soft">
            Nu există nicio petrecere la care să lucrezi acum (publicată, activă și neîncheiată).
        </div>
    @endforelse
</div>
