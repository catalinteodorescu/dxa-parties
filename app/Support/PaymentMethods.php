<?php

namespace App\Support;

use App\Models\Party;
use App\Models\PaymentMethod;
use App\Services\ActivityLogger;
use App\Support\Settings\Settings;
use Closure;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Setări - Metode de plată). Punctul unic de citire/scriere a metodelor de plată.
 *
 * Nu hardcoda liste de metode în view-uri sau componente: citește-le de aici.
 *
 *  - Metodele se gestionează DOAR din Setări. Predefinite: cash, card, transfer, revolut, token, credit.
 *    Custom: adăugate din Setări, cu cheie stabilă `c_<slug>` (nu se schimbă la redenumire).
 *  - `benefit` NU e metodă de plată, ci voucher/cadou: nu se configurează și e mereu permis în vânzări.
 *  - Cash e mereu activ. Tokenii au 3 stări (Activ / Doar încasare / Oprit); creditele au două comutatoare:
 *    „acceptă credite la plată" (metoda credit activă) și „participanții pot cumpăra credite".
 *  - O petrecere alege un SUBSET din metodele active (`parties.payment_methods`, listă de chei; goală = toate
 *    cele active). La intrare tokenii nu se acceptă niciodată (se cumpără la recepție, se folosesc la bar);
 *    la bar cash e mereu acceptat, la intrare doar dacă e bifat pe petrecere.
 *
 * Garduri la dezactivare: modulele care țin bani „în aer" își înregistrează un gard cu registerGuard()
 * (ex. Portofelul de credite: blochează cât suma soldurilor > 0; Recepția: tokeni în circulație).
 * Gardul întoarce mesajul de blocare sau null.
 */
class PaymentMethods
{
    public const CASH = 'cash';

    public const TOKEN = 'token';

    public const CREDIT = 'credit';

    /** Voucher/cadou: apare în vânzări, dar nu e o metodă configurabilă. */
    public const BENEFIT = 'benefit';

    public const TOKEN_ACTIVE = 'active';

    public const TOKEN_COLLECT_ONLY = 'collect_only';

    public const TOKEN_OFF = 'off';

    public const TOKEN_MODES = [
        self::TOKEN_ACTIVE => 'Activ',
        self::TOKEN_COLLECT_ONLY => 'Doar încasare',
        self::TOKEN_OFF => 'Oprit',
    ];

    /** @var array<string, array<int|string, Closure>> */
    private static array $guards = [];

    // ---- Citire ----------------------------------------------------------

    /** Toate metodele (active sau nu): predefinite întâi, apoi custom în ordinea creării. */
    public static function all(): Collection
    {
        return PaymentMethod::query()->orderByDesc('is_builtin')->orderBy('id')->get();
    }

    public static function find(string $key): ?PaymentMethod
    {
        return PaymentMethod::query()->where('key', $key)->first();
    }

    /** Metodele acceptate acum: cheie => etichetă. Cash apare mereu. */
    public static function enabled(): array
    {
        $out = [];
        foreach (self::all() as $m) {
            if ($m->isEnabled()) {
                $out[$m->key] = $m->label;
            }
        }

        return $out + [self::CASH => 'Cash']; // plasă de siguranță: cash mereu prezent
    }

    public static function isEnabled(string $key): bool
    {
        return array_key_exists($key, self::enabled());
    }

    /** Etichetele TUTUROR metodelor (și ale celor dezactivate, pentru istoric) + beneficiu. */
    public static function labels(): array
    {
        $labels = self::all()->pluck('label', 'key')->all();
        $labels[self::BENEFIT] = 'Beneficiu';

        return $labels;
    }

    public static function label(string $key): string
    {
        return self::labels()[$key] ?? $key;
    }

    public static function tokenMode(): string
    {
        $mode = (string) Settings::get('token_mode');

        return array_key_exists($mode, self::TOKEN_MODES) ? $mode : self::TOKEN_ACTIVE;
    }

    public static function tokenRate(): float
    {
        return (float) Settings::get('token_rate');
    }

    /** Tokenii se pot vinde la recepție doar în starea „Activ". */
    public static function tokensSellable(): bool
    {
        return self::tokenMode() === self::TOKEN_ACTIVE;
    }

    public static function creditsPurchasable(): bool
    {
        return self::isEnabled(self::CREDIT) && (bool) Settings::get('credits_purchasable');
    }

    // ---- Petrecere -------------------------------------------------------

