<?php

namespace App\Livewire\Participant;

use App\Models\CreditTransaction;
use App\Services\CreditLedger;
use App\Support\PaymentMethods;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților - runda 16). Pagina „Portofel”: numărul de credite (evidențiat), butonul „Încarcă”
 * și lista tranzacțiilor (încărcări, plăți, refund-uri, ajustări), cu lazy load la defilare (câte 10). Încărcarea (runda 51) se face pe pagina separată `app.wallet.load` (Livewire\Participant\Topup).
 */
#[Layout('layouts.participant', ['title' => 'Portofel'])]
class Wallet extends Component
{
    public int $limit = 10;

    public function more(): void
    {
        $this->limit += 10;
    }

    public function render()
    {
        $me = auth('participant')->user();
        $moves = fn () => CreditTransaction::query()->where('participant_id', $me->id)->whereNull('cancelled_at');

        $purchasable = PaymentMethods::creditsPurchasable();

        return view('livewire.participant.wallet', [
            'purchasable' => $purchasable,
            'balance' => CreditLedger::balance($me),
            'moves' => $moves()->orderByDesc('occurred_at')->orderByDesc('id')->limit($this->limit)->get(),
            'hasMore' => $moves()->count() > $this->limit,
        ]);
    }
}
