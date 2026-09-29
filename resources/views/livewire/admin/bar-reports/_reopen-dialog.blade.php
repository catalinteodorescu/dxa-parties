@if ($confirmingReopenId)
    <div class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/50" wire:click="cancelReopen"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-surface border border-border shadow-lg p-5">
            <h3 class="text-base font-semibold text-ink">Redeschizi raportarea?</h3>
            <p class="mt-1 text-sm text-ink-soft">Barul va putea din nou să vândă și să anuleze în această sesiune, iar barmanul va trebui să retrimită raportarea.</p>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <x-btn variant="neutral" wire:click="cancelReopen">Renunț</x-btn>
                <x-btn variant="primary" wire:click="reopenReport" wire:loading.attr="disabled" wire:target="reopenReport">Redeschide</x-btn>
            </div>
        </div>
    </div>
@endif
