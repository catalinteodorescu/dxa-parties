<?php

namespace App\Livewire\Participant;

use App\Livewire\Concerns\TogglesPartyInterest;
use App\Models\Party;
use App\Services\ContentStats;
use App\Services\Payments\PaymentGateway;
use App\Services\TicketOrders;
use App\Support\PartyPublic;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Detaliul unei petreceri: program, invitați, locație și prețuri cu trepte live.
 * Ciornele și petrecerile dezactivate nu se văd (404); cele „doar logați” trimit la login. Cumpărarea de bilete vine în
 * runda 3 (butonul e deocamdată doar informativ).
 */
#[Layout('layouts.participant')]
class PartyShow extends Component
{
    use TogglesPartyInterest;

    public int $partyId;

    // Cumpărare bilete (runda 14)
    public string $ticketName = '';

    public int $qty = 1;

    public string $code = '';

    public string $appliedCode = '';

    public string $codeError = '';

    public string $buyError = '';

    /** Combo ales („3+1”) sau gol = bilete individuale (runda 26). În combo, `qty` = numărul de seturi. */
    public string $combo = '';

    public function mount(Party $party): void
    {
        abort_if($party->isDraft() || ! $party->is_active, 404);

        if ($party->audience === 'auth' && ! auth('participant')->check()) {
            session()->put('url.intended', route('app.party', $party));
            session()->flash('status', 'Petrecerea e vizibilă doar cu cont. Intră în cont sau creează unul.');
            $this->redirectRoute('app.login', navigate: true);

            return;
        }

        $this->partyId = $party->id;
        ContentStats::record('party', $party->id, 'open', request());   // runda 40: deschiderea paginii
        $this->ticketName = (string) ($party->entryTicketTypes()[0]['name'] ?? '');
    }

    public function selectTicket(string $name): void
    {
        $this->ticketName = $name;
        $this->combo = '';
        $this->qty = max(1, min($this->qty, TicketOrders::maxPerOrder(Party::findOrFail($this->partyId), $name)));
        $this->codeError = $this->buyError = '';
        // Codul aplicat poate să nu meargă pe noul tip: se reverifică la calcul (mesajul apare în sumar).
    }

    /** Alege un combo („3+1”) sau revine la bilete individuale (''). */
    public function selectCombo(string $key): void
    {
        $party = Party::findOrFail($this->partyId);
        $this->combo = $key !== '' && isset(TicketOrders::combos(TicketOrders::ticketType($party, $this->ticketName)['type'] ?? [])[$key]) ? $key : '';
        $this->qty = 1;
        $this->codeError = $this->buyError = '';
    }

    /** Câte bilete intră în comandă: seturi × mărimea combo-ului, sau `qty`. */
    private function ticketCount(Party $party): int
    {
        $def = $this->combo !== '' ? (TicketOrders::combos(TicketOrders::ticketType($party, $this->ticketName)['type'] ?? [])[$this->combo] ?? null) : null;

        return $def ? max(1, $this->qty) * $def['size'] : max(1, $this->qty);
    }

    /** Cât poate crește `qty` (bilete sau seturi). */
    private function maxQtyFor(Party $party): int
    {
        $max = TicketOrders::maxPerOrder($party, $this->ticketName);
        $def = $this->combo !== '' ? (TicketOrders::combos(TicketOrders::ticketType($party, $this->ticketName)['type'] ?? [])[$this->combo] ?? null) : null;

        return $def ? intdiv($max, $def['size']) : $max;
    }

    public function inc(): void
    {
        $party = Party::findOrFail($this->partyId);
        $this->qty = min($this->qty + 1, max(1, $this->maxQtyFor($party)));
    }

    public function dec(): void
    {
        $this->qty = max(1, $this->qty - 1);
    }

    public function applyCode(): void
    {
        $this->codeError = '';
        $party = Party::findOrFail($this->partyId);
        $me = auth('participant')->user();

        try {
            TicketOrders::quote($party, $this->ticketName, $this->ticketCount($party), $this->code, $me ? [$me->id] : [], null, $this->combo ?: null);
        } catch (DomainException $e) {
            $this->codeError = $e->getMessage();

            return;
        }

        $this->appliedCode = strtoupper(preg_replace('/\s+/', '', $this->code));
        $this->dispatch('toast', message: 'Codul de reducere a fost aplicat.', type: 'ok');
    }

    public function clearCode(): void
    {
        $this->code = $this->appliedCode = $this->codeError = '';
    }

    /** Pentru vizitatorii neconectați: după login/înregistrare se întorc aici. */
    public function goLogin(): void
    {
        session()->put('url.intended', route('app.party', $this->partyId));
        $this->redirectRoute('app.login', navigate: true);
    }

    public function goRegister(): void
    {
        session()->put('url.intended', route('app.party', $this->partyId));
        $this->redirectRoute('app.register', navigate: true);
    }

    public function buy(): void
    {
        $this->placeOrder(false);
    }

    /** DXA: adaugat (runda 50). Plata integrală din portofel; confirmarea o cere dialogul înainte de apel, serverul reverifică soldul. */
    public function buyWithCredits(): void
    {
        $this->placeOrder(true);
    }

