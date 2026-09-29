<?php

namespace App\Support;

use Closure;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat (protecție la retrimitere). Rulează o înregistrare cel mult o dată per cheie de încercare.
 * Cheia se scrie în aceeași tranzacție cu operațiunea: dacă operațiunea e respinsă, cheia nu rămâne consumată
 * și utilizatorul poate corecta și retrimite; dacă a reușit, o retrimitere a aceleiași încercări e refuzată.
 */
class SubmitGuard
{
    public const DUPLICATE_MESSAGE = 'Această operațiune a fost deja înregistrată (nu s-a dublat). Verifică în Tranzacții / Vânzări recente.';

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws DomainException când cheia a fost deja folosită de o operațiune reușită
     */
    public static function run(string $key, Closure $callback): mixed
    {
        return DB::transaction(function () use ($key, $callback) {
            try {
                DB::table('submit_guards')->insert(['key' => $key, 'created_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                throw new DomainException(self::DUPLICATE_MESSAGE);
            }

            return $callback();
        });
    }
}
