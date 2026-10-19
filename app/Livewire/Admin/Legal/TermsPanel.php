<?php

namespace App\Livewire\Admin\Legal;

use App\Models\TermsVersion;
use App\Services\LegalTemplates;
use App\Services\Terms;
use DomainException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * DXA: adaugat (runda 60). Panou în pagina Legal (mutat din Setări în runda 68): Termeni și condiții (un singur text, în română). „Publică” creează o versiune nouă
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

    /** Datele organizatorului s-au schimbat: completează câmpurile rămase `{{...}}` fără să strice textul scris de mână. */
    #[On('organizer-saved')]
    public function refillFields(): void
    {
        $this->body = LegalTemplates::fill($this->body);
    }

    /** Pornește din nou de la modelul implicit (pierde modificările necomise din câmp). */
    public function reloadModel(): void
    {
        $this->error = $this->message = '';
        $this->body = LegalTemplates::rendered('terms');
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
        return view('livewire.admin.legal.terms-panel', [
            'current' => Terms::current(),
            'history' => TermsVersion::query()->with('publisher:id,name')->orderByDesc('id')->limit(6)->get(),
            'acceptance' => Terms::enabled() ? Terms::acceptance() : null,
        ]);
    }
}
