<div class="max-w-2xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">{{ $heading }}</h2>
        <p class="mt-1 text-sm text-ink-soft">Cum arată {{ str_replace('aplicației', 'aplicația', $appPhrase) }} de pe telefon: numele, logo-ul și culoarea temei. Tema colorează și iconița de pe ecranul telefonului.</p>
    </div>

    <x-flash class="mb-4" />

    <form wire:submit="save" class="space-y-4">

        @include('livewire.admin._pwa-identity')

        <div>
            <x-btn variant="primary" type="submit">Salvează</x-btn>
        </div>
    </form>
</div>
