<div class="bg-surface border border-border rounded-2xl p-8 shadow-sm">

    <h1 class="text-xl font-semibold text-ink">Am uitat parola</h1>
    <p class="mt-1 text-sm text-ink-soft">Introdu numărul de telefon cu care te loghezi.</p>

    @if ($sent)
        <x-alert type="success" :dismissible="false" class="mt-6">
            Dacă acest număr este înregistrat, vei primi un SMS cu instrucțiuni de resetare.
        </x-alert>
    @else
        <form wire:submit="send" class="mt-6 space-y-4">
            <div>
                <label for="phone" class="block text-sm font-medium text-ink">Telefon</label>
                <input type="text" id="phone" wire:model="phone" autofocus placeholder="07XXXXXXXX"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('phone') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium py-2.5 transition-colors">
                Trimite link de resetare
            </button>
        </form>
    @endif

    <p class="mt-6 text-center text-sm text-ink-soft">
        <a href="{{ route('admin.login') }}" wire:navigate class="text-primary hover:text-primary-hover font-medium">
            Înapoi la login
        </a>
    </p>

</div>
