<?php

namespace App\Livewire\Reception;

use App\Livewire\Reception\Concerns\UsesReceptionParty;
use App\Models\PartyEntry;
use App\Models\ReceptionSession;
use App\Services\CreditLedger;
use App\Services\EntryRecorder;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Recepție - Etapa 4). „Tranzacții”: intrările, vânzările de tokeni și de credite ale
 * sesiunii de recepție DESCHISE, cele mai noi primele, cu anulare (motiv obligatoriu). Anularea se poate doar cât
 * sesiunea e deschisă (regulă aplicată de servicii: EntryRecorder / TokenLedger / CreditLedger) și doar pentru
 * operațiuni ale sesiunii curente a petrecerii alese. Închiderea casei rămâne în admin (raportări).
 */
#[Layout('layouts.reception')]
class Recent extends Component
{
    use UsesReceptionParty;

    public ?string $message = null;

    public ?string $error = null;

    /**
     * @param  string  $kind  entry | token | credit
     * @param  string  $target  batch (intrări) sau id (tokeni / credite)
     */
    public function cancelOperation(string $kind, string $target, string $reason): void
    {
        $this->message = $this->error = null;

        try {
            $party = $this->currentParty();
            $session = $party ? ReceptionSession::currentFor($party->id) : null;
            if (! $session) {
                throw new DomainException('Sesiunea de recepție e închisă: operațiunile nu se mai pot anula de aici.');
            }

            $adminId = Auth::guard('admin')->id();

            match ($kind) {
                'entry' => $this->cancelEntry($session, $target, $reason, $adminId),
                'token' => $this->cancelToken($session, (int) $target, $reason, $adminId),
                'credit' => $this->cancelCredit($session, (int) $target, $reason, $adminId),
                default => throw new DomainException('Operațiune necunoscută.'),
            };
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    private function cancelEntry(ReceptionSession $session, string $batch, string $reason, ?int $adminId): void
    {
        if (! $session->entries()->where('batch', $batch)->exists()) {
            throw new DomainException('Intrarea nu aparține sesiunii deschise.');
        }

        $n = EntryRecorder::cancelBatch($batch, $reason, $adminId);
        $this->message = $n === 1 ? 'Intrarea a fost anulată.' : "Cele {$n} intrări au fost anulate.";
    }

    private function cancelToken(ReceptionSession $session, int $id, string $reason, ?int $adminId): void
    {
        if (! $session->tokenSales()->whereKey($id)->exists()) {
            throw new DomainException('Vânzarea nu aparține sesiunii deschise.');
        }

        TokenLedger::cancel($id, $reason, $adminId);
        $this->message = 'Vânzarea de tokeni a fost anulată.';
    }

    private function cancelCredit(ReceptionSession $session, int $id, string $reason, ?int $adminId): void
    {
        if (! $session->creditSales()->whereKey($id)->exists()) {
            throw new DomainException('Vânzarea nu aparține sesiunii deschise.');
        }

        CreditLedger::cancel($id, $reason, $adminId);
        $this->message = 'Vânzarea de credite a fost anulată.';
    }

    /** @return Collection<int, object> operațiunile sesiunii, cele mai noi primele */
    private function operations(ReceptionSession $session)
    {
        $labels = PaymentMethods::labels();
        $methods = function ($payments) use ($labels) {
            $by = [];
            foreach ($payments as $p) {
                $by[$p->method] = round(($by[$p->method] ?? 0) + (float) $p->amount, 2);
            }

            return collect($by)->map(fn ($amount, $m) => ($labels[$m] ?? $m).' '.number_format($amount, 2, ',', '.'))->implode(' · ');
        };

        $entries = PartyEntry::query()->where('reception_session_id', $session->id)
            ->with(['payments', 'participant'])
            ->orderBy('id')->get()
            ->groupBy('batch')
            ->map(function ($group) use ($methods) {
                $first = $group->first();
                $total = round((float) $group->sum('price_paid'), 2);

                return (object) [
                    'kind' => 'entry',
                    'key' => (string) $first->batch,
                    'at' => $first->entered_at,
                    'label' => 'Intrare',
                    'title' => $group->count().' × '.$first->ticket_type,
                    'amount' => $total,
                    'methods' => $methods($group->flatMap->payments),
                    'people' => $group->pluck('participant.name')->filter()->values()->all(),
                    'cancelled' => $first->isCancelled(),
                    'cancel_reason' => $first->cancel_reason,
                ];
            })->values();

        $tokens = $session->tokenSales()->with(['payments', 'participant'])->get()->map(fn ($t) => (object) [
            'kind' => 'token',
            'key' => (string) $t->id,
            'at' => $t->occurred_at,
            'label' => 'Tokeni',
            'title' => number_format($t->tokens, 0, ',', '.').' tokeni',
            'amount' => round((float) $t->amount, 2),
            'methods' => $methods($t->payments),
            'people' => array_filter([$t->participant?->name]),
            'cancelled' => $t->isCancelled(),
            'cancel_reason' => $t->cancel_reason,
        ]);

        $credits = $session->creditSales()->with(['payments', 'participant'])->get()->map(fn ($c) => (object) [
            'kind' => 'credit',
            'key' => (string) $c->id,
            'at' => $c->occurred_at,
            'label' => 'Credite',
            'title' => number_format((float) $c->amount, 2, ',', '.').' lei credite',
            'amount' => round((float) $c->amount, 2),
            'methods' => $methods($c->payments),
            'people' => array_filter([$c->participant?->name]),
            'cancelled' => $c->isCancelled(),
            'cancel_reason' => $c->cancel_reason,
        ]);

        return $entries->concat($tokens)->concat($credits)->sortByDesc(fn ($o) => $o->at?->timestamp ?? 0)->values();
    }

    public function render()
    {
        $party = $this->currentParty();
        $session = $party ? ReceptionSession::currentFor($party->id) : null;

        return view('livewire.reception.recent', [
            'party' => $party,
            'session' => $session,
            'operations' => $session ? $this->operations($session) : collect(),
        ]);
    }
}
