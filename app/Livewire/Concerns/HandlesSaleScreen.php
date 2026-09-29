<?php

namespace App\Livewire\Concerns;

use App\Livewire\Admin\Concerns\PicksParticipants;
use App\Models\Participant;
use App\Models\Party;
use App\Services\EntryRecorder;
use App\Support\PaymentMethods;
use App\Support\RecentEntryParticipants;

/**
 * DXA: adaugat (PWA Recepție - Etapa 3). Partea comună ecranelor de vânzare din PWA (Tokeni / Credite):
 * plata pe total (poate fi mixtă), participantul și sugestiile (un tap) din ultima intrare.
 * Componenta gazdă trebuie să definească currentParty(), totalCents() și saleKind() (RecentEntryParticipants::TOKENS|CREDITS).
 */
trait HandlesSaleScreen
{
    use PicksParticipants;

    /** @var array<int, array{method: string, amount: string}> plata pe TOTALUL vânzării */
    public array $payments = [['method' => 'cash', 'amount' => '']];

    public ?string $error = null;

    /** Confirmarea de după vânzare (ecran plin): title, line, total, note. */
    public ?array $done = null;

    /** Sugestii: participanții din ultima intrare (când sunt mai mulți). */
    public array $quickPickIds = [];

    public bool $recentLoaded = false;

    abstract protected function saleKind(): string;

    abstract protected function totalCents(): int;

    /** Metodele de plată ale vânzării (fără credite: nu se cumpără tokeni / credite cu credite). */
    protected function saleMethods(): array
    {
        $party = $this->currentParty();

        return array_diff_key($party ? PaymentMethods::forEntry($party) : [], [PaymentMethods::CREDIT => true]);
    }

    /** Participanții din ultima intrare apar ca sugestii. Se face o singură dată, la prima randare. */
    protected function loadRecentParticipants(): void
    {
        if ($this->recentLoaded || ! $this->partyId) {
            return;
        }
        $this->recentLoaded = true;

        $ids = RecentEntryParticipants::for($this->partyId, $this->saleKind());

        // Niciodată precompletat: chiar și un singur participant apare ca sugestie (un tap), ca să nu se vândă din greșeală.
        $this->quickPickIds = $ids;
    }

    public function pickQuickParticipant(int $id): void
    {
        $this->addParticipant($id);
        $this->quickPickIds = [];
    }

    public function dismissDone(): void
    {
        $this->done = null;
    }

    protected function paidCents(): int
    {
        $paid = 0;
        foreach ($this->payments as $p) {
            $raw = str_replace(',', '.', trim((string) ($p['amount'] ?? '')));
            $paid += is_numeric($raw) && (float) $raw > 0 ? (int) round((float) $raw * 100) : 0;
        }

        return $paid;
    }

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
        $this->payments[] = ['method' => array_key_first($this->saleMethods()) ?? 'cash', 'amount' => ''];
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

    /** Datele comune ale view-urilor de vânzare. */
    protected function saleViewData(?Party $party): array
    {
        $this->loadRecentParticipants();

        $methods = $this->saleMethods();
        $total = $this->totalCents();
        $paid = $this->paidCents();

        return [
            ...$this->participantPickerData($party),
            'quickPicks' => $this->quickPickIds
                ? Participant::query()->whereIn('id', $this->quickPickIds)->whereNotIn('id', $this->participantIds)->get(['id', 'name'])
                : collect(),
            'party' => $party,
            'methods' => $methods,
            'topMethods' => EntryRecorder::topMethods($methods),
            'total' => $total / 100,
            'paid' => $paid / 100,
            'rest' => ($total - $paid) / 100,
        ];
    }
}
