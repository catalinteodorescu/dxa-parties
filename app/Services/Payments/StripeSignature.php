<?php

namespace App\Services\Payments;

/**
 * DXA: adaugat (runda 64). Verifică antetul `Stripe-Signature` (schema v1: HMAC-SHA256 peste "{timestamp}.{corp}") cu secretul webhook-ului.
 * Respinge semnături greșite și cereri mai vechi de toleranță (protecție la reluare).
 */
class StripeSignature
{
    public static function verify(string $payload, ?string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
    {
        if ($secret === '' || $header === null || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $timestamp = ctype_digit($v) ? (int) $v : null;
            } elseif ($k === 'v1' && $v !== '') {
                $signatures[] = $v;
            }
        }
        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }
}
