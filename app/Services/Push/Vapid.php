<?php

namespace App\Services\Push;

use RuntimeException;

/**
 * DXA: adaugat (runda 70). Cheile VAPID (identitatea serverului față de serviciul de push al browserului) și semnarea JWT-ului (ES256),
 * doar cu extensia openssl, fără pachete. Cheia privată e în .env ca valoarea „d” (32 de octeți, base64url); publică = punctul necomprimat.
 */
final class Vapid
{
    public static function configured(): bool
    {
        return filled(config('services.push.public_key')) && filled(config('services.push.private_key'));
    }

    public static function publicKey(): string
    {
        return (string) config('services.push.public_key');
    }

    /** @return array{public: string, private: string} chei noi, base64url */
    public static function generate(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            throw new RuntimeException('Nu s-au putut genera cheile (openssl).');
        }
        $ec = openssl_pkey_get_details($key)['ec'];

        return [
            'public' => self::b64(chr(4).str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
            'private' => self::b64(str_pad($ec['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    /** Antetul `Authorization` pentru un endpoint de push (schema `vapid t=…, k=…`). */
    public static function authorization(string $endpoint, ?int $now = null): string
    {
        $parts = parse_url($endpoint);
        $aud = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        $signing = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            .'.'.self::b64(json_encode(['aud' => $aud, 'exp' => ($now ?? time()) + 12 * 3600, 'sub' => (string) config('services.push.subject')]));

        if (! openssl_sign($signing, $der, self::privateKeyResource(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Semnarea VAPID a eșuat.');
        }

        return 'vapid t='.$signing.'.'.self::b64(self::derToRaw($der)).', k='.self::publicKey();
    }

    /** Cheia privată ca resursă openssl, reconstruită din „d” + punctul public (structura ECPrivateKey, curba prime256v1). */
    private static function privateKeyResource(): \OpenSSLAsymmetricKey
    {
        $d = self::unb64((string) config('services.push.private_key'));
        $pub = self::unb64(self::publicKey());
        if (strlen($d) !== 32 || strlen($pub) !== 65) {
            throw new RuntimeException('Cheile VAPID din .env sunt invalide.');
        }

        $der = "\x30\x77\x02\x01\x01\x04\x20".$d."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\xa1\x44\x03\x42\x00".$pub;
        $pem = "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('Cheia privată VAPID nu a putut fi citită.');
        }

        return $key;
    }

    /** Semnătura DER (SEQUENCE{INTEGER r, INTEGER s}) -> 64 de octeți r||s, cum cere JWT ES256. */
    public static function derToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7F;
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = ltrim(substr($der, $pos + 2, $len), "\0");
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    public static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/').str_repeat('=', (4 - strlen($data) % 4) % 4), true);
    }
}
