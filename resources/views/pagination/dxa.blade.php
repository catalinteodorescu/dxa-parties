@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginare" class="flex items-center justify-center gap-1 select-none">

        {{-- Precedenta --}}
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-border text-ink-soft/30 cursor-default">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </span>
        @else
            <button type="button" wire:key="pg-prev" wire:click="previousPage('{{ $paginator->getPageName() }}')" rel="prev"
                    class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-border text-ink-soft hover:bg-bg transition-colors" aria-label="Pagina precedentă">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
        @endif

        {{-- Numere --}}
        <div class="flex items-center gap-1 overflow-x-auto max-w-full">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex items-center justify-center h-9 min-w-9 px-1 text-sm text-ink-soft/50">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex items-center justify-center h-9 min-w-9 px-2.5 rounded-lg bg-primary text-white text-sm font-semibold">{{ $page }}</span>
                        @else
                            <button type="button" wire:key="pg-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                    class="inline-flex items-center justify-center h-9 min-w-9 px-2.5 rounded-lg border border-border text-ink-soft hover:bg-bg text-sm transition-colors">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        {{-- Urmatoarea --}}
        @if ($paginator->hasMorePages())
            <button type="button" wire:key="pg-next" wire:click="nextPage('{{ $paginator->getPageName() }}')" rel="next"
                    class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-border text-ink-soft hover:bg-bg transition-colors" aria-label="Pagina următoare">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        @else
            <span aria-disabled="true" class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-border text-ink-soft/30 cursor-default">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
        @endif

    </nav>
@endif
