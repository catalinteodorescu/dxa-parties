@php
    $stateLabel = ['live' => 'În desfășurare', 'upcoming' => 'Urmează'];
@endphp
<div class="space-y-4">
    <div>
        <h1 class="text-xl font-semibold text-ink">Alege petrecerea</h1>
        <p class="mt-1 text-sm text-ink-soft">La ce petrecere lucrezi acum? O poți schimba oricând din Acasă.</p>
    </div>

    <x-flash />

    @forelse ($parties as $p)
        <button type="button" wire:key="party-{{ $p->id }}" wire:click="choose({{ $p->id }})"
                class="w-full text-left rounded-2xl border px-4 py-4 transition-colors {{ $currentId === $p->id ? 'border-primary bg-primary-soft' : 'border-border bg-surface hover:bg-bg' }}">
            <div class="flex items-start justify-between gap-3">
                <span class="text-base font-semibold text-ink">{{ $p->name }}</span>
                <span class="shrink-0 inline-flex items-center rounded-full text-[11px] font-medium px-2 py-0.5 {{ $p->state() === 'live' ? 'bg-success-soft text-success' : 'bg-info-soft text-info' }}">
                    {{ $stateLabel[$p->state()] ?? $p->state() }}
                </span>
            </div>
            <div class="mt-1 text-sm text-ink-soft">
                {{ $p->starts_at?->format('d.m.Y H:i') }}@if ($p->ends_at) – {{ $p->ends_at->format('H:i') }}@endif
                @if ($p->location_name) · {{ $p->location_name }}@endif
            </div>
        </button>
    @empty
        <div class="rounded-2xl border border-border bg-surface p-5 text-sm text-ink-soft">
            Nu există nicio petrecere la care să lucrezi acum (publicată, activă și neîncheiată).
        </div>
    @endforelse
</div>
