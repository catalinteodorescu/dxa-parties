<?php

namespace App\Services\Payments;

use App\Models\CreditTopup;
use App\Models\Order;

/**
 * DXA: adaugat (runda 51). Interfața proprie a plății online (Stripe e implementarea reală, din runda 64). Aplicația cunoaște doar asta:
 * dă o încărcare în așteptare și primește adresa paginii de plată. Finalizarea (webhook-ul furnizorului) apelează
 * App\Services\CreditTopups::complete() / fail().
 */
interface PaymentGateway
{
    /** Adresa la care se trimite participantul ca să plătească încărcarea. */
    public function checkoutUrl(CreditTopup $topup): string;

    /** DXA: adaugat (runda 65). Adresa paginii de plată pentru o comandă de bilete rezervată (`card_pending`). */
    public function orderCheckoutUrl(Order $order): string;

    /** Dacă plata cu cardul e activă (furnizor configurat), nu doar un loc rezervat. */
    public function online(): bool;
}
