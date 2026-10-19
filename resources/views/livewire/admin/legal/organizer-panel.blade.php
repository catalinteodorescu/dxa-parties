<div class="bg-surface border border-border rounded-2xl p-6" data-organizer-panel>
    <h3 class="text-sm font-semibold text-ink">Date organizator</h3>
    <p class="mt-1 text-sm text-ink-soft leading-relaxed">
        Organizatorul este operatorul datelor participanților. Aceste date completează automat Termenii și Politica de confidențialitate de mai jos.
        Nu poți publica un text în care au rămas câmpuri necompletate.
    </p>

    @if ($message)
        <div class="mt-3"><x-alert type="success" dismiss-prop="message" :dismiss-value="null">{{ $message }}</x-alert></div>
    @endif

    @if ($missing)
        <p class="mt-3 text-xs text-warning" data-organizer-missing>Încă lipsesc: {{ implode(', ', $missing) }}.</p>
    @endif

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        @foreach ($fields as $key => $label)
            <div class="{{ $key === 'organizer_name' || $key === 'organizer_address' ? 'sm:col-span-2' : '' }}">
                <label class="block text-xs font-medium text-ink-soft mb-1" for="org-{{ $key }}">{{ $label }}</label>
                <input type="text" id="org-{{ $key }}" wire:model="org.{{ $key }}" data-org="{{ $key }}"
                       class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40">
                @error("org.$key") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>

    @permits('legal', 'edit')
        <div class="mt-4">
            <x-btn variant="primary" wire:click="save" data-organizer-save>Salvează datele</x-btn>
        </div>
    @endpermits
</div>
