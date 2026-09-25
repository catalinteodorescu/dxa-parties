<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Participanți - fundația). O persoană identificată. Se creează/modifică prin
 * App\Services\ParticipantRegistry (validare, dedupe după telefon, log).
 */
class Participant extends Model
{
    public const ANONYMIZED_NAME = 'Participant șters';

    protected $fillable = ['uuid', 'name', 'phone', 'source', 'created_by', 'anonymized_at'];

    protected function casts(): array
    {
        return ['anonymized_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Participant $p) {
            $p->uuid ??= (string) Str::uuid();
        });
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PartyEntry::class);
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /** „Ion Popescu · +40722123456” (fără telefon după anonimizare). */
    public function label(): string
    {
        return $this->phone ? $this->name.' · '.$this->phone : $this->name;
    }
}
