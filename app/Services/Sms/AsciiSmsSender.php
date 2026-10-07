<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 52d). Toate SMS-urile pleaca fara diacritice (un singur caracter cu diacritice trece mesajul pe codare Unicode,
 * cu limita de 70 de caractere pe SMS in loc de 160). Inveleste furnizorul real; se aplica o singura data, aici.
 */
class AsciiSmsSender implements SmsSender
{
    public function __construct(private SmsSender $inner) {}

    public static function plain(string $message): string
    {
        return Str::ascii(str_replace(['„', '“', '”', '’', '–', '—', '…'], ['"', '"', '"', "'", '-', '-', '...'], $message));
    }

    public function send(string $phone, string $message): void
    {
        $this->inner->send($phone, self::plain($message));
    }
}
