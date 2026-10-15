<?php

namespace App\Providers;

use App\Auth\ZoneGuard;
use App\Contracts\SmsSender;
use App\Http\Middleware\ApplyAppWorker;
use App\Http\Middleware\ApplyAuthZone;
use App\Services\CreditLedger;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PlaceholderGateway;
use App\Services\Payments\StripeGateway;
use App\Services\Push\PushGateway;
use App\Services\Push\WebPushGateway;
use App\Services\Sms\AsciiSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use App\Support\PermissionGuard;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Implementare de dezvoltare (loghează SMS-urile în loc să le trimită).
        // Se înlocuiește cu un provider real când e ales.
        $this->app->bind(SmsSender::class, fn () => new AsciiSmsSender(new LogSmsSender));
        // DXA: adaugat (runda 51, runda 64). Plata online: Stripe dacă STRIPE_SECRET e setat, altfel pagina „plata cu cardul urmează”.
        $this->app->bind(PaymentGateway::class, fn () => filled(config('services.stripe.secret')) ? new StripeGateway : new PlaceholderGateway);
        // DXA: adaugat (runda 70). Notificări push (Web Push); testele leagă o implementare falsă.
        $this->app->bind(PushGateway::class, fn () => new WebPushGateway);
    }

    public function boot(): void
    {
        // În spatele unui proxy/tunel care oprește HTTPS (Cloudflare, load balancer) cererea ajunge la aplicație ca HTTP
        // și linkurile (CSS/JS, Livewire) ies cu http:// → blocate ca „mixed content”. Dacă APP_URL e HTTPS, forțăm HTTPS.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // DXA: adaugat (runda 73): lucrătorul activ din Recepție / Bar se aplică și acțiunilor Livewire (nu doar paginilor).
        Livewire::addPersistentMiddleware([ApplyAuthZone::class, ApplyAppWorker::class]);

        // DXA: adaugat (runda 74): ghidul `zone` — același SessionGuard, dar cu cheie de sesiune și cookie „ține-mă conectat” pe zonă.
        Auth::extend('zone', function ($app, $name, $config) {
            $guard = new ZoneGuard(
                $name,
                Auth::createUserProvider($config['provider'] ?? null),
                $app['session.store'],
                rehashOnLogin: $app['config']->get('hashing.rehash_on_login', true),
                timeboxDuration: $app['config']->get('auth.timebox_duration', 200000),
                hashKey: $app['config']->get('app.key'),
            );
            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

            return $guard;
        });

        // DXA: adaugat (runda 46 — permisiuni): paza acțiunilor Livewire din panou.
        PermissionGuard::register();
        // @permits('secțiune', 'edit') … @endpermits  și  @permitsAction('export_pdf') … @endpermitsAction — butoane doar pentru cine are voie.
        Blade::if('permits', fn (string $section, string $level = 'view') => (bool) auth('admin')->user()?->permits($section, $level));
        Blade::if('permitsAction', fn (string $action) => (bool) auth('admin')->user()?->permitsAction($action));

        // Paginare custom (tema DXA) pentru toate listele.
        Paginator::defaultView('pagination.dxa');
        Paginator::defaultSimpleView('pagination.dxa');

        // DXA: adaugat (Recepție - tokeni): tokenii nu se pot opri cât mai sunt în circulație (se casează întâi).
        PaymentMethods::registerGuard(PaymentMethods::TOKEN, fn () => TokenLedger::offBlockReason(), 'token-ledger');

        // DXA: adaugat (Portofelul de credite): creditele nu se pot opri cât participanții mai au sold > 0.
        PaymentMethods::registerGuard(PaymentMethods::CREDIT, fn () => CreditLedger::offBlockReason(), 'credit-ledger');
    }
}
