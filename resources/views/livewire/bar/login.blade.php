<div class="bg-surface text-ink border border-border rounded-2xl p-6 shadow-lg">
    <x-flash class="mb-4" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="phone" class="block text-sm font-medium text-ink">Telefon</label>
            <input type="tel" id="phone" wire:model="phone" autocomplete="username" inputmode="tel" autofocus placeholder="07XXXXXXXX"
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-3 text-base text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            @error('phone') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-ink">Parolă</label>
            <input type="password" id="password" wire:model="password" autocomplete="current-password"
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-3 text-base text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            @error('password') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <x-checkbox wire:model="remember" class="flex">Ține-mă conectat</x-checkbox>

        <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-primary hover:bg-primary-hover text-white text-base font-medium px-4 py-3 transition-colors disabled:opacity-60">
            Intră
        </button>
    </form>
</div>