    /**
     * Metodele acceptate LA BAR pentru o petrecere (cheie => etichetă): subsetul ales pe petrecere din cele
     * active, cu cash mereu acceptat. Fără petrecere sau cu lista goală = toate cele active.
     */
    public static function forBar(?Party $party): array
    {
        $enabled = self::enabled();
        $list = $party?->payment_methods;

        if (empty($list) || ! is_array($list)) {
            return $enabled;
        }

        return array_filter(
            $enabled,
            fn ($label, $key) => $key === self::CASH || in_array($key, $list, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Metodele acceptate LA INTRARE: subsetul ales pe petrecere din cele active, FĂRĂ tokeni (se cumpără la
     * recepție, se folosesc la bar). Spre deosebire de bar, cash nu e forțat: dacă petrecerea nu-l are bifat,
     * la intrare nu se acceptă. Fără petrecere, listă goală sau fără nicio metodă de intrare = toate cele active.
     */
    public static function forEntry(?Party $party): array
    {
        $enabled = array_diff_key(self::enabled(), [self::TOKEN => true]);
        $list = $party?->payment_methods;

        if (empty($list) || ! is_array($list)) {
            return $enabled;
        }

        return array_intersect_key($enabled, array_flip($list)) ?: $enabled;
    }

    /**
     * Lista pentru bifele din formularul petrecerii: toate cele active + cele deja alese pe petrecere care
     * între timp s-au dezactivat (marcate „(dezactivată)", ca să se poată debifa).
     *
     * @param  array<int, string>  $selected
     * @return array<string, array{label: string, enabled: bool}>
     */
    public static function partyChoices(array $selected = []): array
    {
        $out = [];
        foreach (self::all() as $m) {
            if ($m->isEnabled()) {
                $out[$m->key] = ['label' => $m->label, 'enabled' => true];
            } elseif (in_array($m->key, $selected, true)) {
                $out[$m->key] = ['label' => $m->label, 'enabled' => false];
            }
        }

        return $out;
    }

    /** Etichetele cheilor unei liste salvate pe petrecere (cu „(dezactivată)" unde e cazul). */
    public static function describe(array $keys): array
    {
        $labels = self::labels();
        $enabled = self::enabled();

        return array_map(
            fn ($k) => ($labels[$k] ?? $k).(isset($enabled[$k]) || ! isset($labels[$k]) ? '' : ' (dezactivată)'),
            $keys,
        );
    }

    /** Clasa Tailwind de culoare pentru grafice/legende (culori din temă; custom = neutru). */
    public static function color(string $key): string
    {
        return match ($key) {
            self::CASH => 'bg-primary',
            self::TOKEN => 'bg-info',
            self::CREDIT => 'bg-purple',
            self::BENEFIT => 'bg-success',
            'card' => 'bg-warning',
            'transfer' => 'bg-ink',
            'revolut' => 'bg-danger',
            default => 'bg-ink-soft',
        };
    }

    // ---- Garduri ---------------------------------------------------------

    /**
     * Înregistrează un gard la dezactivarea metodei $key. Cu $id, o a doua înregistrare cu același id îl ÎNLOCUIEȘTE
     * (idempotent: un service provider care rulează la fiecare boot nu acumulează garduri duplicate).
     */
    public static function registerGuard(string $key, Closure $guard, ?string $id = null): void
    {
        if ($id !== null) {
            self::$guards[$key][$id] = $guard;

            return;
        }

        self::$guards[$key][] = $guard;
    }

    public static function flushGuards(): void
    {
        self::$guards = [];
    }

    /** Motivul pentru care metoda NU se poate dezactiva acum, sau null. */
    public static function blockReason(string $key): ?string
    {
        foreach (self::$guards[$key] ?? [] as $guard) {
            $reason = $guard();
            if ($reason) {
                return $reason;
            }
        }

        return null;
    }

    // ---- Scriere (Setări) ------------------------------------------------

    /** Activează/dezactivează o metodă (nu tokenii: vezi setTokenMode). */
    public static function setActive(string $key, bool $active): void
    {
        $method = self::find($key) ?? throw new DomainException('Metoda de plată nu există.');

        if ($key === self::CASH && ! $active) {
            throw new DomainException('Cash nu poate fi dezactivat.');
        }
        if ($key === self::TOKEN) {
            throw new DomainException('Tokenii se setează din starea lor (Activ / Doar încasare / Oprit).');
        }
        if ($method->is_active === $active) {
            return;
        }

        if (! $active && ($reason = self::blockReason($key))) {
            throw new DomainException($reason);
        }

        $method->update(['is_active' => $active]);

        if ($key === self::CREDIT && ! $active) {
            Settings::set('credits_purchasable', false);
        }

        ActivityLogger::log('settings.payment_method_toggled', ($active ? 'A activat' : 'A dezactivat').' metoda de plată „'.$method->label.'".');
    }

    public static function setCreditsPurchasable(bool $on): void
    {
        if ($on && ! self::isEnabled(self::CREDIT)) {
            throw new DomainException('Activează întâi „Acceptă credite la plată".');
        }
        if ((bool) Settings::get('credits_purchasable') === $on) {
            return;
        }

        Settings::set('credits_purchasable', $on);
        ActivityLogger::log('settings.credits_purchase_toggled', $on ? 'A permis cumpărarea de credite de către participanți.' : 'A oprit cumpărarea de credite de către participanți.');
    }

    public static function setTokenMode(string $mode): void
    {
        if (! array_key_exists($mode, self::TOKEN_MODES)) {
            throw new DomainException('Stare de tokeni necunoscută.');
        }

        $current = self::tokenMode();
        if ($mode === $current) {
            return;
        }

        if ($mode !== self::TOKEN_OFF && self::tokenRate() <= 0) {
            throw new DomainException('Setează întâi cursul token → lei (mai mare ca 0).');
        }
        if ($mode === self::TOKEN_OFF && ($reason = self::blockReason(self::TOKEN))) {
            throw new DomainException($reason);
        }

        DB::transaction(function () use ($mode) {
            Settings::set('token_mode', $mode);
            Settings::set('uses_tokens', $mode !== self::TOKEN_OFF); // derivat: restul aplicației citește `uses_tokens`
            self::find(self::TOKEN)?->update(['is_active' => $mode !== self::TOKEN_OFF]);
        });

        ActivityLogger::log('settings.token_mode_changed', 'A schimbat starea tokenilor din „'.self::TOKEN_MODES[$current].'" în „'.self::TOKEN_MODES[$mode].'".');
    }

    public static function setTokenRate(float $rate): void
    {
        if ($rate <= 0 || $rate > 100000) {
            throw new DomainException('Cursul token → lei trebuie să fie mai mare ca 0.');
        }

        $old = self::tokenRate();
        if (abs($old - $rate) < 0.0001) {
            return;
        }

        Settings::set('token_rate', round($rate, 2));
        ActivityLogger::log('settings.token_rate_changed', 'A schimbat cursul token → lei din '.self::num($old).' în '.self::num($rate).' lei.');
    }

    public static function create(string $label): PaymentMethod
    {
        $label = self::validLabel($label);

        $base = 'c_'.Str::limit(Str::slug($label, '_') ?: 'metoda', 40, '');
        $key = $base;
        for ($i = 2; PaymentMethod::query()->where('key', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }

        $method = PaymentMethod::create(['key' => $key, 'label' => $label, 'is_builtin' => false, 'is_active' => true]);
        ActivityLogger::log('settings.payment_method_created', 'A adăugat metoda de plată „'.$label.'".');

        return $method;
    }

    public static function rename(int $id, string $label): void
    {
        $method = PaymentMethod::query()->findOrFail($id);

        if ($method->is_builtin) {
            throw new DomainException('Metodele predefinite nu se redenumesc.');
        }

        $label = self::validLabel($label, $method->id);
        if ($label === $method->label) {
            return;
        }

        $old = $method->label;
        $method->update(['label' => $label]);
        ActivityLogger::log('settings.payment_method_renamed', 'A redenumit metoda de plată „'.$old.'" în „'.$label.'".');
    }

    public static function delete(int $id): void
    {
        $method = PaymentMethod::query()->findOrFail($id);

        if ($method->is_builtin) {
            throw new DomainException('Metodele predefinite nu se șterg. Le poți dezactiva.');
        }
        if (self::isUsed($method->key)) {
            throw new DomainException('„'.$method->label.'" e folosită pe petreceri sau în vânzări. Dezactiveaz-o în loc să o ștergi.');
        }

        $label = $method->label;
        $method->delete();
        ActivityLogger::log('settings.payment_method_deleted', 'A șters metoda de plată „'.$label.'".');
    }

    /** E folosită undeva (pe o petrecere sau într-o plată din vânzări)? Atunci nu se poate șterge. */
    public static function isUsed(string $key): bool
    {
        return Party::query()->whereJsonContains('payment_methods', $key)->exists()
            || DB::table('sale_payments')->where('method', $key)->exists();
    }

    private static function validLabel(string $label, ?int $ignoreId = null): string
    {
        $label = trim(preg_replace('/\s+/', ' ', $label) ?? '');

        if ($label === '') {
            throw new DomainException('Numele metodei de plată este obligatoriu.');
        }
        if (mb_strlen($label) > 60) {
            throw new DomainException('Numele poate avea cel mult 60 de caractere.');
        }

        $duplicate = PaymentMethod::query()
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($duplicate || mb_strtolower($label) === mb_strtolower(self::labels()[self::BENEFIT])) {
            throw new DomainException('Există deja o metodă de plată cu numele „'.$label.'".');
        }

        return $label;
    }

    private static function num(float $n): string
    {
        return $n == floor($n) ? number_format($n, 0, ',', '.') : number_format($n, 2, ',', '.');
    }
}
