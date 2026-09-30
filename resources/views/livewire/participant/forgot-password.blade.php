<div class="pa-stack" style="gap: 1.25rem">
    <div style="text-align: center">
        <h1 class="pa-h1">Parolă uitată</h1>
        <p class="pa-soft" style="margin: .4rem 0 0">Îți trimitem prin SMS un link ca să alegi o parolă nouă.</p>
    </div>

    @if ($sent)
        <div class="pa-glass pa-pad pa-stack" role="status">
            <div class="pa-alert pa-alert-ok">Dacă numărul are cont, ți-am trimis un SMS cu linkul de resetare. Linkul e valabil {{ \App\Services\ParticipantAccounts::RESET_TTL_MINUTES }} de minute.</div>
            <a href="{{ route('app.login') }}" wire:navigate class="pa-btn pa-btn-ghost pa-btn-block">Înapoi la login</a>
        </div>
    @else
        <form wire:submit="send" class="pa-glass pa-pad pa-stack" style="gap: 1rem" novalidate>
            <div>
                <label for="phone" class="pa-label">Telefon</label>
                <input type="tel" id="phone" wire:model="phone" autocomplete="username" inputmode="tel" placeholder="07XXXXXXXX" class="pa-input">
                @error('phone') <p class="pa-err">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" class="pa-btn pa-btn-block">Trimite linkul</button>
        </form>
    @endif
</div>
