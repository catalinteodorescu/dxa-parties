<?php

namespace App\Livewire\Admin\Legal;

use App\Services\ActivityLogger;
use App\Services\LegalTemplates;
use Livewire\Component;

/**
 * DXA: adaugat (runda 83). „Date organizator” în pagina Legal: denumirea, CUI-ul, sediul și contactul organizatorului (clientul, operatorul datelor).
 * Completează automat câmpurile `{{...}}` din modelele Termenilor și Politicii. După salvare, panourile de text își completează singure
 * câmpurile rămase goale, fără să piardă ce ai scris (evenimentul `organizer-saved`).
 */
class OrganizerPanel extends Component
{
    /** @var array<string, string> */
    public array $org = [];

    public string $message = '';

    public function mount(): void
    {
        $this->org = LegalTemplates::organizer();
    }

    public function save(): void
    {
        abort_unless(auth('admin')->user()?->permits('legal', 'edit'), 403);

        $this->validate([
            'org.organizer_name' => ['nullable', 'string', 'max:150'],
            'org.organizer_cui' => ['nullable', 'string', 'max:30'],
            'org.organizer_regcom' => ['nullable', 'string', 'max:40'],
            'org.organizer_address' => ['nullable', 'string', 'max:250'],
            'org.organizer_email' => ['nullable', 'email', 'max:120'],
            'org.organizer_phone' => ['nullable', 'string', 'max:30'],
        ], [
            'org.organizer_email.email' => 'Scrie o adresă de e-mail validă.',
            'org.*.max' => 'Textul e prea lung.',
        ]);

        LegalTemplates::saveOrganizer($this->org);
        ActivityLogger::log('legal.organizer.updated', 'A actualizat datele organizatorului din pagina Legal.');

        $this->org = LegalTemplates::organizer();
        $this->message = 'Datele organizatorului au fost salvate.';
        $this->dispatch('organizer-saved');
    }

    public function render()
    {
        return view('livewire.admin.legal.organizer-panel', [
            'missing' => LegalTemplates::missingOrganizerFields(),
            'fields' => LegalTemplates::ORGANIZER_FIELDS,
        ]);
    }
}
