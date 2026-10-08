<?php

namespace App\Livewire\Admin\Settings;

use App\Models\TermsVersion;
use App\Services\Terms;
use DomainException;
use Livewire\Component;

/**
 * DXA: adaugat (runda 60). Panou în Setări: Termeni și condiții (un singur text, în română). „Publică” creează o versiune nouă
 * (App\Services\Terms::publish); prima cere mereu acceptarea, următoarele după bifa „cere acceptare de la toți”.
 * Câmpul pornește cu versiunea publicată, sau cu un text de probă dacă nu există niciuna (care nu se publică singur).
 */
class TermsPanel extends Component
{
    public string $body = '';

    public bool $requireAll = false;

    public string $message = '';

    public string $error = '';

    public function mount(): void
    {
        $this->body = Terms::current()?->body ?? Terms::sample();
    }

    public function publish(): void
    {
        $this->message = $this->error = '';

        try {
            $version = Terms::publish($this->body, $this->requireAll, auth('admin')->user());
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->requireAll = false;
        $this->message = $version->requires_reaccept
            ? 'Versiunea '.$version->id.' e publicată. Participanții vor fi rugați să o accepte.'
            : 'Versiunea '.$version->id.' e publicată (modificare minoră, fără re-acceptare).';
    }

    public function render()
    {
        return view('livewire.admin.settings.terms-panel', [
            'current' => Terms::current(),
            'history' => TermsVersion::query()->with('publisher:id,name')->orderByDesc('id')->limit(6)->get(),
            'acceptance' => Terms::enabled() ? Terms::acceptance() : null,
        ]);
    }
}
