<?php

namespace App\Support;

/**
 * DXA: adaugat (Participanți). Telefoane în formă normalizată (E.164): +40722123456.
 * Acceptă formatul românesc obișnuit („0722 123 456”, „0040722123456”, „40722123456”) și numere internaționale cu „+” sau „00”.
 */
class Phone
{
    /** Întoarce numărul normalizat sau null dacă nu arată a telefon. */
    public static function normalize(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $plus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($plus) {
            // deja international
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '40'.substr($digits, 1);      // 07xx... -> 407xx...
        } elseif (str_starts_with($digits, '40') && strlen($digits) === 11) {
            // 40722123456: deja cu prefix de tara
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '40'.$digits;                  // 722123456
        }

        return preg_match('/^\d{8,15}$/', $digits) ? '+'.$digits : null;
    }
}
