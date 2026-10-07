{{-- DXA: adaugat (runda 51). Bonusul la vânzarea de credite (praguri din Setări). Variabile: $bonus (lei), $amount (suma vândută, lei). --}}
@if ($bonus > 0)
    <div class="rounded-xl bg-bg px-4 py-2.5 text-sm text-ink-soft" data-credit-bonus>
        Bonus <span class="font-semibold text-ink">+{{ number_format((float) $bonus, 2, ',', '.') }} lei</span> · participantul primește <span class="font-semibold text-ink">{{ number_format((float) $amount + (float) $bonus, 2, ',', '.') }} lei</span> credite
    </div>
@endif
