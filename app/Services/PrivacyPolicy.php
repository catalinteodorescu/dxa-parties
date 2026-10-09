<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\PrivacyVersion;
use DomainException;

/**
 * DXA: adaugat (runda 68). Politica de confidențialitate: un singur text, în română, publicat pe versiuni ca Termenii (App\Services\Terms),
 * dar FĂRĂ acceptare: e o informare pentru participanți (se citește din subsol și din formularul de cont nou), nu un acord. Cât timp
 * nu e publicată nicio versiune, nu se arată nimic. Randarea textului e aceeași cu a Termenilor (Terms::render).
 */
class PrivacyPolicy
{
    public const MAX_LENGTH = 60000;

    public static function current(): ?PrivacyVersion
    {
        return PrivacyVersion::query()->orderByDesc('id')->first();
    }

    public static function enabled(): bool
    {
        return PrivacyVersion::query()->exists();
    }

    public static function publish(string $body, ?Admin $by = null): PrivacyVersion
    {
        $body = trim(str_replace("\r\n", "\n", $body));
        if ($body === '') {
            throw new DomainException('Scrie textul Politicii de confidențialitate.');
        }
        if (mb_strlen($body) > self::MAX_LENGTH) {
            throw new DomainException('Textul e prea lung (maximum '.self::MAX_LENGTH.' de caractere).');
        }
        if (self::current()?->body === $body) {
            throw new DomainException('Textul nu s-a schimbat față de versiunea publicată.');
        }

        $version = PrivacyVersion::create(['body' => $body, 'published_by' => $by?->id]);

        ActivityLogger::log('privacy.published', 'A publicat versiunea '.$version->id.' a Politicii de confidențialitate.');

        return $version;
    }

    /** Textul de probă din câmpul gol (se înlocuiește cu cel final). */
    public static function sample(): string
    {
        return "# Politica de confidențialitate (text de probă)\n\n"
            ."Acesta este un text de probă. Înlocuiește-l cu varianta finală înainte de lansare.\n\n"
            ."# Ce date colectăm\n\n"
            ."Nume, număr de telefon și istoricul biletelor, al intrărilor și al plăților din aplicație.\n\n"
            ."# Cum folosim datele\n\n"
            ."Datele sunt folosite pentru cont, bilete, intrare la evenimente și plăți, și nu sunt vândute.\n\n"
            ."# Drepturile tale\n\n"
            .'Îți poți exporta datele sau poți șterge contul din aplicație, oricând.';
    }
}