    private function placeOrder(bool $withCredits): void
    {
        $this->buyError = '';
        $me = auth('participant')->user();
        if (! $me) {
            $this->goLogin();

            return;
        }

        // Runda 65: cu Stripe activ, „Confirmă” înseamnă plata cu cardul (biletele se rezervă, apoi redirecționare la Stripe).
        $gateway = app(PaymentGateway::class);
        $card = ! $withCredits && $gateway->online();

        try {
            $party = Party::findOrFail($this->partyId);
            $count = $this->ticketCount($party);
            $order = TicketOrders::place(
                $me, $party, $this->ticketName, $count,
                $this->appliedCode ?: null, null, $this->combo ?: null, $withCredits, $card
            );

            if ($order->isAwaitingCard()) {
                try {
                    $url = $gateway->orderCheckoutUrl($order);
                } catch (DomainException $e) {
                    TicketOrders::releaseCard($order);   // nu lăsăm locuri blocate dacă pagina de plată nu a putut fi creată

                    throw $e;
                }

                $this->redirect($url);   // pagină pe alt domeniu: redirecționare completă

                return;
            }
        } catch (DomainException $e) {
            $this->buyError = $e->getMessage();

            return;
        }

        session()->flash('status', ($withCredits ? 'Plătit cu credite. ' : '').self::orderMessage($order));
        $this->redirectRoute('app.tickets', navigate: true);
    }

    /** Mesajul după comandă (runda 53): toate biletele sunt în contul cumpărătorului; cele libere se pot trimite din Bilete. */
    private static function orderMessage($order): string
    {
        $total = $order->tickets->count();

        return $total === 1 ? 'Biletul tău e gata! Îl găsești la Bilete.' : 'Cele '.$total.' bilete sunt gata! Le găsești la Bilete, iar pe cele libere le poți trimite altcuiva.';
    }

    public function render()
    {
        TicketOrders::expireStalePending();   // runda 65: rezervările cu cardul neplătite eliberează locurile
        $party = Party::findOrFail($this->partyId);
        $me = auth('participant')->user();

        $saleBlock = TicketOrders::saleBlockReason($party);
        $maxQty = 0;
        $quote = null;
        $quoteError = '';
        $options = [];
        $combos = [];
        $count = 0;
        $capReason = null;
        $creditBalance = null;   // runda 50: soldul, doar dacă acoperă totalul (altfel opțiunea „Plătesc cu credite” nu apare)

        if (! $saleBlock) {
            foreach ($party->entryTicketTypes() as $t) {
                $unit = TicketOrders::unitPrice($party, $t['type'], TicketOrders::tierSoldCount($party, $t['name']));
                $base = isset($t['type']['price']) && is_numeric($t['type']['price']) ? (float) $t['type']['price'] : null;
                $options[] = [
                    'name' => $t['name'],
                    'price' => $unit,
                    'was' => $base !== null && $unit < $base - 0.005 ? $base : null,
                    'available' => TicketOrders::available($party, $t['name']),
                ];
            }

            // Combo-urile tipului ales care încap acum (stoc, limită per comandă).
            $perOrder = TicketOrders::maxPerOrder($party, $this->ticketName);
            $combos = array_values(array_filter(
                TicketOrders::combos(TicketOrders::ticketType($party, $this->ticketName)['type'] ?? []),
                fn ($c) => $c['size'] <= $perOrder
            ));
            $ticketType = TicketOrders::ticketType($party, $this->ticketName)['type'] ?? [];
            $combos = array_map(fn ($c) => $c + ['note' => TicketOrders::comboNote($party, $ticketType, $this->ticketName, $c)], $combos);
            if ($this->combo !== '' && ! collect($combos)->contains('key', $this->combo)) {
                $this->combo = '';
            }

            $maxQty = $this->maxQtyFor($party);
            $count = 0;
            $capReason = TicketOrders::capReason($party, $this->ticketName);
            if ($maxQty > 0) {
                $this->qty = max(1, min($this->qty, $maxQty));
                $count = $this->ticketCount($party);
                try {
                    $quote = TicketOrders::quote($party, $this->ticketName, $count, $this->appliedCode ?: null, $me ? [$me->id] : [], null, $this->combo ?: null);
                } catch (DomainException $e) {
                    $quoteError = $e->getMessage();
                }
                if ($quote && $me && TicketOrders::canPayWithCredits($me, $party, (float) $quote->total)) {
                    $creditBalance = (float) $me->credit_balance;
                }
            }
        }

        return view('livewire.participant.party-show', [
            'party' => $party,
            'tickets' => PartyPublic::tickets($party),
            'state' => $party->state(),
            'me' => $me,
            'saleBlock' => $saleBlock,
            'options' => $options,
            'maxQty' => $maxQty,
            'combos' => $combos,
            'count' => $count,
            'quote' => $quote,
            'quoteError' => $quoteError,
            'creditBalance' => $creditBalance,
            'cardPay' => $quote && (float) $quote->total > 0 && app(PaymentGateway::class)->online(),   // runda 65: „Confirmă” = plata cu cardul
            'capReason' => $capReason,
        ])->title($party->name);
    }
}
