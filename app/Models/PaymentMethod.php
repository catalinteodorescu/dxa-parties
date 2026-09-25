<?php

namespace App\Models;

use App\Support\PaymentMethods;
use Illuminate\Database\Eloquent\Model;

/**
 * DXA: adaugat (Setări - Metode de plată). O metodă de plată acceptată de organizație.
 *
 * Nu folosi direct în afara Setărilor: citirea/scrierea se face prin App\Support\PaymentMethods, care
 * știe regulile (cash mereu activ, tokeni cu 3 stări, garduri la dezactivare, petrecere).
 *
 * `key` e stabilă (nu se schimbă la redenumire) și e cea salvată pe petreceri și în `sale_payments.method`.
 */
class PaymentMethod extends Model
{
    protected $fillable = ['key', 'label', 'is_builtin', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Acceptată acum? Cash e mereu; tokenii depind de starea lor (Activ / Doar încasare / Oprit). */
    public function isEnabled(): bool
    {
        return match ($this->key) {
            PaymentMethods::CASH => true,
            PaymentMethods::TOKEN => PaymentMethods::tokenMode() !== PaymentMethods::TOKEN_OFF,
            default => $this->is_active,
        };
    }
}
