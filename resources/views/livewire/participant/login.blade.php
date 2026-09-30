<div class="pa-stack" style="gap: 1.25rem">
    <div style="text-align: center">
        <h1 class="pa-h1">Bine ai revenit</h1>
        <p class="pa-soft" style="margin: .4rem 0 0">Intră cu telefonul și parola.</p>
    </div>

    @include('livewire.participant._flash')

    <form wire:submit="login" class="pa-glass pa-pad pa-stack" style="gap: 1rem" novalidate>
        @if ($error)
            <div class="pa-alert pa-alert-err" role="alert">{{ $error }}</div>
        @endif

        <div>
            <label for="phone" class="pa-label">Telefon</label>
            <input type="tel" id="phone" wire:model="phone" autocomplete="username" inputmode="tel" placeholder="07XXXXXXXX" class="pa-input">
            @error('phone') <p class="pa-err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="pa-label">Parolă</label>
            <input type="password" id="password" wire:model="password" autocomplete="current-password" class="pa-input">
            @error('password') <p class="pa-err">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" class="pa-btn pa-btn-block">Intră în cont</button>

        <div class="pa-between" style="font-size: .9rem">
            <a href="{{ route('app.forgot') }}" wire:navigate class="pa-link">Ai uitat parola?</a>
        </div>
    </form>

    <p style="text-align: center; margin: 0" class="pa-soft">Nu ai cont? <a href="{{ route('app.register') }}" wire:navigate class="pa-link">Creează unul</a></p>
</div>
