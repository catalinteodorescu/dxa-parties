<?php

namespace App\Livewire\Admin\Reception;

use App\Models\Party;
use App\Models\PartyEntry;
use App\Services\EntryRecorder;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (Recepție - intrări = listă). Toate intrările, oricând sau filtrate pe petrecere / stare / metodă /
 * interval de date — ca App\Livewire\Admin\Sales\Index la bar. Un rând = un grup (batch): 1-50 persoane înregistrate
 * odată, cu plata lor. Creare: „Intrare nouă” (App\Livewire\Admin\Reception\Form), unde se vând și tokeni.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $party = ''; // '' | id

    #[Url]
    public string $state = 'all'; // all | active | cancelled

    #[Url]
    public string $method = 'all'; // all | cash | card | ...

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    /** Mesajele popup-ului de anulare (afișate lângă listă). */
    public ?string $cancelMessage = null;

    public ?string $cancelError = null;

    public function updated($name): void
    {
        if (in_array($name, ['party', 'state', 'method', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('party', 'state', 'method', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function cancel(string $batch, string $reason): void
    {
        $this->cancelMessage = $this->cancelError = null;

        try {
            $n = EntryRecorder::cancelBatch($batch, $reason, Auth::guard('admin')->id());
            $this->cancelMessage = $n === 1 ? 'Intrarea a fost anulată.' : "Cele {$n} intrări au fost anulate.";
        } catch (DomainException $e) {
            $this->cancelError = $e->getMessage();
        }
    }

    /** Query pe intrări (fără filtrul de stare), pentru reutilizare la listă și la rezumat. */
    private function baseQuery()
    {
        return PartyEntry::query()
            ->when($this->party !== '', fn ($q) => $q->where('party_id', (int) $this->party))
            ->when($this->method !== 'all', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('method', $this->method)))
            ->when($this->dateFrom !== '', fn ($q) => $q->where('entered_at', '>=', Carbon::parse($this->dateFrom)->startOfDay()))
            ->when($this->dateTo !== '', fn ($q) => $q->where('entered_at', '<=', Carbon::parse($this->dateTo)->endOfDay()));
    }

    private function filtered()
    {
        return $this->baseQuery()
            ->when($this->state === 'active', fn ($q) => $q->whereNull('cancelled_at'))
            ->when($this->state === 'cancelled', fn ($q) => $q->whereNotNull('cancelled_at'));
    }

    public function render()
    {
        // Paginam pe BATCH (un grup = un rand), nu pe persoana: numaram batch-urile distincte, apoi
        // hidratam doar cele de pe pagina curenta cu toate randurile lor.
        $batchesPage = (clone $this->filtered())
            ->select('batch')
            ->selectRaw('MAX(entered_at) as last_entered_at')
            ->groupBy('batch')
            ->orderByDesc('last_entered_at')
            ->paginate(15);

        $batchIds = collect($batchesPage->items())->pluck('batch');

        $rowsByBatch = PartyEntry::query()->whereIn('batch', $batchIds)
            ->with(['payments', 'creator', 'participant', 'party'])
            ->get()
            ->groupBy('batch');

        $batches = collect($batchesPage->items())->map(function ($row) use ($rowsByBatch) {
            $group = $rowsByBatch->get($row->batch, collect());
            $first = $group->first();
            if (! $first) {
                return null;
            }

            $byMethod = [];
            foreach ($group->flatMap->payments as $pay) {
                $byMethod[$pay->method] = round(($byMethod[$pay->method] ?? 0) + (float) $pay->amount, 2);
            }

            return (object) [
                'batch' => $first->batch,
                'party' => $first->party,
                'count' => $group->count(),
                'ticket' => $first->ticket_type,
                'at' => $first->entered_at,
                'total' => round((float) $group->sum('price_paid'), 2),
                'payments' => $byMethod,
                'by' => $first->creator?->name,
                'participants' => $group->sortBy('id')->pluck('participant.name')->filter()->values()->all(),
                'override_reason' => $first->override_reason,
                'grace' => $first->grace_applied,
                'cancelled' => $first->isCancelled(),
                'cancel_reason' => $first->cancel_reason,
            ];
        })->filter()->values();

        $activeBase = (clone $this->baseQuery())->whereNull('cancelled_at');
        $summary = [
            'entries' => (int) $activeBase->count(),
            'batches' => (int) (clone $activeBase)->distinct('batch')->count('batch'),
            'revenue' => round((float) (clone $activeBase)->sum('price_paid'), 2),
        ];

        $partyIds = PartyEntry::query()->distinct()->pluck('party_id');

        return view('livewire.admin.reception.index', [
            'batches' => $batches,
            'paginator' => $batchesPage,
            'summary' => $summary,
            'parties' => Party::whereIn('id', $partyIds)->orderByDesc('starts_at')->get(),
            'methodLabels' => PaymentMethods::labels(),
        ]);
    }
}
