<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Implementare temporară, pentru dezvoltare locală: în loc să trimită
 * efectiv un SMS, scrie conținutul în log. Deschide storage/logs/laravel.log
 * (sau rulează "php artisan pail" dacă e instalat) ca să vezi mesajul și
 * să copiezi link-ul din el.
 *
 * Când alegeți un furnizor real de SMS (Twilio, Vonage, SmsLink.ro,
 * SMSAdvert etc.), înlocuiți binding-ul din AppServiceProvider cu o
 * implementare nouă a interfeței SmsSender, fără să schimbați nimic
 * altundeva în aplicație.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("[SMS către {$phone}]: {$message}");
    }
}
