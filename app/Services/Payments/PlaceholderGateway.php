<?php

namespace App\Services\Payments;

use App\Models\CreditTopup;
use App\Models\Order;
use DomainException;

/**
 * DXA: adaugat (runda 51). Până la Stripe: trimite la pagina proprie „plata cu cardul urmează”, unde nu se retrage nimic.
 */
class PlaceholderGateway implements PaymentGateway
{
    public function checkoutUrl(CreditTopup $topup): string
    {
        return route('app.wallet.topup', $topup);
    }

    public function orderCheckoutUrl(Order $order): string
    {
        throw new DomainException('Plata cu cardul nu este disponibilă acum.');
    }

    public function online(): bool
    {
        return false;
    }
}
