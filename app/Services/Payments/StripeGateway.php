<?php

namespace App\Services\Payments;

use App\Models\CreditTopup;
use App\Models\Order;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DXA: adaugat (runda 64). Plata cu cardul prin Stripe Checkout găzduit (card, Apple Pay, Google Pay). Fără pachet Composer nou:
 * vorbim direct cu API-ul REST. Participantul e trimis la pagina Stripe; creditele intră DOAR când webhook-ul semnat
 * (App\Http\Controllers\StripeWebhookController) apelează CreditTopups::complete() — întoarcerea în aplicație nu creditează nimic.
 * Comisionul Stripe îl suportă școala (fee = 0): se plătește exact suma încărcată.
 */
class StripeGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1';

    public function checkoutUrl(CreditTopup $topup): string
    {
        $this->expirePrevious($topup->provider, $topup->provider_ref);

        $return = route('app.wallet.topup', $topup);
        $session = $this->createSession([
            'client_reference_id' => $topup->uuid,
            'success_url' => $return.'?plata=ok',
            'cancel_url' => $return,
            'product' => 'Încărcare credite',
            'amount' => self::cents($topup->toPay()),
            'ref' => $topup->uuid,
        ]);

        $topup->update(['provider' => 'stripe', 'provider_ref' => $session['id']]);

        return $session['url'];
    }

    /** DXA: adaugat (runda 65). Comanda de bilete: o singură linie cu totalul înghețat la rezervare (reduceri, cod, trepte deja aplicate). */
    public function orderCheckoutUrl(Order $order): string
    {
        $this->expirePrevious($order->payment_provider, $order->payment_ref);

        $order->loadMissing('party');
        $return = route('app.tickets.pay', $order);
        $session = $this->createSession([
            'client_reference_id' => self::ORDER_PREFIX.$order->uuid,
            'success_url' => $return.'?plata=ok',
            'cancel_url' => $return,
            'product' => 'Bilete · '.($order->party?->name ?? 'petrecere'),
            'amount' => self::cents((float) $order->total),
            'ref' => self::ORDER_PREFIX.$order->uuid,
        ]);

        $order->update(['payment_provider' => 'stripe', 'payment_ref' => $session['id']]);

        return $session['url'];
    }

    /** Prefixul care deosebește, în `client_reference_id`, o comandă de bilete de o încărcare de credite. */
    public const ORDER_PREFIX = 'order_';

    /**
     * @param  array{client_reference_id: string, success_url: string, cancel_url: string, product: string, amount: int, ref: string}  $p
     * @return array{id: string, url: string}
     */
    private function createSession(array $p): array
    {
        try {
            $response = $this->http()->asForm()->post(self::API.'/checkout/sessions', [
                'mode' => 'payment',
                'locale' => 'ro',
                'client_reference_id' => $p['client_reference_id'],
                'success_url' => $p['success_url'],
                'cancel_url' => $p['cancel_url'],
                // Stripe cere minim 30 de minute; rezervarea biletelor ține mai mult (TicketOrders::RESERVATION_MINUTES).
                'expires_at' => now()->addMinutes(31)->timestamp,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower((string) config('services.stripe.currency', 'ron')),
                        'unit_amount' => $p['amount'],
                        'product_data' => ['name' => $p['product']],
                    ],
                ]],
                'metadata' => ['ref' => $p['ref']],
                'payment_intent_data' => ['metadata' => ['ref' => $p['ref']]],
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Stripe: conexiune eșuată la crearea sesiunii: '.$e->getMessage());
            throw new DomainException('Plata cu cardul nu poate fi pornită acum. Încearcă din nou.');
        }

        $url = $response->json('url');
        if (! $response->successful() || ! is_string($url) || $url === '') {
            Log::error('Stripe: sesiune de plată refuzată', ['status' => $response->status(), 'body' => $response->json('error.message')]);
            throw new DomainException('Plata cu cardul nu poate fi pornită acum. Încearcă din nou.');
        }

        return ['id' => (string) $response->json('id'), 'url' => $url];
    }

    public function online(): bool
    {
        return true;
    }

    /** Suma în subunități (bani), fără erori de virgulă mobilă. */
    public static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /** Dacă participantul reîncepe plata, sesiunea veche se închide, ca să nu poată plăti de două ori aceeași încărcare/comandă. */
    private function expirePrevious(?string $provider, ?string $ref): void
    {
        $ref = (string) $ref;
        if ($provider !== 'stripe' || ! str_starts_with($ref, 'cs_')) {
            return;
        }
        try {
            $this->http()->asForm()->post(self::API.'/checkout/sessions/'.$ref.'/expire');
        } catch (ConnectionException) {
            // Best effort: dacă nu reușește, complete()/completeCard() oricum sunt idempotente.
        }
    }

    private function http(): PendingRequest
    {
        return Http::withToken((string) config('services.stripe.secret'))
            ->withHeaders(['Stripe-Version' => (string) config('services.stripe.api_version')])
            ->timeout(15);
    }
}
