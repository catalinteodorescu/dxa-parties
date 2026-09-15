<div class="bg-surface border border-border rounded-2xl p-8 shadow-sm">

    <h1 class="text-xl font-semibold text-ink">Setează o parolă nouă</h1>
    <p class="mt-1 text-sm text-ink-soft">Alege o parolă nouă pentru contul tău.</p>

    <form wire:submit="save" class="mt-6 space-y-4">

        <div>
            <label for="password" class="block text-sm font-medium text-ink">Parolă nouă</label>
            <input type="password" id="password" wire:model="password" autofocus
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            @error('password') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-ink">Confirmă parola nouă</label>
            <input type="password" id="password_confirmation" wire:model="password_confirmation"
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium py-2.5 transition-colors">
            Schimbă parola
        </button>

    </form>

</div>
