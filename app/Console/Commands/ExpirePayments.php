<?php

namespace App\Console\Commands;

use App\Services\CreditTopups;
use App\Services\TicketOrders;
use Illuminate\Console\Command;

/**
 * DXA: adaugat (runda 76). Închide plățile cu cardul rămase în așteptare după expirare: rezervările de bilete (locurile se eliberează) și încărcările de credite.
 * O plată sosită totuși mai târziu se tratează ca înainte (webhook-ul Stripe). Se rulează din cron, la 15 minute.
 */
class ExpirePayments extends Command
{
    protected $signature = 'dxa:expire-payments';

    protected $description = 'Închide rezervările și încărcările cu cardul expirate';

    public function handle(): int
    {
        $orders = TicketOrders::expireStalePending();
        $topups = CreditTopups::expireStale();
        $this->line('Rezervări de bilete închise: '.$orders.' · încărcări de credite închise: '.$topups);

        return self::SUCCESS;
    }
}
