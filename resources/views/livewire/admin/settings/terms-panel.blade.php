<div class="bg-surface border border-border rounded-2xl p-6" x-data="{ confirmOpen: false }" data-terms-panel>
    <h3 class="text-sm font-semibold text-ink">Termeni și condiții</h3>
    <p class="mt-1 text-sm text-ink-soft leading-relaxed">
        Textul din aplicația participanților (pagina „Termeni și condiții”). Cât timp nu publici nimic, aplicația nu cere și nu arată nimic.
        Paragrafele se despart printr-un rând gol, iar o linie care începe cu <code>#</code> și spațiu devine titlu.
    </p>

    <div class="mt-4 space-y-3">
        @if ($message)
            <x-alert type="success" dismiss-prop="message" :dismiss-value="null">{{ $message }}</x-alert>
        @endif
        @if ($error)
            <x-alert type="error" dismiss-prop="error" :dismiss-value="null">{{ $error }}</x-alert>
        @endif
    </div>

    @if (! $current)
        <p class="mt-4 text-xs text-ink-soft">Nu e publicat niciun text. Mai jos e un text de probă, nepublicat: modifică-l și apasă „Publică”.</p>
    @else
        <p class="mt-4 text-xs text-ink-soft" data-terms-status>
            Versiunea publicată: {{ $current->id }} ({{ $current->created_at->format('d.m.Y H:i') }}).
            @if ($acceptance) Au acceptat {{ $acceptance['accepted'] }} din {{ $acceptance['total'] }} conturi. @endif
        </p>
    @endif

    <textarea wire:model="body" rows="14" maxlength="{{ \App\Services\Terms::MAX_LENGTH }}" data-terms-body
              class="mt-3 w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>

    @if ($current)
        <div class="mt-3">
            <x-checkbox wire:model="requireAll" data-terms-require>Cere acceptare de la toți participanții (altfel e o modificare minoră)</x-checkbox>
        </div>
    @endif

    <div class="mt-4">
        <x-btn variant="primary" x-on:click="confirmOpen = true" data-terms-publish>Publică</x-btn>
    </div>

    @if ($history->isNotEmpty())
        <div class="mt-5 border-t border-border pt-3 space-y-1" data-terms-history>
            <div class="text-xs font-medium text-ink-soft">Versiuni</div>
            @foreach ($history as $v)
                <div class="text-xs text-ink-soft">v{{ $v->id }} · {{ $v->created_at->format('d.m.Y H:i') }} · {{ $v->publisher?->name ?? 'Sistem' }} · {{ $v->requires_reaccept ? 'cere acceptare' : 'modificare minoră' }}</div>
            @endforeach
        </div>
    @endif

    {{-- Confirmare publicare --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Publici această versiune?</h3>
            <p class="mt-2 text-sm text-ink-soft leading-relaxed">Textul devine vizibil în aplicație și nu mai poate fi modificat; o schimbare ulterioară înseamnă o versiune nouă.</p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="confirmOpen = false" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn variant="primary" x-on:click="$wire.publish(); confirmOpen = false" data-terms-confirm>Publică</x-btn>
            </div>
        </div>
    </div>
</div>
