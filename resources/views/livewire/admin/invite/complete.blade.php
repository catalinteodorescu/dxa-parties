<div class="bg-surface border border-border rounded-2xl p-8 shadow-sm">

    <h1 class="text-xl font-semibold text-ink">Completează-ți contul</h1>
    <p class="mt-1 text-sm text-ink-soft">Setează-ți numele și o parolă ca să poți intra în panou.</p>

    <form wire:submit="save" class="mt-6 space-y-4">

        <div>
            <label for="name" class="block text-sm font-medium text-ink">Nume</label>
            <input type="text" id="name" wire:model="name" autofocus
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            @error('name') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-ink">Parolă</label>
            <input type="password" id="password" wire:model="password"
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            @error('password') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-ink">Confirmă parola</label>
            <input type="password" id="password_confirmation" wire:model="password_confirmation"
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium py-2.5 transition-colors">
            Activează contul
        </button>

    </form>

</div>
