<div class="pa-stack" style="gap: 1.25rem">
    <div style="text-align: center">
        <h1 class="pa-h1">Cont nou</h1>
        <p class="pa-soft" style="margin: .4rem 0 0">Îți trimitem un cod prin SMS ca să-ți confirmi telefonul.</p>
    </div>

    <form wire:submit="register" class="pa-glass pa-pad pa-stack" style="gap: 1rem" novalidate>
        @if ($error)
            <div class="pa-alert pa-alert-err" role="alert">{{ $error }}</div>
        @endif

        <div>
            <label for="name" class="pa-label">Numele tău</label>
            <input type="text" id="name" wire:model="name" autocomplete="name" placeholder="Ion Popescu" class="pa-input">
            @error('name') <p class="pa-err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="phone" class="pa-label">Telefon</label>
            <input type="tel" id="phone" wire:model="phone" autocomplete="tel" inputmode="tel" placeholder="07XXXXXXXX" class="pa-input">
            @error('phone') <p class="pa-err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="pa-label">Parolă (minim {{ \App\Services\ParticipantAccounts::MIN_PASSWORD }} caractere)</label>
            <input type="password" id="password" wire:model="password" autocomplete="new-password" class="pa-input">
            @error('password') <p class="pa-err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="pa-label">Repetă parola</label>
            <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" class="pa-input">
        </div>

        <button type="submit" wire:loading.attr="disabled" class="pa-btn pa-btn-block">Trimite codul</button>
    </form>

    <p style="text-align: center; margin: 0" class="pa-soft">Ai deja cont? <a href="{{ route('app.login') }}" wire:navigate class="pa-link">Intră în cont</a></p>
</div>
