<?php

namespace App\Services\Payments;

use App\Models\CreditTopup;

/**
 * DXA: adaugat (runda 51). Interfața proprie a plății online (Stripe va fi o implementare). Aplicația cunoaște doar asta:
 * dă o încărcare în așteptare și primește adresa paginii de plată. Finalizarea (webhook-ul furnizorului) apelează
 * App\Services\CreditTopups::complete() / fail().
 */
interface PaymentGateway
{
    /** Adresa la care se trimite participantul ca să plătească încărcarea. */
    public function checkoutUrl(CreditTopup $topup): string;
}
