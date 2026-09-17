@props([
    'variant' => 'primary',  // primary | danger | warning | info | purple | neutral
    'size' => 'md',          // md | sm | icon
    'href' => null,
    'tooltip' => null,
    'outline' => false,      // alb cu border+icon colorat; la hover se umple, icon alb
])

@php
    $solid = [
        'primary' => 'bg-primary hover:bg-primary-hover text-white',
        'danger'  => 'bg-danger hover:bg-danger/90 text-white',
        'warning' => 'bg-warning hover:bg-warning/90 text-white',
        'info'    => 'bg-info hover:bg-info/90 text-white',
        'purple'  => 'bg-purple hover:bg-purple/90 text-white',
        'neutral' => 'bg-surface border border-border text-ink-soft hover:bg-bg',
    ];

    $outlineVariants = [
        'primary' => 'bg-surface border border-primary text-primary hover:bg-primary hover:text-white',
        'danger'  => 'bg-surface border border-danger text-danger hover:bg-danger hover:text-white',
        'warning' => 'bg-surface border border-warning text-warning hover:bg-warning hover:text-white',
        'info'    => 'bg-surface border border-info text-info hover:bg-info hover:text-white',
        'purple'  => 'bg-surface border border-purple text-purple hover:bg-purple hover:text-white',
        'neutral' => 'bg-surface border border-border text-ink-soft hover:bg-ink-soft hover:text-white',
    ];

    $palette = $outline ? $outlineVariants : $solid;

    $sizes = [
        'md'   => 'h-9 px-3.5 text-sm gap-1.5',
        'sm'   => 'h-8 px-3 text-xs gap-1',
        'icon' => 'h-7 w-7',
    ];

    $classes = 'inline-flex items-center justify-center rounded-lg font-medium transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed '
        .($palette[$variant] ?? $palette['primary']).' '
        .($sizes[$size] ?? $sizes['md']);
@endphp

@if ($tooltip)
    <span class="relative inline-flex" x-data="{ tip: false }"
          @mouseenter="tip = true" @mouseleave="tip = false"
          @focusin="tip = true" @focusout="tip = false">
        @if ($href)
            <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
        @else
            <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
        @endif

        <span x-show="tip" x-cloak x-transition.opacity
              class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-50 whitespace-nowrap rounded-md bg-ink text-white text-xs px-2 py-1 shadow-lg">
            {{ $tooltip }}
            <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-ink"></span>
        </span>
    </span>
@else
    @if ($href)
        <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
    @else
        <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
    @endif
@endif
