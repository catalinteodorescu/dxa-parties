<div x-data="{ topup: false }" @keydown.escape.window="topup = false">
    <section class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Portofel</div>
        <div class="pa-glass pa-pad pa-stack" style="align-items: center; text-align: center; gap: .35rem; padding: 1.75rem 1rem">
            <div class="pa-soft" style="font-size: .85rem; font-weight: 700">Credite disponibile</div>
            <div style="display: flex; align-items: baseline; justify-content: center; gap: .4rem">
                <span class="pa-price" style="font-size: 3.2rem; line-height: 1" data-balance>{{ number_format($balance, 2, ',', '.') }}</span>
                <span class="pa-soft" style="font-size: .9rem; font-weight: 700">lei</span>
            </div>
        </div>
        <button type="button" class="pa-btn pa-btn-block" @click="topup = true">Încarcă</button>
    </section>

    <section class="pa-section">
        <div class="pa-glass pa-pad pa-stack">
            <div class="pa-label" style="margin: 0">Ultimele tranzacții</div>
            @forelse ($moves as $t)
                <div wire:key="mv-{{ $t->id }}">@include('livewire.participant._credit-row', ['t' => $t])</div>
            @empty
                <div class="pa-soft" style="font-size: .9rem">Nu ai încă tranzacții în portofel.</div>
            @endforelse
            @if ($hasMore)
                @include('livewire.participant._lazy-sentinel', ['limit' => $limit])
            @endif
        </div>
    </section>

    {{-- „Încarcă”: plata online vine cu Stripe; până atunci creditele se încarcă la Recepție. --}}
    <div x-show="topup" x-cloak class="pa-modal-bg" @click.self="topup = false" role="dialog" aria-modal="true" aria-label="Încarcă credite">
        <div class="pa-modal pa-stack" style="gap: 1rem; text-align: center">
            <h2 class="pa-h2" style="margin: 0">Încarcă credite</h2>
            <div class="pa-soft" style="font-size: .92rem">Încărcarea cu cardul în aplicație vine în curând. Până atunci poți încărca credite la Recepție, arătând codul tău QR.</div>
            <button type="button" class="pa-btn pa-btn-block" @click="topup = false">Am înțeles</button>
        </div>
    </div>
</div>
