<?php

namespace App\Console\Commands;

use App\Services\LegalTemplates;
use App\Services\PrivacyPolicy;
use App\Services\Terms;
use DomainException;
use Illuminate\Console\Command;

/**
 * DXA: adaugat (runda 83). Pasul din procedura de instalare care pune textele legale în aplicație: salvează datele organizatorului (clientul),
 * completează modelele Termenilor și Politicii de confidențialitate și le PUBLICĂ. Dacă lipsește vreo dată, nu publică nimic și le listează.
 * Cu texte deja publicate nu face nimic, decât cu `--update` (versiune nouă; pentru Termeni, modificare minoră, fără re-acceptare).
 */
class InstallLegal extends Command
{
    protected $signature = 'app:install-legal
        {--name= : Denumirea organizatorului}
        {--cui= : CUI}
        {--regcom= : Nr. înregistrare la Registrul Comerțului}
        {--address= : Sediul}
        {--email= : E-mail de contact}
        {--phone= : Telefon de contact}
        {--update : Publică o versiune nouă chiar dacă textele sunt deja publicate}';

    protected $description = 'Salvează datele organizatorului și publică Termenii și Politica de confidențialitate din modelele implicite';

    public function handle(): int
    {
        $map = [
            'name' => 'organizer_name', 'cui' => 'organizer_cui', 'regcom' => 'organizer_regcom',
            'address' => 'organizer_address', 'email' => 'organizer_email', 'phone' => 'organizer_phone',
        ];
        $data = [];
        foreach ($map as $option => $key) {
            if (($v = $this->option($option)) !== null && trim((string) $v) !== '') {
                $data[$key] = trim((string) $v);
            }
        }
        if ($data !== []) {
            LegalTemplates::saveOrganizer($data);
        }

        $missing = LegalTemplates::missingOrganizerFields();
        if ($missing !== []) {
            $this->error('Lipsesc datele organizatorului: '.implode(', ', $missing).'. Dă-le ca opțiuni (--name, --cui, --regcom, --address, --email, --phone) sau completează-le în Legal › Date organizator. Nu am publicat nimic.');

            return self::FAILURE;
        }

        $update = (bool) $this->option('update');
        $done = 0;

        foreach ([
            'Termenii și condițiile' => [Terms::enabled(), fn () => Terms::publish(LegalTemplates::rendered('terms'), false)],
            'Politica de confidențialitate' => [PrivacyPolicy::enabled(), fn () => PrivacyPolicy::publish(LegalTemplates::rendered('privacy'))],
        ] as $label => [$published, $publish]) {
            if ($published && ! $update) {
                $this->line("{$label}: deja publicați, nu schimb nimic (folosește --update pentru o versiune nouă).");

                continue;
            }

            try {
                $publish();
                $this->info("{$label}: publicați.");
                $done++;
            } catch (DomainException $e) {
                $this->line("{$label}: {$e->getMessage()}");   // de ex. „textul nu s-a schimbat”
            }
        }

        return self::SUCCESS;
    }
}
