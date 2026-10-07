<?php

namespace App\Services;

use App\Support\Settings\Settings;
use DomainException;

/**
 * DXA: adaugat (runda 51). Bonusul la încărcarea de credite și limitele încărcării (Setări › Metode de plată › Credite).
 *
 * Reguli:
 *  - praguri „de la X lei → +Y%”; se aplică CEL MAI MARE prag atins (nu se cumulează); bonusul = suma × Y%, rotunjit la 2 zecimale;
 *  - bonusul e credit obișnuit (fără expirare, se consumă și se returnează ca orice credit); în ledger apare în `amount`, iar `bonus` spune
 *    câtă parte din el a fost gratis (banii încasați = amount − bonus);
 *  - se aplică la încărcarea din aplicație (CreditTopups) și la vânzarea la Recepție (CreditLedger::sell), nu la ajustări/refund/manual.
 */
class CreditBonus
{
    public const MAX_TIERS = 10;

    public const MAX_PERCENT = 100;

    /** Plafon pentru maximul de încărcare setat (CreditLedger::MAX_LOAD e 5000 și trebuie să încapă și bonusul). */
    public const MAX_TOPUP_LIMIT = 2000;

    /** @return array<int, array{min: float, percent: float}> pragurile valide, crescător după `min` */
    public static function tiers(): array
    {
        $raw = json_decode((string) Settings::get('credit_bonus_tiers'), true);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $t) {
            $min = is_array($t) && isset($t['min']) && is_numeric($t['min']) ? round((float) $t['min'], 2) : 0.0;
            $percent = is_array($t) && isset($t['percent']) && is_numeric($t['percent']) ? round((float) $t['percent'], 2) : 0.0;
            if ($min > 0 && $percent > 0 && $percent <= self::MAX_PERCENT) {
                $out[(string) $min] = ['min' => $min, 'percent' => $percent];
            }
        }
        ksort($out, SORT_NUMERIC);

        return array_values($out);
    }

    /** Pragul care se aplică unei sume (cel mai mare cu min ≤ sumă), sau null. */
    public static function tierFor(float $amount): ?array
    {
        $found = null;
        foreach (self::tiers() as $t) {
            if ($amount + 0.00001 >= $t['min']) {
                $found = $t;
            }
        }

        return $found;
    }

    public static function percentFor(float $amount): float
    {
        return self::tierFor($amount)['percent'] ?? 0.0;
    }

    /** Bonusul în lei pentru o sumă încărcată. */
    public static function bonusFor(float $amount): float
    {
        $percent = self::percentFor($amount);

        return $percent > 0 ? round($amount * $percent / 100, 2) : 0.0;
    }

    /** Următorul prag peste suma dată (pentru îndemnul „încă X lei și primești Y%”), sau null. */
    public static function nextTier(float $amount): ?array
    {
        foreach (self::tiers() as $t) {
            if ($t['min'] > $amount + 0.00001) {
                return $t;
            }
        }

        return null;
    }

    /**
     * Salvează pragurile. Intrările goale se ignoră; restul trebuie să fie valide (min > 0, 0 < % ≤ 100, fără praguri duplicate).
     *
     * @param  array<int, array{min?: mixed, percent?: mixed}>  $rows
     *
     * @throws DomainException
     */
    public static function saveTiers(array $rows): void
    {
        $clean = [];
        $seen = [];
        foreach ($rows as $r) {
            $min = trim(str_replace(',', '.', (string) ($r['min'] ?? '')));
            $percent = trim(str_replace(',', '.', (string) ($r['percent'] ?? '')));
            if ($min === '' && $percent === '') {
                continue;
            }
            if (! is_numeric($min) || (float) $min <= 0) {
                throw new DomainException('Pragul „de la” trebuie să fie o sumă mai mare ca 0.');
            }
            if (! is_numeric($percent) || (float) $percent <= 0 || (float) $percent > self::MAX_PERCENT) {
                throw new DomainException('Bonusul trebuie să fie un procent între 0 și '.self::MAX_PERCENT.'.');
            }
            $key = (string) round((float) $min, 2);
            if (isset($seen[$key])) {
                throw new DomainException('Pragul de '.$key.' lei apare de două ori.');
            }
            $seen[$key] = true;
            $clean[] = ['min' => round((float) $min, 2), 'percent' => round((float) $percent, 2)];
        }
        if (count($clean) > self::MAX_TIERS) {
            throw new DomainException('Cel mult '.self::MAX_TIERS.' praguri.');
        }
        usort($clean, fn ($a, $b) => $a['min'] <=> $b['min']);

        Settings::set('credit_bonus_tiers', $clean ? json_encode($clean) : '');
        ActivityLogger::log('credits.bonus_tiers_updated', $clean
            ? 'A setat bonusul la încărcarea de credite: '.collect($clean)->map(fn ($t) => 'de la '.PaymentRows::money($t['min']).' lei +'.rtrim(rtrim(number_format($t['percent'], 2, ',', ''), '0'), ',').'%')->implode('; ').'.'
            : 'A oprit bonusul la încărcarea de credite (fără praguri).');
    }

    // ---- Limite și sume rapide -------------------------------------------

    public static function min(): float
    {
        return max(1.0, (float) Settings::get('credit_topup_min'));
    }

    public static function max(): float
    {
        return max(self::min(), min(self::MAX_TOPUP_LIMIT, (float) Settings::get('credit_topup_max')));
    }

    /** @return array<int, int> sumele rapide din dialog (în limitele min–max), crescător */
    public static function presets(): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', (string) Settings::get('credit_topup_presets'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $p) {
            if (ctype_digit($p) && (int) $p >= self::min() && (int) $p <= self::max()) {
                $out[(int) $p] = (int) $p;
            }
        }
        ksort($out);

        return array_values($out);
    }

    /**
     * @throws DomainException
     */
    public static function saveLimits(string $presets, string $min, string $max): void
    {
        $min = str_replace(',', '.', trim($min));
        $max = str_replace(',', '.', trim($max));
        if (! is_numeric($min) || (float) $min < 1) {
            throw new DomainException('Suma minimă de încărcat trebuie să fie cel puțin 1 leu.');
        }
        if (! is_numeric($max) || (float) $max < (float) $min || (float) $max > self::MAX_TOPUP_LIMIT) {
            throw new DomainException('Suma maximă trebuie să fie între minim și '.self::MAX_TOPUP_LIMIT.' lei.');
        }
        $list = [];
        foreach (preg_split('/[\s,;]+/', trim($presets), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $p) {
            if (! ctype_digit($p) || (int) $p < (float) $min || (int) $p > (float) $max) {
                throw new DomainException('Sumele rapide trebuie să fie numere întregi între minim și maxim, separate prin virgulă.');
            }
            $list[(int) $p] = (int) $p;
        }
        if (count($list) > 6) {
            throw new DomainException('Cel mult 6 sume rapide.');
        }
        ksort($list);

        Settings::set('credit_topup_min', (float) $min);
        Settings::set('credit_topup_max', (float) $max);
        Settings::set('credit_topup_presets', implode(',', $list));
        ActivityLogger::log('credits.topup_limits_updated', 'A setat încărcarea de credite: minim '.PaymentRows::money((float) $min).' lei, maxim '.PaymentRows::money((float) $max).' lei, sume rapide '.($list ? implode(', ', $list) : '—').'.');
    }
}
