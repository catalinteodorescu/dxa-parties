<?php

namespace App\Livewire\Admin\BarReports\Concerns;

use App\Models\BarReport;
use DomainException;
use Illuminate\Support\Facades\Auth;

/** DXA: adaugat (Bar - raportări casă). Redeschiderea unei raportări trimise, cu dialog propriu de confirmare (confirmingReopenId). */
trait ReopensBarReport
{
    public ?int $confirmingReopenId = null;

    public ?string $reopenMessage = null;

    public ?string $reopenError = null;

    public function askReopen(int $id): void
    {
        $this->reopenMessage = $this->reopenError = null;
        $this->confirmingReopenId = $id;
    }

    public function cancelReopen(): void
    {
        $this->confirmingReopenId = null;
    }

    public function reopenReport(): void
    {
        $this->reopenMessage = $this->reopenError = null;

        try {
            $report = BarReport::with('group')->findOrFail($this->confirmingReopenId);
            if (! ($report->group?->isOpen() ?? false)) {
                throw new DomainException('Sesiunea de vânzări e închisă: raportarea nu mai poate fi redeschisă.');
            }
            $report->reopen((int) Auth::guard('admin')->id());
            $this->reopenMessage = 'Raportarea nr. '.$report->number().' a fost redeschisă: barul poate vinde și anula din nou.';
        } catch (DomainException $e) {
            $this->reopenError = $e->getMessage();
        }

        $this->confirmingReopenId = null;
    }
}
