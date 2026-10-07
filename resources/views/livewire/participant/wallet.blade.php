<div>
    <section class="pa-section" style="margin-top: .5rem">
        <div class="pa-eyebrow">Portofel</div>
        <div class="pa-glass pa-pad pa-stack" style="align-items: center; text-align: center; gap: .35rem; padding: 1.75rem 1rem">
            <div class="pa-soft" style="font-size: .85rem; font-weight: 700">Credite disponibile</div>
            <div style="display: flex; align-items: baseline; justify-content: center; gap: .4rem">
                <span class="pa-price" style="font-size: 3.2rem; line-height: 1" data-balance>{{ number_format($balance, 2, ',', '.') }}</span>
                <span class="pa-soft" style="font-size: .9rem; font-weight: 700">lei</span>
            </div>
        </div>
        @if ($purchasable)
            <a href="{{ route('app.wallet.load') }}" wire:navigate class="pa-btn pa-btn-block" style="text-align: center" data-topup-open>Încarcă</a>
        @else
            <div class="pa-soft" style="font-size: .9rem; text-align: center" data-topup-unavailable>Încărcarea cu cardul nu este disponibilă acum. Poți încărca credite la Recepție, arătând codul tău QR.</div>
        @endif
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
</div>
