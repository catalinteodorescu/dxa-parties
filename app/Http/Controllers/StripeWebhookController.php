<?php

namespace App\Http\Controllers;

use App\Models\CreditTopup;
use App\Models\Order;
use App\Services\CreditTopups;
use App\Services\Payments\StripeGateway;
use App\Services\Payments\StripeSignature;
use App\Services\TicketOrders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * DXA: adaugat (runda 64). Webhook-ul Stripe (POST /webhooks/stripe). Singurul loc în care o plată cu cardul devine credite.
 * - Semnătura se verifică pe corpul brut; fără semnătură validă → 400, nu se atinge nimic.
 * - Evenimente tratate: checkout.session.completed / async_payment_succeeded (creditează dacă plata e `paid`), async_payment_failed (eșuat),
 *   checkout.session.expired (anulat, doar dacă încă e în așteptare). Restul se confirmă cu 200 și se ignoră.
 * - Creditarea cere ca suma și moneda din Stripe să fie exact cele înghețate la începerea încărcării; altfel nu se creditează și se loghează eroare.
 * - Idempotent: Stripe reîncearcă; CreditTopups::complete() nu dublează creditele.
 * - Runda 65: sesiunile cu `client_reference_id` = `order_<uuid>` sunt comenzi de bilete (TicketOrders::completeCard / releaseCard); restul, încărcări de credite.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if (! StripeSignature::verify($request->getContent(), $request->header('Stripe-Signature'), $secret)) {
            return response('Semnătură invalidă.', 400);
        }

        $event = json_decode($request->getContent(), true);
        if (! is_array($event)) {
            return response('Corp invalid.', 400);
        }

        $type = (string) ($event['type'] ?? '');
        $session = $event['data']['object'] ?? [];
        if (! str_starts_with($type, 'checkout.session.') || ! is_array($session)) {
            return response('ok');
        }

        $reference = (string) ($session['client_reference_id'] ?? '');
        if (str_starts_with($reference, StripeGateway::ORDER_PREFIX)) {
            $this->handleOrder($type, substr($reference, strlen(StripeGateway::ORDER_PREFIX)), $session);

            return response('ok');
        }

        $topup = CreditTopup::query()->where('uuid', $reference)->first();
        if (! $topup) {
            Log::warning('Stripe: eveniment pentru o încărcare necunoscută', ['type' => $type, 'session' => $session['id'] ?? null]);

            return response('ok');
        }

        switch ($type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                if (($session['payment_status'] ?? '') === 'paid') {
                    $this->credit($topup, $session);
                }
                break;
            case 'checkout.session.async_payment_failed':
            case 'checkout.session.expired':
                // Doar sesiunea curentă (o sesiune veche, închisă la reluarea plății, nu anulează încărcarea).
                if (($session['id'] ?? null) === $topup->provider_ref) {
                    $type === 'checkout.session.expired' ? CreditTopups::cancel($topup) : CreditTopups::fail($topup);
                }
                break;
        }

        return response('ok');
    }

    /** Comandă de bilete plătită cu cardul. */
    private function handleOrder(string $type, string $uuid, array $session): void
    {
        $order = Order::query()->where('uuid', $uuid)->first();
        if (! $order) {
            Log::warning('Stripe: eveniment pentru o comandă necunoscută', ['type' => $type, 'session' => $session['id'] ?? null]);

            return;
        }

        switch ($type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                if (($session['payment_status'] ?? '') !== 'paid') {
                    break;
                }
                $paid = (int) ($session['amount_total'] ?? -1);
                $currency = strtolower((string) ($session['currency'] ?? ''));
                if ($paid !== StripeGateway::cents((float) $order->total) || $currency !== strtolower((string) config('services.stripe.currency', 'ron'))) {
                    Log::error('Stripe: suma/moneda plătită nu corespunde comenzii — biletele NU s-au activat', [
                        'order' => $order->uuid, 'asteptat' => StripeGateway::cents((float) $order->total), 'platit' => $paid, 'moneda' => $currency,
                    ]);
                    break;
                }
                TicketOrders::completeCard($order, (string) ($session['payment_intent'] ?? $session['id'] ?? ''));
                break;
            case 'checkout.session.async_payment_failed':
            case 'checkout.session.expired':
                // Doar sesiunea curentă contează: una veche, închisă de noi când participantul a reluat plata, nu eliberează rezervarea.
                if (($session['id'] ?? null) === $order->payment_ref) {
                    TicketOrders::releaseCard($order, $type === 'checkout.session.expired' ? Order::PAY_CARD_EXPIRED : Order::PAY_CARD_FAILED);
                }
                break;
        }
    }

    private function credit(CreditTopup $topup, array $session): void
    {
        $paid = (int) ($session['amount_total'] ?? -1);
        $currency = strtolower((string) ($session['currency'] ?? ''));
        if ($paid !== StripeGateway::cents($topup->toPay()) || $currency !== strtolower((string) config('services.stripe.currency', 'ron'))) {
            Log::error('Stripe: suma/moneda plătită nu corespunde încărcării — NU s-au creditat', [
                'topup' => $topup->uuid, 'asteptat' => StripeGateway::cents($topup->toPay()), 'platit' => $paid, 'moneda' => $currency,
            ]);

            return;
        }

        CreditTopups::complete($topup, (string) ($session['payment_intent'] ?? $session['id'] ?? ''), 'stripe');
    }
}
