<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// DXA: adaugat (runda 70). Notificările push programate; cron-ul serverului rulează `php artisan schedule:run` (sau direct `dxa:push-run`) la 15 minute.
Schedule::command('dxa:push-run')->everyFifteenMinutes()->withoutOverlapping();
