<?php

namespace App\Console\Commands;

use App\Services\Push\Events;
use App\Services\Push\Notifier;
use App\Support\Settings\Settings;
use Illuminate\Console\Command;

/** DXA: adaugat (runda 70). Trimite notificările programate (petrecere nouă, preț, locuri, mâine, cu 2 ore înainte, anunțuri). Se rulează din cron, la 15 minute. */
class PushRun extends Command
{
    protected $signature = 'dxa:push-run';

    protected $description = 'Trimite notificările push programate';

    public function handle(): int
    {
        if (! Notifier::enabled()) {
            $this->line('Push nu e configurat (lipsesc cheile VAPID).');

            return self::SUCCESS;
        }

        $sent = Events::runScheduled();
        Settings::set('push_last_run', now()->toDateTimeString());
        Notifier::prune();
        $this->line('Trimise: '.collect($sent)->map(fn ($n, $k) => $k.'='.$n)->implode(', '));

        return self::SUCCESS;
    }
}
