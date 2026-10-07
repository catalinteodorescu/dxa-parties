<?php

namespace App\Livewire\Reception;

use App\Livewire\Concerns\GuardsResubmit;
use App\Livewire\Concerns\HandlesSaleScreen;
use App\Livewire\Reception\Concerns\UsesReceptionParty;
use App\Models\ReceptionSession;
use App\Services\CreditBonus;
use App\Services\CreditLedger;
use App\Services\ParticipantRegistry;
use App\Support\PaymentMethods;
use App\Support\RecentEntryParticipants;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Recepție - Etapa 3). Ecranul „Vânzare credite”: suma, participantul (OBLIGATORIU, sugerat
 * din ultima intrare) și plata pe total. Logica de business e în App\Services\CreditLedger (aceeași ca pe web).
 */
#[Layout('layouts.reception')]
class CreditSale extends Component
{
    use GuardsResubmit;
    use HandlesSaleScreen;
    use UsesReceptionParty;

    public string $amount = '20';

    protected function saleKind(): string
    {
        return RecentEntryParticipants::CREDITS;
    }

    private function amountValue(): float
    {
        $raw = str_replace(',', '.', trim($this->amount));

        return is_numeric($raw) ? max(0.0, min(CreditLedger::MAX_SALE, (float) $raw)) : 0.0;
    }

    protected function totalCents(): int
    {
        return (int) round($this->amountValue() * 100);
    }

    public function setAmount(int $n): void
    {
        $this->amount = (string) $n;
    }

    public function sell(): void
    {
        $this->error = null;
        $this->participantError = null;
        $party = $this->currentParty();

        try {
            if (! $party) {
                throw new DomainException('Alege o petrecere.');
            }
            if (! $this->participantIds) {
                throw new DomainException('Alege participantul căruia îi vinzi credite (creditele merg în portofelul lui).');
            }

            $participant = ParticipantRegistry::usable($this->participantIds[0]);
            $hadSession = ReceptionSession::currentFor($party->id) !== null;

            $tx = $this->once(fn () => CreditLedger::sell($party, $participant, $this->amountValue(), $this->payments, Auth::guard('admin')->id()));

            RecentEntryParticipants::markSold($party->id, RecentEntryParticipants::CREDITS, $participant->id);

            $this->done = [
                'title' => 'Credite vândute',
                'line' => $participant->label().((float) $tx->bonus > 0 ? ' · bonus '.number_format((float) $tx->bonus, 2, ',', '.').' lei' : ''),
                'total' => number_format((float) $tx->amount, 2, ',', '.'),
                'note' => $hadSession ? null : ReceptionSession::openedNotice($party->id),
            ];
            $this->amount = '20';
            $this->payments = [['method' => 'cash', 'amount' => '']];
            $this->resetParticipants();
            $this->quickPickIds = [];
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.reception.credit-sale', [
            ...$this->saleViewData($this->currentParty()),
            'purchasable' => PaymentMethods::creditsPurchasable(),
            'bonus' => CreditBonus::bonusFor($this->amountValue()),   // runda 51
            'amountSold' => $this->amountValue(),
        ]);
    }
}
