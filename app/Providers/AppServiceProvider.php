<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Implementare de dezvoltare (loghează SMS-urile în loc să le trimită).
        // Se înlocuiește cu un provider real când e ales.
        $this->app->bind(SmsSender::class, LogSmsSender::class);
    }

    public function boot(): void
    {
        // Paginare custom (tema DXA) pentru toate listele.
        Paginator::defaultView('pagination.dxa');
        Paginator::defaultSimpleView('pagination.dxa');

        // DXA: adaugat (Recepție - tokeni): tokenii nu se pot opri cât mai sunt în circulație (se casează întâi).
        PaymentMethods::registerGuard(PaymentMethods::TOKEN, fn () => TokenLedger::offBlockReason(), 'token-ledger');
    }
}
