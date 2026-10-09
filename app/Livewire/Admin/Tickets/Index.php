<?php

namespace App\Livewire\Admin\Tickets;

use App\Models\Party;
use App\Services\AdminTickets;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (runda 66). Pagina „Bilete” din admin: toate biletele cumpărate din aplicație, peste toate petrecerile, cu căutare
 * (telefon, nume, cod de bilet, #comandă) și filtre. Doar citire; detaliul unui bilet e în Tickets\Show.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'petrecere')]
    public string $filterParty = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    #[Url(as: 'plata')]
    public string $filterPayment = '';

    #[Url(as: 'din')]
    public string $dateFrom = '';

    #[Url(as: 'pana')]
    public string $dateTo = '';

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'filterParty', 'filterStatus', 'filterPayment', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'filterParty', 'filterStatus', 'filterPayment', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function render()
    {
        $hasFilters = $this->search !== '' || $this->filterParty !== '' || $this->filterStatus !== '' || $this->filterPayment !== '' || $this->dateFrom !== '' || $this->dateTo !== '';

        return view('livewire.admin.tickets.index', [
            'tickets' => AdminTickets::query($this->search, $this->filterParty, $this->filterStatus, $this->filterPayment, $this->dateFrom, $this->dateTo)->paginate(25),
            'partyOptions' => ['' => 'Toate petrecerile'] + Party::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'statusOptions' => ['' => 'Toate statusurile'] + AdminTickets::STATUS_LABELS,
            'paymentOptions' => ['' => 'Orice plată'] + AdminTickets::PAYMENT_LABELS,
            'hasFilters' => $hasFilters,
        ]);
    }
}
