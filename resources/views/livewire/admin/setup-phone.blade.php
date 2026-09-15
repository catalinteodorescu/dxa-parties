<div class="bg-surface border border-border rounded-2xl p-8 shadow-sm">

    <h1 class="text-xl font-semibold text-ink">Setează numărul de telefon</h1>
    <p class="mt-1 text-sm text-ink-soft leading-relaxed">
        Contul tău a fost creat cu un login temporar. Introdu numărul tău real
        de telefon — de acum înainte te vei loga cu el.
    </p>

    <form wire:submit="save" class="mt-6 space-y-4">
        <div>
            <label for="phone" class="block text-sm font-medium text-ink">Telefon</label>
            <input
                type="text"
                id="phone"
                wire:model="phone"
                autofocus
                placeholder="07XXXXXXXX"
                class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"
            >
            @error('phone')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium py-2.5 transition-colors"
        >
            Salvează
        </button>
    </form>

</div>
