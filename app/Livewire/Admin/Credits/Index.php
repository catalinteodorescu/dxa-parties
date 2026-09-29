<?php

namespace App\Livewire\Admin\Credits;

use App\Models\CreditTransaction;
use App\Models\Party;
use App\Services\CreditsOverview;
use App\Support\PaymentMethods;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (Credite - pagina „Credite", pe modelul paginii Carduri). Doar afișare: sumar all-time, distribuții
 * (încărcat pe sursă, cheltuit pe destinație), participanții cu cel mai mare sold și istoricul filtrabil al
 * mișcărilor. Corecțiile (încărcare/ajustare/refund) rămân în fișa fiecărui participant (CreditLedger).
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tip')]
    public string $filterType = '';

    #[Url(as: 'sursa')]
    public string $filterSource = '';

    #[Url(as: 'petrecere')]
    public string $filterParty = '';

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSource(): void
    {
        $this->resetPage();
    }

    public function updatedFilterParty(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $partyOptions = ['' => 'Toate petrecerile'] + Party::query()->orderByDesc('start_date')->pluck('name', 'id')->all();

        return view('livewire.admin.credits.index', [
            'creditsOn' => PaymentMethods::isEnabled(PaymentMethods::CREDIT),
            'purchasable' => PaymentMethods::creditsPurchasable(),
            'summary' => CreditsOverview::summary(),
            'loadedBySource' => CreditsOverview::loadedBySource(),
            'top' => CreditsOverview::topBalances(),
            'typeOptions' => ['' => 'Toate tipurile'] + CreditTransaction::TYPE_LABELS,
            'sourceOptions' => ['' => 'Toate sursele'] + CreditTransaction::SOURCE_LABELS,
            'partyOptions' => $partyOptions,
            'transactions' => CreditsOverview::history($this->filterType, $this->filterSource, $this->filterParty)->paginate(25),
        ]);
    }
}
