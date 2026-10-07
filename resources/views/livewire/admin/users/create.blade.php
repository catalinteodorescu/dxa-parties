<div class="max-w-xl">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">Adaugă utilizator</h2>
        <p class="mt-1 text-sm text-ink-soft leading-relaxed">
            Introdu numărul de telefon și alege aplicațiile în care are acces. Noul utilizator va primi un SMS cu un
            link prin care își completează numele și își setează parola. Permisiunile se setează de la început, ca să le aibă imediat ce își activează contul.
        </p>
    </div>

    <form wire:submit="save" class="bg-surface border border-border rounded-2xl p-6 space-y-4">

        <div>
            <label for="phone" class="block text-sm font-medium text-ink">Telefon</label>
            <input type="text" id="phone" wire:model="phone" autofocus placeholder="07XXXXXXXX"
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            @error('phone') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="block text-sm font-medium text-ink">Acces</span>
            <div class="mt-2 space-y-2">
                @foreach (['accessAdmin' => \App\Models\Admin::APP_ADMIN, 'accessBar' => \App\Models\Admin::APP_BAR, 'accessReception' => \App\Models\Admin::APP_RECEPTION] as $field => $app)
                    <x-checkbox wire:model="{{ $field }}" class="flex">{{ \App\Models\Admin::APP_LABELS[$app] }}</x-checkbox>
                @endforeach
            </div>
            @error('accessAdmin') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        @if ($isSuper)
            <div class="border-t border-border pt-4">
                @include('livewire.admin.users._permissions')
            </div>
        @else
            <p class="text-xs text-ink-soft border-t border-border pt-4">Permisiunile în panou le setează un superadmin. Contul nou va porni fără nicio permisiune.</p>
        @endif

        <div class="flex items-center gap-4 pt-2">
            <button type="submit"
                    class="rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">
                Trimite invitație
            </button>
            <a href="{{ route('admin.users.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">
                Anulează
            </a>
        </div>

    </form>
</div>
