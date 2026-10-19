<?php

namespace App\Console\Commands;

use App\Services\Plan\SmsUsage;
use App\Services\Sms\SmsoClient;
use Illuminate\Console\Command;

/** DXA: adaugat (runda 82). `php artisan app:sms-status` — driverul activ, creditul și expeditorii din contul SMSO, plus SMS-urile lunii. */
class SmsStatus extends Command
{
    protected $signature = 'app:sms-status';

    protected $description = 'Arată driverul SMS, creditul și expeditorii SMSO (id-ul pentru SMSO_SENDER) și consumul lunii';

    public function handle(): int
    {
        $driver = (string) config('sms.driver');
        $this->line("Driver SMS: <info>{$driver}</info>");

        $r = SmsUsage::report();
        $this->line('Luna '.$r['period'].': '.$r['sent'].' trimise'.($r['included'] === null ? '' : ' din '.$r['included'].' incluse').', cost furnizor '.number_format($r['cost'], 2, ',', '.').' €.');

        if ($driver !== 'smso') {
            $this->line('Cu driverul „log” mesajele se scriu în storage/logs/laravel.log. Pune SMS_DRIVER=smso pentru trimitere reală.');

            return self::SUCCESS;
        }

        $client = SmsoClient::fromConfig();
        if (! $client) {
            $this->error('Lipsește SMSO_API_KEY din .env.');

            return self::FAILURE;
        }

        $credit = $client->credit();
        if ($credit === null) {
            $this->error('Nu am putut citi creditul (cheie greșită sau SMSO indisponibil).');

            return self::FAILURE;
        }
        $this->line('Credit SMSO: <info>'.number_format($credit, 2, ',', '.').'</info>');

        $senders = $client->senders();
        if ($senders === []) {
            $this->warn('Nu am găsit expeditori în cont.');

            return self::SUCCESS;
        }

        $configured = (string) config('sms.smso.sender');
        $this->table(['ID (SMSO_SENDER)', 'Nume', 'Preț / SMS', ''], array_map(
            fn ($s) => [$s['id'], $s['name'], $s['price'] ?? '—', (string) $s['id'] === $configured ? '← folosit' : ''],
            $senders,
        ));

        return self::SUCCESS;
    }
}
