<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DXA: runda 79. Un plan (Basic/Pro): funcționalitățile incluse + limitele (null = nelimitat). */
class Plan extends Model
{
    protected $fillable = ['key', 'name', 'price_monthly', 'price_yearly_monthly', 'sms_overage_eur', 'features', 'limits'];

    protected function casts(): array
    {
        return ['features' => 'array', 'limits' => 'array'];
    }
}
