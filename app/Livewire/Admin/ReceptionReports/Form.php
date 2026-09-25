<?php

namespace App\Livewire\Admin\ReceptionReports;

use App\Models\ReceptionReport;
use App\Services\ReceptionReportPdfExporter;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Recepție - raportări). Raportarea de recepție = închiderea casei unei sesiuni.
 *
 * Draft cu autosave (fiecare câmp se salvează la modificare): fond de casă, cash predat/scos (+ notă), cash numărat,
 * note pe metode, notă generală. Încasările (intrări + tokeni) se calculează live din sesiune. „Finalizează” îngheață
 * valorile și închide sesiunea (ReceptionReport::finalize()); o raportare finalizată e doar de citit / exportat.
 * Toată logica de bani stă în ReceptionReport / ReceptionSummary; aici doar interfața.
 */
#[Layout('layouts.admin')]
class Form extends Component
{
    public ReceptionReport $report;

    public string $opening_float = '';

    public string $handed_over = '';

    public string $handed_note = '';

    public string $counted_cash = '';

    public string $note = '';

    /** @var array<string, string> cheie metodă => notă */
    public array $method_notes = [];

    /** Dialogul de finalizare (recap cu cifrele salvate). */
    public bool $confirming = false;

    /** Mesaje ale barei de acțiuni (salvare / finalizare), afișate lângă butoane. */
    public ?string $error = null;

    public ?string $savedAt = null;

    public function mount(ReceptionReport $report): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $this->report = $report->load(['party', 'session', 'creator', 'finalizer']);

        $this->opening_float = $this->fmt($report->opening_float);
        $this->handed_over = $this->fmt($report->handed_over);
        $this->handed_note = (string) $report->handed_note;
        $this->counted_cash = $this->fmt($report->counted_cash);
        $this->note = (string) $report->note;
        $this->method_notes = array_map('strval', $report->method_notes ?? []);
    }

    /** Autosave: orice câmp din draft se salvează imediat. */
    public function updated($name): void
    {
        if (! $this->report->isDraft()) {
            return;
        }

        $this->error = null;
        try {
            $this->persist();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    /** „Finalizează”: salvează, verifică și deschide dialogul cu recap-ul (finalizarea propriu-zisă e în finalize()). */
    public function askFinalize(): void
    {
        $this->error = null;
        $this->confirming = false;

        try {
            $this->assertDraft();
            $this->persist();
            if ($this->report->counted_cash === null) {
                throw new DomainException('Introdu cash-ul numărat ca să poți finaliza raportarea (poate fi 0).');
            }
            $this->confirming = true;
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function cancelFinalize(): void
    {
        $this->confirming = false;
    }

    public function finalize(): void
    {
        $this->error = null;

        try {
            $this->assertDraft();
            $this->persist();
            $this->report->finalize((int) Auth::guard('admin')->id());
            $this->report = $this->report->fresh(['party', 'session', 'creator', 'finalizer']);
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }

        $this->confirming = false;
    }

    public function exportPdf()
    {
        return ReceptionReportPdfExporter::stream($this->report);
    }

    private function assertDraft(): void
    {
        $this->report->refresh();
        if (! $this->report->isDraft()) {
            throw new DomainException('Raportarea e deja finalizată.');
        }
    }

    /** Validează și salvează câmpurile draftului. @throws DomainException */
    private function persist(): void
    {
        $this->assertDraft();

        $notes = [];
        foreach ($this->method_notes as $key => $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $notes[(string) $key] = mb_substr($text, 0, 255);
            }
        }

        $this->report->update([
            'opening_float' => $this->money($this->opening_float, 'Fondul de casă'),
            'handed_over' => $this->money($this->handed_over, 'Cash-ul predat/scos'),
            'handed_note' => trim($this->handed_note) !== '' ? mb_substr(trim($this->handed_note), 0, 255) : null,
            'counted_cash' => $this->money($this->counted_cash, 'Cash-ul numărat'),
            'method_notes' => $notes ?: null,
            'note' => trim($this->note) !== '' ? mb_substr(trim($this->note), 0, 2000) : null,
        ]);

        $this->savedAt = now()->format('H:i:s');
    }

    /** Text -> lei (virgulă sau punct); '' = necompletat (null). @throws DomainException */
    private function money(string $value, string $label): ?float
    {
        $raw = str_replace([' ', ','], ['', '.'], trim($value));
        if ($raw === '') {
            return null;
        }
        if (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > 10000000) {
            throw new DomainException($label.' trebuie să fie o sumă între 0 și 10.000.000 lei.');
        }

        return round((float) $raw, 2);
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
        return view('livewire.admin.reception-reports.form', [
            'fig' => $this->report->figures(),
        ]);
    }
}
