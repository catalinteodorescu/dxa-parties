<?php

namespace App\Livewire\Admin\Reception;

use App\Livewire\Admin\Concerns\PicksParticipants;
use App\Models\Party;
use App\Models\ReceptionSession;
use App\Services\PartyStats;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * DXA: adaugat (Recepție - tokeni). Cardul „Vinde tokeni” din ecranul Recepție (montat cu petrecerea aleasă).
 * Plata se introduce pe total, poate fi mixtă, fără credite (tokenii nu se cumpără cu credite). Vânzarea e
 * disponibilă doar cât tokenii sunt în starea „Activ”. Logica stă în App\Services\TokenLedger.
 * Lista ultimelor vânzări (cu anulare) e în coloana din dreapta a ecranului Recepție (Index); între ele se anunță
 * evenimentul „tokens-changed”.
 */
class TokenSale extends Component
{
    use PicksParticipants;

    public int $partyId;

    public int|string $tokens = 10;

    /** @var array<int, array{method: string, amount: string}> plata pe TOTALUL vânzării */
    public array $payments = [['method' => 'cash', 'amount' => '']];

    public ?string $message = null;

    public ?string $error = null;

    /** Id-urile participanților din ultima intrare înregistrată pe această petrecere (chip-uri rapide, fără căutare). */
    public array $quickPickIds = [];

    public function mount(int $partyId): void
    {
        $this->partyId = $partyId;
    }

    /** Anunțat de Reception\Form după o intrare cu participanți identificați: oferă alegerea lor ca „scurtătură”. */
    #[On('entry-recorded')]
    public function onEntryRecorded(int $partyId, array $participantIds): void
    {
        if ($partyId === $this->partyId) {
            $this->quickPickIds = $participantIds;
        }
    }

    /** Alege direct un participant din chip-urile rapide (fără căutare) — un singur tap. */
    public function pickQuickParticipant(int $id): void
    {
        $this->addParticipant($id);
        $this->quickPickIds = [];
    }

    private function party(): ?Party
    {
        return Party::find($this->partyId);
    }

    /** @return array<string, string> */
    private function methods(): array
    {
        return array_diff_key(PaymentMethods::forEntry($this->party()), [PaymentMethods::CREDIT => true]);
    }

    private function qty(): int
    {
        return max(1, min(TokenLedger::MAX_SALE, (int) $this->tokens));
    }

    private function totalCents(): int
    {
        return (int) round($this->qty() * PaymentMethods::tokenRate() * 100);
    }

    public function stepTokens(int $delta): void
    {
        $this->tokens = max(1, min(TokenLedger::MAX_SALE, (int) $this->tokens + $delta));
    }

    public function setTokens(int $n): void
    {
        $this->tokens = max(1, min(TokenLedger::MAX_SALE, $n));
    }

    /** „Restul”: completează în rândul $i suma rămasă de încasat (ca la bar). */
    public function fillRemaining(int $i): void
    {
        $others = 0;
        foreach ($this->payments as $k => $p) {
            if ($k === $i) {
                continue;
            }
            $raw = str_replace(',', '.', trim((string) ($p['amount'] ?? '')));
            $others += is_numeric($raw) && (float) $raw > 0 ? (int) round((float) $raw * 100) : 0;
        }

        $remaining = max(0, $this->totalCents() - $others);
        $this->payments[$i]['amount'] = $remaining > 0 ? ($remaining % 100 === 0 ? (string) intdiv($remaining, 100) : number_format($remaining / 100, 2, '.', '')) : '';
    }

    public function addPayment(): void
    {
        $this->payments[] = ['method' => array_key_first($this->methods()) ?? 'cash', 'amount' => ''];
    }

    public function removePayment(int $i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments) ?: [['method' => 'cash', 'amount' => '']];
    }

    public function payAll(string $method): void
    {
        $total = $this->totalCents() / 100;
        $this->payments = [['method' => $method, 'amount' => $total == floor($total) ? (string) (int) $total : number_format($total, 2, '.', '')]];
    }

    public function sell(): void
    {
        $this->message = $this->error = null;
        $this->participantError = null;
        $party = $this->party();

        try {
            if (! $party) {
                throw new DomainException('Alege o petrecere.');
            }

            $hadSession = ReceptionSession::currentFor($party->id) !== null;

            $tx = TokenLedger::sell(
                $party,
                (int) $this->tokens,
                $this->payments,
                Auth::guard('admin')->id(),
                participantId: $this->participantIds[0] ?? null,
            );

            $this->message = sprintf('Vândut: %s tokeni — %s lei.', number_format($tx->tokens, 0, ',', '.'), number_format((float) $tx->amount, 2, ',', '.'));
            if (! $hadSession) {
                $this->message .= ' '.ReceptionSession::openedNotice($party->id);
            }
            $this->tokens = 10;
            $this->payments = [['method' => 'cash', 'amount' => '']];
            $this->resetParticipants();
            $this->quickPickIds = [];
            $this->dispatch('tokens-changed'); // reafiseaza listele din ecranul Receptie
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    /** Vânzare anulată din lista din dreapta (Index): reafișează circulația. */
    #[On('tokens-changed')]
    public function refresh(): void
    {
        // doar re-randare
    }

    public function render()
    {
        $party = $this->party();

        $paid = 0;
        foreach ($this->payments as $p) {
            $raw = str_replace(',', '.', trim((string) ($p['amount'] ?? '')));
            $paid += is_numeric($raw) && (float) $raw > 0 ? (int) round((float) $raw * 100) : 0;
        }
        $total = $this->totalCents();

        return view('livewire.admin.reception.token-sale', [
            ...$this->participantPickerData(),
            'quickPicks' => $this->quickPickIds
                ? \App\Models\Participant::query()->whereIn('id', $this->quickPickIds)
                    ->whereNotIn('id', $this->participantIds)
                    ->get(['id', 'name'])
                : collect(),
            'paid' => $paid / 100,
            'party' => $party,
            'enabled' => PaymentMethods::isEnabled(PaymentMethods::TOKEN),
            'sellable' => PaymentMethods::tokensSellable(),
            'rate' => PaymentMethods::tokenRate(),
            'methods' => $this->methods(),
            'methodLabels' => PaymentMethods::labels(),
            'qty' => $this->qty(),
            'total' => $total / 100,
            'rest' => ($total - $paid) / 100,
            'circulation' => TokenLedger::circulation(),
            'totals' => $party ? TokenLedger::partyTotals($party) : null,
        ]);
    }
}
