<?php

namespace App\Livewire\Reception;

use App\Livewire\Reception\Concerns\UsesReceptionParty;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Recepție - raportare). Recepționerul numără casa la final de seară și trimite raportarea din aplicație:
 * fond de casă, bani scoși din casă pe parcurs, cash numărat, note (și pe metodele necash). Vede totalurile sesiunii și diferența față de
 * cash-ul așteptat. „Trimite” NU închide casa: marchează draftul ca trimis (blochează înregistrările și anulările din
 * sesiune), iar adminul îl finalizează sau îl redeschide din web. Cifrele vin din App\Models\ReceptionReport::figures().
 */
#[Layout('layouts.reception')]
class Report extends Component
{
    use UsesReceptionParty;

    public string $opening_float = '';

    public string $handed_over = '';

    public string $handed_note = '';

    public string $counted_cash = '';

    public string $note = '';

    /** @var array<string, string> notițe pe metodele necash (ex. total POS card) */
    public array $method_notes = [];

    public bool $loaded = false;

    public bool $confirming = false;

    public ?string $error = null;

    public ?string $message = null;

    private function session(): ?ReceptionSession
    {
        return $this->partyId ? ReceptionSession::currentFor($this->partyId) : null;
    }

    private function existing(ReceptionSession $session): ?ReceptionReport
    {
        return ReceptionReport::query()->where('reception_session_id', $session->id)->first();
    }

    /** Completează câmpurile o singură dată: din draftul existent, altfel cu fondul de casă propus. */
    private function loadOnce(?ReceptionSession $session): void
    {
        if ($this->loaded || ! $session) {
            return;
        }
        $this->loaded = true;

        $report = $this->existing($session);
        if ($report) {
            $this->opening_float = $this->fmt($report->opening_float);
            $this->handed_over = $this->fmt($report->handed_over);
            $this->handed_note = (string) $report->handed_note;
            $this->counted_cash = $this->fmt($report->counted_cash);
            $this->note = (string) $report->note;
            $this->method_notes = array_map('strval', $report->method_notes ?? []);
        } else {
            $this->opening_float = $this->fmt(ReceptionReport::suggestedFloat($session->party_id));
        }
    }

    /** Modelul cu valorile din formular aplicate (nesalvate), ca cifrele să se recalculeze pe măsură ce scrie. */
    private function draftModel(ReceptionSession $session): ReceptionReport
    {
        $report = $this->existing($session) ?? (new ReceptionReport(['reception_session_id' => $session->id, 'party_id' => $session->party_id, 'status' => 'draft']))->setRelation('session', $session);

        if ($report->isSubmitted()) {
            return $report;
        }

        $try = function (string $v) {
            try {
                return ReceptionReport::parseMoney($v, 'Suma');
            } catch (DomainException) {
                return null;
            }
        };

        $report->opening_float = $try($this->opening_float);
        $report->handed_over = $try($this->handed_over);
        $report->counted_cash = $try($this->counted_cash);
        $report->method_notes = array_filter(array_map(fn ($t) => trim((string) $t), $this->method_notes), fn ($t) => $t !== '') ?: null;

        return $report;
    }

    /** Salvează câmpurile în draft (îl creează dacă nu există). @throws DomainException */
    private function persist(ReceptionSession $session): ReceptionReport
    {
        $report = ReceptionReport::startFor($session, Auth::guard('admin')->id());

        if ($report->isSubmitted()) {
            throw new DomainException('Raportarea a fost deja trimisă.');
        }

        $notes = [];
        foreach ($this->method_notes as $key => $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $notes[(string) $key] = mb_substr($text, 0, 255);
            }
        }

        $report->update([
            'opening_float' => ReceptionReport::parseMoney($this->opening_float, 'Fondul de casă'),
            'handed_over' => ReceptionReport::parseMoney($this->handed_over, 'Banii scoși din casă'),
            'handed_note' => trim($this->handed_note) !== '' ? mb_substr(trim($this->handed_note), 0, 255) : null,
            'counted_cash' => ReceptionReport::parseMoney($this->counted_cash, 'Cash-ul numărat'),
            'method_notes' => $notes ?: null,
            'note' => trim($this->note) !== '' ? mb_substr(trim($this->note), 0, 2000) : null,
        ]);

        return $report;
    }

    public function save(): void
    {
        $this->error = $this->message = null;
        $this->confirming = false;

        try {
            $this->persist($this->session() ?? throw new DomainException('Nu există o sesiune de recepție deschisă.'));
            $this->message = 'Salvat. Raportarea e încă în lucru (draft).';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    /** Salvează, verifică și deschide dialogul de confirmare (trimiterea propriu-zisă e în submit()). */
    public function askSubmit(): void
    {
        $this->error = $this->message = null;
        $this->confirming = false;

        try {
            $report = $this->persist($this->session() ?? throw new DomainException('Nu există o sesiune de recepție deschisă.'));
            if ($report->counted_cash === null) {
                throw new DomainException('Introdu cash-ul numărat ca să poți trimite raportarea (poate fi 0).');
            }
            $this->confirming = true;
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function cancelSubmit(): void
    {
        $this->confirming = false;
    }

    public function submit(): void
    {
        $this->error = $this->message = null;

        try {
            $session = $this->session() ?? throw new DomainException('Nu există o sesiune de recepție deschisă.');
            $report = $this->persist($session);
            $report->submit((int) Auth::guard('admin')->id());
            $this->message = 'Raportarea a fost trimisă. Un admin o verifică și închide casa.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }

        $this->confirming = false;
    }

    private function fmt(mixed $n): string
    {
        if ($n === null || $n === '') {
            return '';
        }
        $n = (float) $n;

        return $n == floor($n) ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    public function render()
    {
        $party = $this->currentParty();
        $session = $this->session();
        $this->loadOnce($session);

        $report = $session ? $this->draftModel($session) : null;

        return view('livewire.reception.report', [
            'party' => $party,
            'session' => $session,
            'report' => $report,
            'fig' => $report?->figures(),
            'submitted' => $report?->isSubmitted() ?? false,
        ]);
    }
}
