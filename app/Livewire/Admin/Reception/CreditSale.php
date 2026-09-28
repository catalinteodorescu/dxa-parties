<?php

namespace App\Livewire\Admin\Reception;

use App\Livewire\Admin\Concerns\PicksParticipants;
use App\Models\Party;
use App\Models\ReceptionSession;
use App\Services\CreditLedger;
use App\Services\ParticipantRegistry;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * DXA: adaugat (Portofelul de credite - Etapa 2). Cardul „Vinde credite” din ecranul Recepție (în oglindă cu
 * TokenSale), montat cu petrecerea aleasă. Plata se introduce pe total, poate fi mixtă, fără credite (nu se
 * cumpără credite cu credite). Spre deosebire de tokeni, creditele merg în portofelul unui participant, deci
 * participantul e OBLIGATORIU (nu se pot vinde credite anonim). Disponibilă doar cât „participanții pot cumpăra
 * credite” e activ în Setări. Logica stă în App\Services\CreditLedger.
 * Lista ultimelor vânzări (cu anulare) e în coloana din dreapta a ecranului Recepție (Form); între ele se
 * anunță evenimentul „credits-changed”.
 */
class CreditSale extends Component
{
    use PicksParticipants;

    public int $partyId;

    public string $amount = '20';

    /** @var array<int, array{method: string, amount: string}> plata pe TOTALUL vânzării */
    public array $payments = [['method' => 'cash', 'amount' => '']];

    public ?string $message = null;

    public ?string $error = null;

    public function mount(int $partyId): void
    {
        $this->partyId = $partyId;
    }

    private function party(): ?Party
    {
        return Party::find($this->partyId);
    }

    /** @return array<string, string> */
    private function methods(): array
    {
        return array_diff_key(PaymentMethods::forEntry($this->party()), [PaymentMethods::CREDIT => true]);
    }

    private function amountValue(): float
    {
        $raw = str_replace(',', '.', trim($this->amount));

        return is_numeric($raw) ? max(0.0, min(CreditLedger::MAX_SALE, (float) $raw)) : 0.0;
    }

    private function totalCents(): int
    {
        return (int) round($this->amountValue() * 100);
    }

    public function setAmount(int $n): void
    {
        $this->amount = (string) $n;
    }

    /** „Restul”: completează în rândul $i suma rămasă de încasat (ca la bar). */
    public function fillRemaining(int $i): void
    {
        $others = 0;
        foreach ($this->payments as $k => $p) {
            if ($k === $i) {
                continue;
            }
            $raw = str_replace(',', '.', trim((string) ($p['amount'] ?? '')));
            $others += is_numeric($raw) && (float) $raw > 0 ? (int) round((float) $raw * 100) : 0;
        }

        $remaining = max(0, $this->totalCents() - $others);
        $this->payments[$i]['amount'] = $remaining > 0 ? ($remaining % 100 === 0 ? (string) intdiv($remaining, 100) : number_format($remaining / 100, 2, '.', '')) : '';
    }

    public function addPayment(): void
    {
        $this->payments[] = ['method' => array_key_first($this->methods()) ?? 'cash', 'amount' => ''];
    }

    public function removePayment(int $i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments) ?: [['method' => 'cash', 'amount' => '']];
    }

    public function payAll(string $method): void
    {
        $total = $this->totalCents() / 100;
        $this->payments = [['method' => $method, 'amount' => $total == floor($total) ? (string) (int) $total : number_format($total, 2, '.', '')]];
    }

    public function sell(): void
    {
        $this->message = $this->error = null;
        $this->participantError = null;
        $party = $this->party();

        try {
            if (! $party) {
                throw new DomainException('Alege o petrecere.');
            }
            if (! $this->participantIds) {
                throw new DomainException('Alege participantul căruia îi vinzi credite (creditele merg în portofelul lui).');
            }

            $participant = ParticipantRegistry::usable($this->participantIds[0]);
            $hadSession = ReceptionSession::currentFor($party->id) !== null;

            $tx = CreditLedger::sell(
                $party,
                $participant,
                $this->amountValue(),
                $this->payments,
                Auth::guard('admin')->id(),
            );

            $this->message = sprintf('Vândut: %s lei credite pentru %s.', number_format((float) $tx->amount, 2, ',', '.'), $participant->label());
            if (! $hadSession) {
                $this->message .= ' '.ReceptionSession::openedNotice($party->id);
            }
            $this->amount = '20';
            $this->payments = [['method' => 'cash', 'amount' => '']];
            $this->resetParticipants();
            $this->dispatch('credits-changed'); // reafiseaza listele din ecranul Receptie
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    /** Vânzare anulată din lista din dreapta (Form): reafișează. */
    #[On('credits-changed')]
    public function refresh(): void
    {
        // doar re-randare
    }

    public function render()
    {
        $party = $this->party();

        $paid = 0;
        foreach ($this->payments as $p) {
            $raw = str_replace(',', '.', trim((string) ($p['amount'] ?? '')));
            $paid += is_numeric($raw) && (float) $raw > 0 ? (int) round((float) $raw * 100) : 0;
        }
        $total = $this->totalCents();

        return view('livewire.admin.reception.credit-sale', [
            ...$this->participantPickerData(),
            'paid' => $paid / 100,
            'party' => $party,
            'purchasable' => PaymentMethods::creditsPurchasable(),
            'methods' => $this->methods(),
            'methodLabels' => PaymentMethods::labels(),
            'total' => $total / 100,
            'rest' => ($total - $paid) / 100,
            'totals' => $party ? CreditLedger::partyTotals($party) : null,
        ]);
    }
}
