<?php

namespace App\Livewire\Participant;

use App\Models\Party;
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
    public int $partyId;

    // Cumpărare bilete (runda 14)
    public string $ticketName = '';

    public int $qty = 1;

    /** Telefoanele participanților pentru biletele 2..N (opționale). */
    public array $phones = [];

    public string $code = '';

    public string $appliedCode = '';

    public string $codeError = '';

    public string $buyError = '';

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
        $this->ticketName = (string) ($party->entryTicketTypes()[0]['name'] ?? '');
    }

    public function selectTicket(string $name): void
    {
        $this->ticketName = $name;
        $this->qty = max(1, min($this->qty, TicketOrders::maxPerOrder(Party::findOrFail($this->partyId), $name)));
        $this->codeError = $this->buyError = '';
        // Codul aplicat poate să nu meargă pe noul tip: se reverifică la calcul (mesajul apare în sumar).
    }

    public function inc(): void
    {
        $party = Party::findOrFail($this->partyId);
        $this->qty = min($this->qty + 1, max(1, TicketOrders::maxPerOrder($party, $this->ticketName)));
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
            TicketOrders::quote($party, $this->ticketName, max(1, $this->qty), $this->code, $me ? [$me->id] : []);
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
        $this->buyError = '';
        $me = auth('participant')->user();
        if (! $me) {
            $this->goLogin();

            return;
        }

        try {
            $order = TicketOrders::place(
                $me, Party::findOrFail($this->partyId), $this->ticketName, $this->qty,
                array_slice($this->phones, 0, max(0, $this->qty - 1)), $this->appliedCode ?: null
            );
        } catch (DomainException $e) {
            $this->buyError = $e->getMessage();

            return;
        }

        session()->flash('status', $order->tickets_count === 1 ? 'Biletul tău e gata! Îl găsești la Bilete.' : 'Cele '.$order->tickets_count.' bilete sunt gata! Le găsești la Bilete.');
        $this->redirectRoute('app.tickets', navigate: true);
    }

    public function render()
    {
        $party = Party::findOrFail($this->partyId);
        $me = auth('participant')->user();

        $saleBlock = TicketOrders::saleBlockReason($party);
        $maxQty = 0;
        $quote = null;
        $quoteError = '';
        $options = [];

        if (! $saleBlock) {
            foreach ($party->entryTicketTypes() as $t) {
                $unit = TicketOrders::unitPrice($party, $t['type'], TicketOrders::soldCount($party, $t['name']));
                $base = isset($t['type']['price']) && is_numeric($t['type']['price']) ? (float) $t['type']['price'] : null;
                $options[] = [
                    'name' => $t['name'],
                    'price' => $unit,
                    'was' => $base !== null && $unit < $base - 0.005 ? $base : null,
                    'available' => TicketOrders::available($party, $t['name']),
                ];
            }

            $maxQty = TicketOrders::maxPerOrder($party, $this->ticketName);
            if ($maxQty > 0) {
                $this->qty = max(1, min($this->qty, $maxQty));
                try {
                    $quote = TicketOrders::quote($party, $this->ticketName, $this->qty, $this->appliedCode ?: null, $me ? [$me->id] : []);
                } catch (DomainException $e) {
                    $quoteError = $e->getMessage();
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
            'quote' => $quote,
            'quoteError' => $quoteError,
        ])->title($party->name);
    }
}
