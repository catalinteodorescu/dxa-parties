<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DXA: runda 79. Licența instalării (un singur rând): planul activ + suprascrieri pe funcție (cheie => true|false). */
class License extends Model
{
    protected $table = 'license';

    protected $fillable = ['plan_key', 'overrides', 'updated_by'];

    protected function casts(): array
    {
        return ['overrides' => 'array'];
    }
}
