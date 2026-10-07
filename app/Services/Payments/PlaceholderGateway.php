<?php

namespace App\Services\Payments;

use App\Models\CreditTopup;

/**
 * DXA: adaugat (runda 51). Până la Stripe: trimite la pagina proprie „plata cu cardul urmează”, unde nu se retrage nimic.
 */
class PlaceholderGateway implements PaymentGateway
{
    public function checkoutUrl(CreditTopup $topup): string
    {
        return route('app.wallet.topup', $topup);
    }
}
