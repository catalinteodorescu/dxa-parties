<div class="pa-stack" style="gap: 1.25rem">
    <div style="text-align: center">
        <h1 class="pa-h1">Parolă nouă</h1>
        <p class="pa-soft" style="margin: .4rem 0 0">Alege o parolă de cel puțin {{ \App\Services\ParticipantAccounts::MIN_PASSWORD }} caractere.</p>
    </div>

    <form wire:submit="save" class="pa-glass pa-pad pa-stack" style="gap: 1rem" novalidate>
        @if ($error)
            <div class="pa-alert pa-alert-err" role="alert">{{ $error }}</div>
        @endif

        <div>
            <label for="password" class="pa-label">Parolă nouă</label>
            <input type="password" id="password" wire:model="password" autocomplete="new-password" class="pa-input">
            @error('password') <p class="pa-err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="pa-label">Repetă parola</label>
            <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" class="pa-input">
        </div>

        <button type="submit" wire:loading.attr="disabled" class="pa-btn pa-btn-block">Schimbă parola</button>
    </form>
</div>
