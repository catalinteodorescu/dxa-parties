<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/** DXA: adaugat (runda 70). Un eveniment „către toți” deja tratat (cheie unică): garantează că nu se repetă. */
class PushEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['key'];

    /** Rezervă evenimentul; false dacă a fost deja rezervat. */
    public static function claim(string $key): bool
    {
        try {
            self::create(['key' => $key]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }
}
