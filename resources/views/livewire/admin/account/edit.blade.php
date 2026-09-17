<div class="max-w-5xl grid grid-cols-1 md:grid-cols-2 gap-8">

    <div>
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-ink">Contul meu</h2>
            <p class="mt-1 text-sm text-ink-soft">Datele contului tău de administrator.</p>
        </div>

        @if ($profileUpdated)
            <div class="mb-4 rounded-lg bg-primary-soft text-primary text-sm px-4 py-3">
                Datele au fost actualizate cu succes.
            </div>
        @endif

        <form wire:submit="updateProfile" class="bg-surface border border-border rounded-2xl p-6 space-y-4">

            <div>
                <label for="name" class="block text-sm font-medium text-ink">Nume</label>
                <input type="text" id="name" wire:model="name"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('name') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-ink">Telefon (login)</label>
                <input type="text" id="phone" wire:model="phone" placeholder="07XXXXXXXX"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('phone') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">
                Salvează datele
            </button>

        </form>
    </div>

    <div>
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-ink">Schimbă parola</h2>
            <p class="mt-1 text-sm text-ink-soft">Ai nevoie de parola curentă.</p>
        </div>

        @if ($passwordUpdated)
            <div class="mb-4 rounded-lg bg-primary-soft text-primary text-sm px-4 py-3">
                Parola a fost schimbată cu succes.
            </div>
        @endif

        <form wire:submit="updatePassword" class="bg-surface border border-border rounded-2xl p-6 space-y-4">

            <div>
                <label for="current_password" class="block text-sm font-medium text-ink">Parola curentă</label>
                <input type="password" id="current_password" wire:model="current_password"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('current_password') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-ink">Parolă nouă</label>
                <input type="password" id="password" wire:model="password"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('password') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-ink">Confirmă parola nouă</label>
                <input type="password" id="password_confirmation" wire:model="password_confirmation"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            </div>

            <button type="submit"
                    class="rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">
                Schimbă parola
            </button>

        </form>
    </div>

</div>
