<?php

namespace App\Livewire\Reception;

use App\Livewire\Concerns\HandlesSaleScreen;
use App\Livewire\Reception\Concerns\UsesReceptionParty;
use App\Models\ReceptionSession;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use App\Support\RecentEntryParticipants;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Recepție - Etapa 3). Ecranul „Vânzare tokeni”: număr de tokeni, participant (opțional, precompletat
 * din ultima intrare), plata pe total. Logica de business e în App\Services\TokenLedger (aceeași ca pe web).
 */
#[Layout('layouts.reception')]
class TokenSale extends Component
{
    use HandlesSaleScreen;
    use UsesReceptionParty;

    public int|string $tokens = 10;

    protected function saleKind(): string
    {
        return RecentEntryParticipants::TOKENS;
    }

    private function qty(): int
    {
        return max(1, min(TokenLedger::MAX_SALE, (int) $this->tokens));
    }

    protected function totalCents(): int
    {
        return (int) round($this->qty() * PaymentMethods::tokenRate() * 100);
    }

    public function stepTokens(int $delta): void
    {
        $this->tokens = max(1, min(TokenLedger::MAX_SALE, (int) $this->tokens + $delta));
    }

    public function setTokens(int $n): void
    {
        $this->tokens = max(1, min(TokenLedger::MAX_SALE, $n));
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

            $hadSession = ReceptionSession::currentFor($party->id) !== null;
            $participantId = $this->participantIds[0] ?? null;

            $tx = TokenLedger::sell($party, (int) $this->tokens, $this->payments, Auth::guard('admin')->id(), participantId: $participantId);

            RecentEntryParticipants::markSold($party->id, RecentEntryParticipants::TOKENS, $participantId);

            $this->done = [
                'title' => 'Tokeni vânduți',
                'line' => number_format($tx->tokens, 0, ',', '.').' tokeni',
                'total' => number_format((float) $tx->amount, 2, ',', '.'),
                'note' => $hadSession ? null : ReceptionSession::openedNotice($party->id),
            ];
            $this->tokens = 10;
            $this->payments = [['method' => 'cash', 'amount' => '']];
            $this->resetParticipants();
            $this->quickPickIds = [];
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $party = $this->currentParty();

        return view('livewire.reception.token-sale', [
            ...$this->saleViewData($party),
            'enabled' => PaymentMethods::isEnabled(PaymentMethods::TOKEN),
            'sellable' => PaymentMethods::tokensSellable(),
            'rate' => PaymentMethods::tokenRate(),
            'qty' => $this->qty(),
        ]);
    }
}
