<?php

namespace App\Livewire\Admin\Participants;

use App\Models\Participant;
use App\Models\PartyEntry;
use App\Models\SalePayment;
use App\Models\TokenTransaction;
use App\Services\ParticipantRegistry;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (Participanți - fundația). Lista participanților identificați (adăugați manual la recepție acum;
 * conturi din aplicație mai târziu), cu căutare după nume/telefon și adăugare. Logica: ParticipantRegistry.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** Sortare: name | bar (cheltuit la bar) | tokens (tokeni cumpărați) | entries | last (ultima intrare). */
    #[Url(as: 'sortare')]
    public string $sort = 'name';

    public bool $adding = false;

    public string $newName = '';

    public string $newPhone = '';

    public ?string $message = null;

    public ?string $error = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function toggleAdd(): void
    {
        $this->adding = ! $this->adding;
        $this->message = $this->error = null;
    }

    public function create(): void
    {
        $this->message = $this->error = null;

        try {
            $p = ParticipantRegistry::create($this->newName, $this->newPhone, Auth::guard('admin')->id());
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->message = 'Participantul „'.$p->name.'” a fost adăugat.';
        $this->newName = $this->newPhone = '';
        $this->adding = false;
    }

    public function render()
    {
        $sort = in_array($this->sort, ['name', 'bar', 'tokens', 'entries', 'last'], true) ? $this->sort : 'name';
        $this->sort = $sort;
        $q = trim($this->search);
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        $phonePart = $digits !== '' ? ltrim(str_starts_with($digits, '0') ? substr($digits, 1) : $digits, '0') : '';

        // Metrici pe participant, calculate in query ca sa se poata sorta: intrari valabile, cheltuit la bar (fara beneficii),
        // tokeni cumparati, ultima intrare (definitiile: App\Services\ParticipantStats).
        $participants = Participant::query()
            ->select('participants.*')
            ->selectSub(PartyEntry::query()->active()->whereColumn('party_entries.participant_id', 'participants.id')->selectRaw('COUNT(*)'), 'entries_count')
            ->selectSub(PartyEntry::query()->active()->whereColumn('party_entries.participant_id', 'participants.id')->selectRaw('MAX(entered_at)'), 'last_at')
            ->selectSub(
                SalePayment::query()
                    ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                    ->whereColumn('sales.customer_id', 'participants.id')
                    ->where('sales.status', 'completed')
                    ->where('sale_payments.method', '!=', 'benefit')
                    ->selectRaw('COALESCE(SUM(sale_payments.amount), 0)'),
                'bar_spent'
            )
            ->selectSub(
                TokenTransaction::query()->active()->where('type', TokenTransaction::SOLD)
                    ->whereColumn('token_transactions.participant_id', 'participants.id')
                    ->selectRaw('COALESCE(SUM(tokens), 0)'),
                'tokens_bought'
            )
            ->when($q !== '', function ($query) use ($q, $phonePart) {
                $query->where(function ($w) use ($q, $phonePart) {
                    $w->where('name', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%');
                    if (mb_strlen($phonePart) >= 3) {
                        $w->orWhere('phone', 'like', '%'.$phonePart.'%');
                    }
                });
            })
            ->orderByRaw('anonymized_at IS NOT NULL')
            ->when($this->sort === 'bar', fn ($query) => $query->orderByDesc('bar_spent'))
            ->when($this->sort === 'tokens', fn ($query) => $query->orderByDesc('tokens_bought'))
            ->when($this->sort === 'entries', fn ($query) => $query->orderByDesc('entries_count'))
            ->when($this->sort === 'last', fn ($query) => $query->orderByRaw('last_at IS NULL')->orderByDesc('last_at'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.participants.index', [
            'participants' => $participants,
            'sortOptions' => [
                'name' => 'Nume',
                'bar' => 'Cheltuit la bar',
                'tokens' => 'Tokeni cumpărați',
                'entries' => 'Intrări',
                'last' => 'Ultima intrare',
            ],
            'total' => Participant::query()->whereNull('anonymized_at')->count(),
        ]);
    }
}
