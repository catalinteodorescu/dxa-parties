<div class="max-w-xl">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">Permisiuni — {{ $target->name ?: $target->phone }}</h2>
        <p class="mt-1 text-sm text-ink-soft leading-relaxed">
            Ce vede și ce poate face acest utilizator în panoul admin. Modificările se aplică imediat.
        </p>
    </div>

    <form wire:submit="save" class="bg-surface border border-border rounded-2xl p-6 space-y-4">
        @include('livewire.admin.users._permissions')

        <div class="flex items-center gap-4 pt-2">
            <button type="submit"
                    class="rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-medium px-4 py-2.5 transition-colors">
                Salvează permisiunile
            </button>
            <a href="{{ route('admin.users.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">Anulează</a>
        </div>
    </form>
</div>
