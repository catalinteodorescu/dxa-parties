<div class="pa-stack" style="gap: 1.25rem">
    <div style="text-align: center">
        <h1 class="pa-h1">Confirmă telefonul</h1>
        <p class="pa-soft" style="margin: .4rem 0 0">Am trimis un cod de 6 cifre la {{ $masked }}. Expiră în {{ \App\Services\ParticipantAccounts::CODE_TTL_MINUTES }} minute.</p>
    </div>

    <form wire:submit="verify" class="pa-glass pa-pad pa-stack" style="gap: 1rem" novalidate>
        @if ($error)
            <div class="pa-alert pa-alert-err" role="alert">{{ $error }}</div>
        @endif
        @if ($notice)
            <div class="pa-alert pa-alert-ok" role="status">{{ $notice }}</div>
        @endif

        <div>
            <label for="code" class="pa-label">Codul din SMS</label>
            <input type="text" id="code" wire:model="code" autocomplete="one-time-code" inputmode="numeric" maxlength="6" placeholder="••••••" autofocus class="pa-input pa-code">
            @error('code') <p class="pa-err">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" class="pa-btn pa-btn-block">Activează contul</button>
    </form>

    <div style="text-align: center" x-data="{ s: {{ (int) $wait }}, t: null }"
         x-init="if (s > 0) { t = setInterval(() => { s = Math.max(0, s - 1); if (s === 0) clearInterval(t) }, 1000) }">
        <span x-show="s > 0" class="pa-soft">Poți cere alt cod în <b x-text="s"></b> s</span>
        <button type="button" wire:click="resend" x-show="s === 0" class="pa-link" @if ($wait > 0) x-cloak style="display: none" @endif>Retrimite codul</button>
    </div>

    <p style="text-align: center; margin: 0"><a href="{{ route('app.register') }}" wire:navigate class="pa-link pa-soft">Am greșit numărul</a></p>
</div>
