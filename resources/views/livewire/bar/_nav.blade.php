{{-- DXA: adaugat (PWA Bar). Butonul cu iconiță din antetul ecranelor: Acasă (căsuța). Variabilă: $current = sale | recent | report. --}}
<div class="shrink-0 flex items-center gap-2">
    <a href="{{ route('bar.home') }}" wire:navigate aria-label="Acasă" title="Acasă" class="rounded-lg p-2 bg-primary-soft text-primary hover:bg-primary/20 transition-colors">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
    </a>
</div>
