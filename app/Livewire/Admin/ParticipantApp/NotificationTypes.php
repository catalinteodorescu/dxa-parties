<?php

namespace App\Livewire\Admin\ParticipantApp;

use App\Services\ActivityLogger;
use App\Services\Push\Notifier;
use Livewire\Component;

/**
 * DXA: adaugat (runda 70c). Secțiunea „Notificări — ce se trimite” din Setări › Aplicație participanți: câte un întrerupător pe tip de
 * notificare, cu descriere și un exemplu. Cere bifa „Notificări” din permisiuni (acțiunea manage_notifications, metoda toggleType).
 */
class NotificationTypes extends Component
{
    /** Pragul „bilete luate de alții” așa cum e tastat în câmp (text, ca să putem afișa eroarea). */
    public string $buyersThreshold = '';

    public function mount(): void
    {
        $this->buyersThreshold = (string) (Notifier::buyersThreshold() ?? '');
    }

    /** Salvează pragul. Gol = șters (și tipul se oprește). Valoare invalidă = eroare în caseta notificării, pragul rămâne cum era. */
    public function saveThreshold(): void
    {
        $this->resetErrorBag('buyers');
        $raw = trim($this->buyersThreshold);
        if ($raw === '') {
            Notifier::setBuyersThreshold(null);
            if (Notifier::typeEnabled('buyers')) {
                Notifier::setType('buyers', false);
                ActivityLogger::log('push.type_toggled', 'A oprit notificările „'.Notifier::TYPES['buyers'][0].'” (pragul a fost șters).');
            }

            return;
        }
        if (! ctype_digit($raw) || (int) $raw < 1 || (int) $raw > 100000) {
            $this->addError('buyers', 'Pragul trebuie să fie un număr întreg, cel puțin 1.');
            $this->buyersThreshold = (string) (Notifier::buyersThreshold() ?? '');

            return;
        }
        Notifier::setBuyersThreshold((int) $raw);
        ActivityLogger::log('push.type_toggled', 'A setat pragul „'.Notifier::TYPES['buyers'][0].'” la '.(int) $raw.' persoane.');
    }

    /** Pornește/oprește un tip de notificare. */
    public function toggleType(string $kind): void
    {
        if (! isset(Notifier::TYPES[$kind])) {
            return;
        }
        $on = ! Notifier::typeEnabled($kind);
        if ($kind === 'buyers' && $on) {
            $this->resetErrorBag('buyers');
            if (Notifier::buyersThreshold() === null) {
                $this->addError('buyers', 'Completează mai întâi pragul (câte persoane au cumpărat bilet), apoi pornește notificarea.');

                return;
            }
        }
        Notifier::setType($kind, $on);
        ActivityLogger::log('push.type_toggled', ($on ? 'A pornit' : 'A oprit').' notificările „'.Notifier::TYPES[$kind][0].'”.');
    }

    public function render()
    {
        return view('livewire.admin.participant-app.notification-types', [
            'enabled' => Notifier::enabled(),
            'types' => collect(Notifier::TYPES)->map(fn ($t, $k) => ['label' => $t[0], 'hint' => $t[1], 'example' => $t[2], 'on' => Notifier::typeEnabled($k)])->all(),
        ]);
    }
}
