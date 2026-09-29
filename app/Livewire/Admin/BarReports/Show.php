<?php

namespace App\Livewire\Admin\BarReports;

use App\Livewire\Admin\BarReports\Concerns\ReopensBarReport;
use App\Models\BarReport;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** DXA: adaugat (Bar - raportări casă). Detaliul (doar citire) al unei raportări de bar: cash, tokeni, încasări pe metodă, PDF, redeschidere. */
#[Layout('layouts.admin')]
class Show extends Component
{
    use ReopensBarReport;

    public BarReport $report;

    public function mount(BarReport $report): void
    {
        $this->report = $report;
    }

    public function render()
    {
        $this->report->refresh()->load(['party', 'group', 'submitter']);

        return view('livewire.admin.bar-reports.show', [
            'report' => $this->report,
            'fig' => $this->report->figures(),
        ]);
    }
}
