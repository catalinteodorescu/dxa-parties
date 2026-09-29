<?php

namespace App\Livewire\Admin\BarReports;

use App\Livewire\Admin\BarReports\Concerns\ReopensBarReport;
use App\Models\BarReport;
use App\Models\Party;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (Bar - raportări casă). Lista raportărilor de casă trimise din aplicația de bar (și a celor încă în lucru),
 * cu cifrele de cash/tokeni, detaliu, PDF și redeschidere. Cele care așteaptă adminul apar primele.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use ReopensBarReport;
    use WithPagination;

    #[Url]
    public string $status = 'all';   // all | awaiting | draft | closed

    #[Url]
    public string $party = '';

    public function updated($name): void
    {
        if (in_array($name, ['status', 'party'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->status = 'all';
        $this->party = '';
        $this->resetPage();
    }

    public function render()
    {
        $reports = BarReport::query()
            ->with(['party', 'group', 'submitter'])
            ->when($this->status === 'awaiting', fn ($q) => $q->awaitingAdmin())
            ->when($this->status === 'draft', fn ($q) => $q->whereNull('submitted_at'))
            ->when($this->status === 'closed', fn ($q) => $q->whereHas('group', fn ($g) => $g->where('status', 'closed')))
            ->when($this->party !== '', fn ($q) => $q->where('party_id', (int) $this->party))
            ->orderByRaw('CASE WHEN submitted_at IS NOT NULL AND EXISTS (SELECT 1 FROM sales_groups g WHERE g.id = bar_reports.sales_group_id AND g.status = ?) THEN 0 ELSE 1 END', ['open'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15);

        $parties = Party::whereIn('id', BarReport::query()->whereNotNull('party_id')->distinct()->pluck('party_id'))->orderByDesc('starts_at')->get();

        return view('livewire.admin.bar-reports.index', [
            'reports' => $reports,
            'parties' => $parties,
            'statuses' => ['awaiting' => 'Așteaptă adminul', 'draft' => 'În lucru', 'closed' => 'Sesiune închisă'],
            'flashMessage' => $this->reopenMessage,
            'flashError' => $this->reopenError,
        ]);
    }
}
