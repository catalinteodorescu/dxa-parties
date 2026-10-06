<?php

namespace App\Livewire\Admin\Parties;

use App\Models\Party;
use App\Services\PartyAttendees;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (runda 43). „Participanți” la o petrecere: cine are bilet, cine a intrat, cine e așteptat. Doar afișare.
 */
#[Layout('layouts.admin')]
class Attendees extends Component
{
    use WithPagination;

    public const PER_PAGE = 25;

    public Party $party;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'situatie')]
    public string $status = '';

    public function mount(Party $party): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $this->party = $party;
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function render()
    {
        $data = PartyAttendees::for($this->party);
        $rows = PartyAttendees::filter($data->rows, $this->status, $this->search);
        $page = $this->getPage();

        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );

        return view('livewire.admin.parties.attendees', [
            'summary' => $data->summary,
            'people' => $paginator,
            'totalPeople' => $data->rows->count(),
            'hasFilters' => $this->search !== '' || $this->status !== '',
            'statusOptions' => [
                '' => 'Toate situațiile',
                PartyAttendees::ENTERED => 'Au intrat',
                PartyAttendees::WAITING => 'Cu bilet, încă neintrați',
                PartyAttendees::PHONE => 'Doar telefon (fără cont)',
            ],
        ]);
    }
}
