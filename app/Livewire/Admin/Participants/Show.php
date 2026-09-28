<?php

namespace App\Livewire\Admin\Participants;

use App\Models\CreditTransaction;
use App\Models\Participant;
use App\Models\PartyEntry;
use App\Services\CreditLedger;
use App\Services\LoyaltyLedger;
use App\Services\ParticipantRegistry;
use App\Services\ParticipantStats;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Participanți - fundația). Fișa unui participant: date (editabile), numărul de intrări și istoricul lor.
 * Ștergerea e permisă doar fără intrări; altfel „Anonimizează” (golește numele și telefonul, păstrează numărătoarea).
 *
 * DXA: adaugat (Portofelul de credite - Etapa 1). Soldul de credite și acțiunile manuale din admin: încărcare,
 * ajustare, refund. Logica stă în App\Services\CreditLedger.
 *
 * DXA: adaugat (Card de fidelitate). Cardul activ (vizual + istoric), înrolare (o singură dată, cu opțiunea de
 * a prelua ștampile de pe un card fizic) și ajustare manuală de ștampile. Logica stă în App\Services\LoyaltyLedger.
 */
#[Layout('layouts.admin')]
class Show extends Component
{
    public Participant $participant;

    public string $name = '';

    public string $phone = '';

    public ?string $message = null;

    public ?string $error = null;

    public string $creditLoadAmount = '';

    public string $creditLoadNote = '';

    public string $creditAdjustAmount = '';

    public string $creditAdjustReason = '';

    public string $creditRefundAmount = '';

    public string $creditRefundReason = '';

    // DXA: adaugat (Card de fidelitate).
    public bool $enrollOpen = false;

    public string $enrollInitialStamps = '';

    public string $loyaltyAdjustDelta = '';

    public string $loyaltyAdjustReason = '';

    public function mount(Participant $participant): void
    {
        $this->participant = $participant;
        $this->syncFields();
    }

