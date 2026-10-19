<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Services\Plan\SmsUsage;

/**
 * DXA: adaugat (runda 80). Înveleste trimiterea reală și numără fiecare SMS plecat în luna curentă. Numără DUPĂ ce furnizorul a
 * acceptat mesajul (o excepție nu se numără) și nu blochează niciodată trimiterea: cota e „moale”, depășirea se facturează lunar.
 * O defecțiune a contorului nu poate opri un SMS de activare sau de resetare a parolei.
 */
class CountingSmsSender implements SmsSender
{
    public function __construct(private SmsSender $inner) {}

    public function send(string $phone, string $message): void
    {
        $this->inner->send($phone, $message);

        try {
            SmsUsage::record();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
