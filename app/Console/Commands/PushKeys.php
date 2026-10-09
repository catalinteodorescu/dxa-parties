<?php

namespace App\Console\Commands;

use App\Services\Push\Vapid;
use Illuminate\Console\Command;

/** DXA: adaugat (runda 70). Generează o pereche de chei VAPID pentru notificările push. Se rulează o singură dată; cheile se pun în .env. */
class PushKeys extends Command
{
    protected $signature = 'dxa:push-keys {--force : generează chei noi chiar dacă există deja (abonările existente se pierd)}';

    protected $description = 'Generează cheile VAPID pentru notificările push (de pus în .env)';

    public function handle(): int
    {
        if (Vapid::configured() && ! $this->option('force')) {
            $this->warn('Cheile VAPID există deja în .env. Chei noi ar invalida toate abonările (folosește --force doar dacă e intenționat).');

            return self::FAILURE;
        }

        $keys = Vapid::generate();
        $this->line('Adaugă în .env (cheia privată rămâne secretă: nu o pune în chat și nu o include în zip):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['public']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['private']);
        $this->line('VAPID_SUBJECT=mailto:adresa-ta@exemplu.ro');

        return self::SUCCESS;
    }
}
