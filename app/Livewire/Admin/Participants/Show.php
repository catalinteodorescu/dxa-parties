<?php

namespace App\Livewire\Admin\Participants;

use App\Models\CreditTransaction;
use App\Models\Participant;
use App\Models\PartyEntry;
use App\Services\CreditLedger;
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
        ]);
    }
}
