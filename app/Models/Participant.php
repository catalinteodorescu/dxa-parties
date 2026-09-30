<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Participanți - fundația). O persoană identificată. Se creează/modifică prin
 * App\Services\ParticipantRegistry (validare, dedupe după telefon, log).
 */
class Participant extends Authenticatable
{
    public const ANONYMIZED_NAME = 'Participant șters';

    protected $fillable = ['uuid', 'name', 'phone', 'password', 'phone_verified_at', 'avatar_path', 'avatar_updated_at', 'source', 'created_by', 'anonymized_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'anonymized_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'avatar_updated_at' => 'datetime',
            'password' => 'hashed',
            'credit_balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Participant $p) {
            $p->uuid ??= (string) Str::uuid();
        });
    }

    /** Prefixul din codul QR personal (aplicația pentru participanți va afișa același conținut). */
    public const QR_PREFIX = 'DXA:P:';

    /** Conținutul codului QR personal. */
    public function qrPayload(): string
    {
        return self::QR_PREFIX.$this->uuid;
    }

    /** Găsește participantul după conținutul unui QR scanat (acceptă și uuid-ul simplu). */
    public static function findByQrPayload(string $payload): ?self
    {
        $code = trim($payload);
        if (str_starts_with($code, self::QR_PREFIX)) {
            $code = substr($code, strlen(self::QR_PREFIX));
        }

        return Str::isUuid($code) ? self::where('uuid', strtolower($code))->first() : null;
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PartyEntry::class);
    }

    /** DXA: adaugat (Portofelul de credite). Ledgerul complet; soldul e cache în credit_balance. */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /** DXA: adaugat (Card de fidelitate). Toate cardurile (active + completate/arhivate), cel mai nou primul. */
    public function loyaltyCards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class)->latest('id');
    }

    /** Înrolat la fidelitate? (are cel puțin un card, oricând creat). */
    public function isLoyaltyEnrolled(): bool
    {
        return $this->loyaltyCards()->exists();
    }

    /** Are cont de aplicație activ (parolă setată + telefon confirmat prin SMS) și nu e anonimizat? */
    public function hasAccount(): bool
    {
        return $this->password !== null && $this->phone_verified_at !== null && ! $this->isAnonymized();
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

    /** Inițialele pentru cercul fără poză: prima literă din primele două cuvinte ale numelui („Ana Maria Pop” → „AM”). */
    public function initials(): string
    {
        $words = preg_split('/[\s\-]+/u', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters) ?: '?';
    }

    public function hasAvatar(): bool
    {
        return $this->avatar_path !== null && ! $this->isAnonymized();
    }

    /** URL-ul pozei proprii (servită de aplicație, nu public), cu versiune pentru cache; null fără poză. */
    public function avatarUrl(): ?string
    {
        return $this->hasAvatar()
            ? route('app.avatar', ['v' => $this->avatar_updated_at?->timestamp ?? 0], false)
            : null;
    }

    /** Aceeași poză, pentru lista din admin (ruta admin, cu versiune pentru cache); null fără poză. */
    public function adminAvatarUrl(): ?string
    {
        return $this->hasAvatar()
            ? route('admin.participants.avatar', ['participant' => $this->id, 'v' => $this->avatar_updated_at?->timestamp ?? 0], false)
            : null;
    }
}
