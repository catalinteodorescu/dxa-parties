<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DXA: adaugat (runda 60). O versiune publicată a Termenilor și condițiilor (App\Services\Terms). */
class TermsVersion extends Model
{
    protected $fillable = ['body', 'requires_reaccept', 'published_by'];

    protected function casts(): array
    {
        return ['requires_reaccept' => 'boolean'];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by');
    }
}