    private function run(callable $action, string $success): void
    {
        $this->message = $this->error = null;

        try {
            $action();
            $this->message = $success;
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    private function syncFields(): void
    {
        $this->name = $this->participant->isAnonymized() ? '' : $this->participant->name;
        $this->phone = (string) $this->participant->phone;
    }

    public function save(): void
    {
        $this->message = $this->error = null;

        try {
            ParticipantRegistry::update($this->participant, $this->name, $this->phone);
            $this->participant->refresh();
            $this->syncFields();
            $this->message = 'Datele au fost salvate.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function anonymize(): void
    {
        $this->message = $this->error = null;

        try {
            ParticipantRegistry::anonymize($this->participant);
            $this->participant->refresh();
            $this->syncFields();
            $this->message = 'Participantul a fost anonimizat.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function delete()
    {
        $this->message = $this->error = null;

        try {
            ParticipantRegistry::delete($this->participant);
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        session()->flash('status', 'Participantul a fost șters.');

        return $this->redirectRoute('admin.participants.index', navigate: true);
    }

    private function parseAmount(string $raw): ?float
    {
        $raw = trim(str_replace(',', '.', $raw));

        return preg_match('/^\d+(\.\d{1,2})?$/', $raw) ? (float) $raw : null;
    }

    public function loadCredits(): void
    {
        $amount = $this->parseAmount($this->creditLoadAmount);

        if ($amount === null || $amount <= 0) {
            $this->message = null;
            $this->error = 'Scrie o sumă validă de încărcat (ex. 50 sau 50.00).';

            return;
        }

        $this->run(function () use ($amount) {
            CreditLedger::load($this->participant, $amount, CreditTransaction::SOURCE_MANUAL, Auth::guard('admin')->id(), $this->creditLoadNote);
            $this->participant->refresh();
            $this->creditLoadAmount = '';
            $this->creditLoadNote = '';
        }, 'Creditele au fost încărcate.');
    }

    public function adjustCredits(): void
    {
        $raw = trim(str_replace([',', ' '], ['.', ''], $this->creditAdjustAmount));
        $amount = ($raw !== '' && preg_match('/^-?\d+(\.\d{1,2})?$/', $raw)) ? (float) $raw : null;

        if ($amount === null || $amount === 0.0) {
            $this->message = null;
            $this->error = 'Scrie o sumă validă de ajustat, diferită de 0 (negativ pentru scădere).';

            return;
        }

        $this->run(function () use ($amount) {
            CreditLedger::adjust($this->participant, $amount, $this->creditAdjustReason, Auth::guard('admin')->id());
            $this->participant->refresh();
            $this->creditAdjustAmount = '';
            $this->creditAdjustReason = '';
        }, 'Ajustarea a fost înregistrată.');
    }

    public function refundCredits(): void
    {
        $amount = $this->parseAmount($this->creditRefundAmount);

        if ($amount === null || $amount <= 0) {
            $this->message = null;
            $this->error = 'Scrie o sumă validă de refundat.';

            return;
        }

        $this->run(function () use ($amount) {
            CreditLedger::refund($this->participant, $amount, $this->creditRefundReason, Auth::guard('admin')->id());
            $this->participant->refresh();
            $this->creditRefundAmount = '';
            $this->creditRefundReason = '';
        }, 'Refundul a fost înregistrat.');
    }

    public function toggleEnroll(): void
    {
        $this->enrollOpen = ! $this->enrollOpen;
        $this->enrollInitialStamps = '';
        $this->error = null;
    }

    public function enrollLoyalty(): void
    {
        $raw = trim($this->enrollInitialStamps);
        $initial = $raw === '' ? 0 : (int) $raw;

        if ($raw !== '' && (! ctype_digit($raw) || (int) $raw < 0)) {
            $this->message = null;
            $this->error = 'Numărul de ștampile preluate trebuie să fie 0 sau mai mare.';

            return;
        }

        $this->run(function () use ($initial) {
            LoyaltyLedger::enroll($this->participant, Auth::guard('admin')->id(), $initial);
            $this->participant->refresh();
            $this->enrollOpen = false;
            $this->enrollInitialStamps = '';
        }, 'Participantul a fost înrolat la fidelitate.');
    }

    public function adjustLoyaltyStamps(): void
    {
        $raw = trim($this->loyaltyAdjustDelta);
        $delta = ($raw !== '' && preg_match('/^-?\d+$/', $raw)) ? (int) $raw : null;

        if ($delta === null || $delta === 0) {
            $this->message = null;
            $this->error = 'Scrie un număr întreg de ștampile, diferit de 0 (negativ pentru scădere).';

            return;
        }

        $this->run(function () use ($delta) {
            LoyaltyLedger::adjustStamps($this->participant, $delta, $this->loyaltyAdjustReason, Auth::guard('admin')->id());
            $this->participant->refresh();
            $this->loyaltyAdjustDelta = '';
            $this->loyaltyAdjustReason = '';
        }, 'Ajustarea de ștampile a fost înregistrată.');
    }

    public function render()
    {
        $entries = PartyEntry::query()
            ->where('participant_id', $this->participant->id)
            ->with('party:id,name,start_date')
            ->orderByDesc('entered_at')->orderByDesc('id')
            ->limit(50)
            ->get();

        $creditTransactions = $this->participant->creditTransactions()
            ->with('createdBy:id,name')
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit(50)
            ->get();

        $loyaltyEnrolled = $this->participant->isLoyaltyEnrolled();
        $loyaltyCard = $loyaltyEnrolled ? LoyaltyLedger::activeCard($this->participant) : null;
        $loyaltyStamps = $loyaltyCard
            ? $loyaltyCard->stamps()->whereNull('voided_at')->orderBy('stamped_at')->orderBy('id')->get()
            : collect();

        $loyaltyCardNumbers = [];
        $loyaltyHistory = collect();
        if ($loyaltyEnrolled) {
            $cards = $this->participant->loyaltyCards()
                ->orderBy('id')
                ->with(['stamps' => fn ($q) => $q->orderByDesc('stamped_at')->orderByDesc('id')->with('partyEntry.party:id,name')])
                ->get();

            foreach ($cards as $i => $c) {
                $loyaltyCardNumbers[$c->id] = $i + 1;
            }

            $rows = collect();
            foreach ($cards as $c) {
                // DXA: adaugat — ștampilele „manual_adjust" create în același apel (același `batch`) apar ca UN
                // singur rând (ex. „+5 ștampile"), nu unul per ștampilă. Cele de la intrări (`entry`) n-au batch
                // — fiecare rămâne rândul ei propriu, ca să arate petrecerea la care s-a făcut intrarea.
                foreach ($c->stamps->groupBy(fn ($s) => $s->batch ?? 'single-'.$s->id) as $group) {
                    $first = $group->first();
                    $activeCount = $group->filter(fn ($s) => ! $s->isVoided())->count();

                    $rows->push((object) [
                        'key' => $first->batch ? 'batch-'.$first->batch : 'stamp-'.$first->id,
                        'source' => $first->source,
                        'count' => $group->count(),
                        'activeCount' => $activeCount,
                        'stamped_at' => $group->max('stamped_at'),
                        'reason' => $first->reason,
                        'voided' => $activeCount === 0,
                        'void_reason' => optional($group->first(fn ($s) => $s->isVoided()))->void_reason,
                        'party_name' => $first->partyEntry?->party?->name,
                        'card_number' => $loyaltyCardNumbers[$c->id] ?? $c->id,
                    ]);
                }
            }

            $loyaltyHistory = $rows->sortByDesc(fn ($r) => $r->stamped_at)->take(30)->values();
        }

        return view('livewire.admin.participants.show', [
            'spend' => ParticipantStats::for($this->participant),
            'entries' => $entries,
            'totalEntries' => $this->participant->entries()->count(),
            'validEntries' => (int) $this->participant->entries()->active()->count(),
            'paidEntries' => (int) $this->participant->entries()->active()->where('price_paid', '>', 0)->count(),
            'freeEntries' => (int) $this->participant->entries()->active()->where('price_paid', '<=', 0)->count(),
            'firstAt' => $this->participant->entries()->active()->min('entered_at'),
            'lastAt' => $this->participant->entries()->active()->max('entered_at'),
            'creditBalance' => CreditLedger::balance($this->participant),
            'creditTransactions' => $creditTransactions,
            'totalCreditTransactions' => $this->participant->creditTransactions()->count(),
            // DXA: adaugat (Card de fidelitate).
            'loyaltyOn' => LoyaltyLedger::enabled(),
            'loyaltyEnrolled' => $loyaltyEnrolled,
            'loyaltyCard' => $loyaltyCard,
            'loyaltyCardNumber' => $loyaltyCard ? ($loyaltyCardNumbers[$loyaltyCard->id] ?? $loyaltyCard->id) : null,
            'loyaltyCardNumbers' => $loyaltyCardNumbers,
            'loyaltyStamps' => $loyaltyStamps,
            'loyaltyHistory' => $loyaltyHistory,
            'loyaltyArchived' => $loyaltyEnrolled ? LoyaltyLedger::archivedCards($this->participant) : collect(),
        ]);
    }
}
