<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un singur rand cheie-valoare din tabelul `settings`. Nu folosi acest model
 * direct in restul aplicatiei — foloseste App\Support\Settings\Settings::get()/set(),
 * care stie si tipul fiecarei chei (bool, number, string...) din SettingsRegistry.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];
}
