<?php

namespace App\Livewire\Admin\Credits;

use App\Models\CreditTopup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (runda 76). „Încărcări cu cardul”: lista încercărilor de a încărca credite cu cardul, cu starea și referința Stripe.
 * Doar afișare: o încărcare plătită intră singură în ledger (webhook), una neplătită se închide singură după expirare.
 */
#[Layout('layouts.admin')]
class Topups extends Component
{
    use WithPagination;

    public const STATES = [
        CreditTopup::PENDING => 'În așteptare',
        CreditTopup::PAID => 'Plătite',
        CreditTopup::EXPIRED => 'Expirate',
        CreditTopup::FAILED => 'Eșuate',
        CreditTopup::CANCELLED => 'Anulate',
    ];

    #[Url(as: 'stare')]
    public string $filterState = '';

    public function updatedFilterState(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $counts = CreditTopup::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('livewire.admin.credits.topups', [
            'stateOptions' => ['' => 'Toate stările'] + self::STATES,
            'counts' => $counts,
            'topups' => CreditTopup::query()->with('participant')
                ->when(isset(self::STATES[$this->filterState]), fn ($q) => $q->where('status', $this->filterState))
                ->latest('id')->paginate(25),
        ]);
    }
}
