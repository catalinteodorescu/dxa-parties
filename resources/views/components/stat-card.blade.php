@props([
    'label' => '',
    'value' => '',
    'hint' => null,
    'href' => null,
    'accent' => 'primary',   // primary | info | warning | purple | danger | neutral
])

@php
    $accents = [
        'primary' => 'bg-primary-soft text-primary',
        'info'    => 'bg-info-soft text-info',
        'warning' => 'bg-warning/10 text-warning',
        'purple'  => 'bg-purple/10 text-purple',
        'danger'  => 'bg-danger/10 text-danger',
        'success' => 'bg-success-soft text-success',
        'neutral' => 'bg-bg text-ink-soft',
    ];
    $badge = $accents[$accent] ?? $accents['primary'];
    $base = 'group relative flex flex-col h-full rounded-xl border border-border bg-surface p-3'
        .($href ? ' hover:border-primary/50 hover:shadow-sm transition-all' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $base]) }}>
@else
    <div {{ $attributes->merge(['class' => $base]) }}>
@endif
        <div class="flex items-center gap-2">
            @isset($icon)
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg shrink-0 {{ $badge }}">{{ $icon }}</span>
            @endisset
            <span class="text-xl font-bold text-ink leading-none">{{ $value }}</span>
        </div>
        <div class="mt-2 text-xs font-medium text-ink truncate">{{ $label }}</div>
        <div class="mt-0.5 text-[11px] text-ink-soft/70 truncate">{{ $hint }}</div>

        @if ($href)
            <svg class="absolute top-2.5 right-2.5 w-3.5 h-3.5 text-ink-soft/40 opacity-0 group-hover:opacity-100 group-hover:text-primary transition-all"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>
            </svg>
        @endif
@if ($href)
    </a>
@else
    </div>
@endif
