@props([
    'label' => '',
    'href' => '#',
    'accent' => 'primary',   // primary | neutral (doar culoarea badge-ului)
])

@php
    $badges = [
        'primary' => 'bg-primary-soft text-primary',
        'neutral' => 'bg-bg text-ink-soft',
    ];
    $badge = $badges[$accent] ?? $badges['primary'];
@endphp

<a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => 'group relative flex items-center gap-2.5 h-full rounded-xl border border-border bg-surface p-3 hover:border-primary/50 hover:shadow-sm transition-all']) }}>
    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg shrink-0 {{ $badge }}">{{ $icon ?? '' }}</span>
    <span class="text-sm font-medium text-ink">{{ $label }}</span>
    <svg class="absolute top-2.5 right-2.5 w-3.5 h-3.5 text-ink-soft/40 opacity-0 group-hover:opacity-100 group-hover:text-primary transition-all"
         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>
    </svg>
</a>
