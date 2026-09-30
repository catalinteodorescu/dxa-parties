<?php

namespace App\Support;

use App\Models\Party;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Aplicația participanților - runda 1). Text și cifre pentru afișarea publică a unei petreceri (listă,
 * card, detaliu): date în română, preț „de la”, treptele de preț live. Doar citire, fără reguli de business noi
 * (prețurile vin din Party::priceForTypeAt / Party::discountActive).
 */
class PartyPublic
{
    public static function day(Party $party): ?Carbon
    {
        return $party->start_date?->copy()->locale('ro');
    }

    public static function dayNumber(Party $party): string
    {
        return (string) (self::day($party)?->format('j') ?? '');
    }

    public static function monthShort(Party $party): string
    {
        return mb_strtoupper((string) self::day($party)?->translatedFormat('M'));
    }

    /** „sâmbătă, 3 octombrie” sau, la festival, „3 – 5 octombrie”. */
    public static function dateLabel(Party $party): string
    {
        $start = self::day($party);
        if (! $start) {
            return '';
        }

        if ($party->isFestival() && $party->end_date && ! $party->end_date->isSameDay($party->start_date)) {
            $end = $party->end_date->copy()->locale('ro');

            return $start->isSameMonth($end)
                ? $start->format('j').' – '.$end->translatedFormat('j F')
                : $start->translatedFormat('j F').' – '.$end->translatedFormat('j F');
        }

        return $start->translatedFormat('l, j F');
    }

    /** „22:00 – 03:00” (petrecere simplă) sau „2 zile” (festival). */
    public static function timeLabel(Party $party): string
    {
        if ($party->isFestival()) {
            $n = count($party->days ?? []);

            return $n > 0 ? $n.' '.($n === 1 ? 'zi' : 'zile') : '';
        }

        $s = self::hm($party->start_time);
        $e = self::hm($party->end_time);

        return trim($s.($e ? ' – '.$e : ''));
    }

    public static function hm(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '';
    }

    public static function lei(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',').' lei';
    }

    /** „de la 25 lei” / „25 lei” / „Intrare gratuită” / null când nu are preț. */
    public static function priceLabel(Party $party): ?string
    {
        if ($party->is_free) {
            return 'Intrare gratuită';
        }

        $price = $party->currentPrice();
        if ($price === null) {
            return null;
        }

        $label = $price <= 0 ? 'Gratuit' : self::lei($price);

        return $party->hasMultipleTicketTypes() && $price > 0 ? 'de la '.$label : $label;
    }

    /**
     * Prețurile pe tipuri de bilet, cu treptele „live” (Early bird etc.): pentru fiecare tip, rândurile în ordinea în care
     * se aplică; `on` marchează prețul valabil ACUM.
     *
     * @return array<int, array{name: string, current: float, rows: array<int, array{label: string, price: float, note: string, on: bool}>}>
     */
    public static function tickets(Party $party, ?Carbon $now = null): array
    {
        $now ??= now();
        $out = [];

        foreach ($party->entryTicketTypes() as $entry) {
            $type = $entry['type'];
            $current = $party->priceForTypeAt($type, $now);
            if ($current === null) {
                continue;
            }

            $rows = [];
            foreach ($type['discounts'] ?? $type['tiers'] ?? [] as $t) {
                if (! isset($t['price']) || ! is_numeric($t['price']) || ! Party::discountActive($t['until'] ?? null, $now)) {
                    continue;
                }
                $until = trim((string) ($t['until'] ?? ''));
                $rows[] = [
                    'label' => trim((string) ($t['label'] ?? '')) ?: 'Ofertă',
                    'price' => (float) $t['price'],
                    'note' => $until === '' ? 'până la epuizare' : 'până '.self::untilLabel($until),
                    'sort' => $until === '' ? '9999' : $until,
                    'on' => abs((float) $t['price'] - $current) < 0.005,
                ];
            }
            usort($rows, fn ($a, $b) => strcmp($a['sort'], $b['sort']));

            if (isset($type['price']) && is_numeric($type['price'])) {
                $rows[] = [
                    'label' => $rows ? 'Preț normal' : 'Preț bilet',
                    'price' => (float) $type['price'],
                    'note' => '',
                    'sort' => '9999',
                    'on' => abs((float) $type['price'] - $current) < 0.005,
                ];
            }

            // Doar rândul cel mai potrivit e „acum” (două trepte cu același preț nu se marchează amândouă).
            $marked = false;
            foreach ($rows as &$r) {
                $r['on'] = $r['on'] && ! $marked;
                $marked = $marked || $r['on'];
                unset($r['sort']);
            }
            unset($r);

            $out[] = ['name' => $entry['name'], 'current' => $current, 'rows' => $rows];
        }

        return $out;
    }

    /** „2 oct., 22:00” din valoarea `until` (Y-m-d sau Y-m-d\TH:i). */
    public static function untilLabel(string $until): string
    {
        $c = Carbon::parse($until)->locale('ro');

        return mb_strlen($until) <= 10 ? $c->translatedFormat('j M') : $c->translatedFormat('j M, H:i');
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = collect($parts)->filter()->take(2)->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode('');

        return $letters !== '' ? $letters : '?';
    }

    public static function imageUrl(Party $party): ?string
    {
        return $party->imageUrl();
    }
}
