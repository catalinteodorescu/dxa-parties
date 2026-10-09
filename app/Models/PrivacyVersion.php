<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DXA: adaugat (runda 68). O versiune publicată a Politicii de confidențialitate (App\Services\PrivacyPolicy). Nu se acceptă: e informare. */
class PrivacyVersion extends Model
{
    protected $fillable = ['body', 'published_by'];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by');
    }
}
