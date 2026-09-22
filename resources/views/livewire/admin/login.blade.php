<div class="bg-surface border border-border rounded-2xl p-8 shadow-sm">

    <h1 class="text-xl font-semibold text-ink">Autentificare admin</h1>
    <p class="mt-1 text-sm text-ink-soft">
        @if ($isBootstrapLogin)
            Prima autentificare — folosește login-ul temporar.
        @else
            Intră cu contul tău de administrator.
        @endif
    </p>

    <x-flash class="mt-4" />

    <form wire:submit="login" class="mt-6 space-y-4">

        <div>
            @if ($isBootstrapLogin)
                <label for="phone" class="block text-sm font-medium text-ink">Utilizator</label>
                <input
                    type="text"
                    id="phone"
                    wire:model="phone"
                    autofocus
                    class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"
                    placeholder="admin"
                >
            @else
                <label for="phone" class="block text-sm font-medium text-ink">Telefon</label>
                <input
                    type="text"
                    id="phone"
                    wire:model="phone"
                    autofocus
                    autocomplete="tel"
                    class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"
                    placeholder="07XXXXXXXX"
                >
            @endif
            @error('phone')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-ink">Parolă</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autocomplete="current-password"
                class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"
                placeholder="••••••••"
            >
            @error('password')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" wire:model="remember" class="rounded border-border text-primary focus:ring-primary/40">
                Ține-mă minte
            </label>

            @unless ($isBootstrapLogin)
                <a href="{{ route('admin.forgot-password') }}" wire:navigate class="text-sm text-primary hover:text-primary-hover font-medium">
                    Am uitat parola
                </a>
            @endunless
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="login"
            class="w-full rounded-lg bg-primary hover:bg-primary-hover disabled:opacity-70 text-white text-sm font-medium py-2.5 transition-colors"
        >
            <span wire:loading.remove wire:target="login">Intră în cont</span>
            <span wire:loading wire:target="login">Se verifică...</span>
        </button>

    </form>

</div>
