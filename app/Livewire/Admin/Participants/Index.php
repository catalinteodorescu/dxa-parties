<?php

namespace App\Livewire\Admin\Participants;

use App\Models\CreditTransaction;
use App\Models\Participant;
use App\Models\PartyEntry;
use App\Models\SalePayment;
use App\Services\LoyaltyLedger;
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
 *
 * DXA: adaugat (Card de fidelitate). Buton cu icon care deschide un popup DOAR-AFIȘARE cu cardul de fidelitate
 * al participantului (fără acțiuni — ajustarea/înrolarea rămân în fișa lui).
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** Sortare: name | entries | tokens (tokeni cheltuiți) | credits (credite cheltuite). */
    #[Url(as: 'sortare')]
    public string $sort = 'name';

    public bool $adding = false;

    public string $newName = '';

    public string $newPhone = '';

    public ?string $message = null;

    public ?string $error = null;

    // DXA: adaugat (Card de fidelitate) — popup doar-afișare.
    public ?int $loyaltyPopupParticipantId = null;

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

    /** Șterge (fără intrări) sau anonimizează (cu intrări) direct din listă — aceeași regulă ca în fișa participantului. */
    public function delete(int $id): void
    {
        $this->message = $this->error = null;

        try {
            ParticipantRegistry::delete(Participant::findOrFail($id));
            $this->message = 'Participantul a fost șters.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function anonymize(int $id): void
    {
        $this->message = $this->error = null;

        try {
            ParticipantRegistry::anonymize(Participant::findOrFail($id));
            $this->message = 'Participantul a fost anonimizat.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function showLoyaltyCard(int $id): void
    {
        $this->loyaltyPopupParticipantId = $id;
    }

    public function closeLoyaltyCard(): void
    {
        $this->loyaltyPopupParticipantId = null;
    }

    public function render()
    {
        $sort = in_array($this->sort, ['name', 'entries', 'tokens', 'credits'], true) ? $this->sort : 'name';
        $this->sort = $sort;
        $q = trim($this->search);
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        $phonePart = $digits !== '' ? ltrim(str_starts_with($digits, '0') ? substr($digits, 1) : $digits, '0') : '';

        // Metrici pe participant, calculate in query ca sa se poata sorta: intrari valabile, tokeni cheltuiti
        // (sale_payments cu metoda token), credite cheltuite (CreditTransaction::PAYMENT, neanulate).
        $participants = Participant::query()
            ->select('participants.*')
            ->selectSub(PartyEntry::query()->active()->whereColumn('party_entries.participant_id', 'participants.id')->selectRaw('COUNT(*)'), 'entries_count')
            ->selectSub(
                SalePayment::query()
                    ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                    ->whereColumn('sales.customer_id', 'participants.id')
                    ->where('sales.status', 'completed')
                    ->where('sale_payments.method', 'token')
                    ->selectRaw('COALESCE(SUM(sale_payments.tokens), 0)'),
                'tokens_spent'
            )
            ->selectSub(
                CreditTransaction::query()->active()->where('type', CreditTransaction::PAYMENT)
                    ->whereColumn('credit_transactions.participant_id', 'participants.id')
                    ->selectRaw('COALESCE(SUM(-amount), 0)'),
                'credits_spent'
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
            ->when($this->sort === 'entries', fn ($query) => $query->orderByDesc('entries_count'))
            ->when($this->sort === 'tokens', fn ($query) => $query->orderByDesc('tokens_spent'))
            ->when($this->sort === 'credits', fn ($query) => $query->orderByDesc('credits_spent'))
            ->orderBy('name')
            ->paginate(15);

        $loyaltyPopupParticipant = null;
        $loyaltyPopupCard = null;
        $loyaltyPopupStamps = collect();
        $loyaltyPopupCardNumber = null;
        $loyaltyPopupLastAt = null;
        if ($this->loyaltyPopupParticipantId) {
            $loyaltyPopupParticipant = Participant::query()->find($this->loyaltyPopupParticipantId);
            if ($loyaltyPopupParticipant && $loyaltyPopupParticipant->isLoyaltyEnrolled()) {
                $loyaltyPopupCard = LoyaltyLedger::activeCard($loyaltyPopupParticipant);
                if ($loyaltyPopupCard) {
                    $loyaltyPopupStamps = $loyaltyPopupCard->stamps()->whereNull('voided_at')->orderBy('stamped_at')->orderBy('id')->get();
                    $loyaltyPopupCardNumber = $loyaltyPopupParticipant->loyaltyCards()->where('id', '<=', $loyaltyPopupCard->id)->count();
                    $loyaltyPopupLastAt = $loyaltyPopupParticipant->entries()->active()->max('entered_at');
                }
            }
        }

        return view('livewire.admin.participants.index', [
            'participants' => $participants,
            'sortOptions' => [
                'name' => 'Nume',
                'entries' => 'Intrări',
                'tokens' => 'Tokeni cheltuiți',
                'credits' => 'Credite cheltuite',
            ],
            'total' => Participant::query()->whereNull('anonymized_at')->count(),
            // DXA: adaugat (Card de fidelitate).
            'loyaltyOn' => LoyaltyLedger::enabled(),
            'loyaltyPopupParticipant' => $loyaltyPopupParticipant,
            'loyaltyPopupCard' => $loyaltyPopupCard,
            'loyaltyPopupStamps' => $loyaltyPopupStamps,
            'loyaltyPopupCardNumber' => $loyaltyPopupCardNumber,
            'loyaltyPopupLastAt' => $loyaltyPopupLastAt,
        ]);
    }
}
