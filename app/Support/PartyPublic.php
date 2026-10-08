<?php

namespace App\Support;

use App\Models\Party;
use App\Services\TicketOrders;
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
    /**
     * Runda 62. Descrierea din aplicație, fără numele petrecerii repetat: descrierea generată din admin începe cu un rând cu numele
     * (la blocurile pe limbă, precedat de steag + [RO]/[EN]/[ES]). Rândul se scoate doar dacă e chiar numele petrecerii; marcajul de limbă rămâne.
     */
    public static function description(Party $party): string
    {
        $text = trim(str_replace("\r\n", "\n", (string) $party->description));
        $name = mb_strtolower(trim((string) $party->name));
        if ($text === '' || $name === '') {
            return $text;
        }

        $blocks = array_map(function (string $block) use ($name): string {
            $lines = explode("\n", $block, 2);
            $first = trim($lines[0]);
            $tag = '';
            if (preg_match('/^(.*?\[(?:RO|EN|ES)\])\s*(.*)$/u', $first, $m)) {
                $tag = $m[1];
                $first = trim($m[2]);
            }
            if (mb_strtolower($first) !== $name) {
                return $block;
            }

            return trim($tag.($tag !== '' && isset($lines[1]) ? "\n\n" : '').ltrim($lines[1] ?? ''));
        }, explode("\n\n———\n\n", $text));

        return trim(implode("\n\n———\n\n", array_filter($blocks, fn (string $b) => trim($b) !== '')));
    }

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

    /**
     * DXA: adaugat (runda 33). Cel mai mic preț la care se poate cumpăra acum un bilet: minimul dintre tipurile de bilet, cu reducerile
     * pe dată/oră și treptele „primele N” (la vânzarea online) deja aplicate; tipurile epuizate online se sar (dacă toate sunt epuizate,
     * se iau toate). Null când niciun tip n-are preț.
     */
    public static function fromPrice(Party $party): ?float
    {
        $all = [];
        $open = [];
        foreach ($party->entryTicketTypes() as $entry) {
            $type = $entry['type'];
            $tiers = $party->online_sales && ! empty($type['qty_tiers']);
            $price = $tiers
                ? TicketOrders::unitPrice($party, $type, TicketOrders::tierSoldCount($party, $entry['name']))
                : $party->priceForTypeAt($type);
            if ($price === null) {
                continue;
            }
            $all[] = (float) $price;
            if (! $party->online_sales || TicketOrders::available($party, $entry['name']) !== 0) {
                $open[] = (float) $price;
            }
        }
        $pool = $open ?: $all;

        return $pool ? min($pool) : null;
    }

    /** Are petrecerea mai multe tipuri de bilet definite (cu nume sau preț), chiar dacă unul nu are acum un preț valabil? */
    public static function hasMultipleTypes(Party $party): bool
    {
        return collect($party->ticket_types ?? [])
            ->filter(fn ($t) => trim((string) ($t['name'] ?? '')) !== '' || (isset($t['price']) && is_numeric($t['price'])) || ! empty($t['discounts']))
            ->count() > 1;
    }

    /** „de la 25 lei” / „Gratuit” / „Intrare gratuită” / null când nu are preț. */
    public static function priceLabel(Party $party): ?string
    {
        if ($party->is_free) {
            return 'Intrare gratuită';
        }

        $price = self::fromPrice($party);
        if ($price === null) {
            return null;
        }

        // „de la” doar când sunt mai multe tipuri de bilet (cu un singur tip nu există mai multe prețuri de comparat).
        return $price <= 0 ? 'Gratuit' : (self::hasMultipleTypes($party) ? 'de la ' : '').self::lei($price);
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
                if (! isset($t['price']) || ! is_numeric($t['price'])
                    || ! Party::discountActive($t['until'] ?? null, $now) || ! Party::discountActive($t['enter_until'] ?? null, $now)) {
                    continue;
                }
                $until = trim((string) ($t['until'] ?? ''));
                $enter = trim((string) ($t['enter_until'] ?? ''));
                $notes = [];
                if ($until !== '') {
                    $notes[] = 'cumperi până '.self::untilLabel($until);
                }
                if ($enter !== '') {
                    $notes[] = 'intri până '.self::untilLabel($enter);
                }
                $sortKey = $until !== '' && $enter !== '' ? min($until, $enter) : ($until !== '' ? $until : $enter);
                $rows[] = [
                    'label' => trim((string) ($t['label'] ?? '')) ?: 'Ofertă',
                    'price' => (float) $t['price'],
                    'note' => $notes ? implode(' · ', $notes) : 'până la epuizare',
                    'sort' => $sortKey === '' ? '9999' : $sortKey,
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

            // DXA: adaugat (runda 14). Trepte „primele N bilete” (doar cu vânzare online): rândurile încă deschise, cu câte mai sunt;
            // treapta activă devine „ACUM” (dacă e la fel de ieftină ca prețul curent), iar prețul curent afișat o reflectă.
            if ($party->online_sales && ! empty($type['qty_tiers'])) {
                $sold = TicketOrders::tierSoldCount($party, $entry['name']);   // runda 25: doar biletele cu preț ≠ 0 ocupă locuri din treaptă
                $activeTier = TicketOrders::tierPrice($type, $sold);
                $tierActive = $activeTier !== null && $activeTier <= $current + 0.005;

                $tiers = collect($type['qty_tiers'])->filter(fn ($q) => isset($q['price'], $q['first']) && is_numeric($q['price']) && is_numeric($q['first']))
                    ->sortBy(fn ($q) => (int) $q['first'])->values();
                $prevFirst = 0;
                $tierRows = [];
                foreach ($tiers as $q) {
                    $first = (int) $q['first'];
                    $left = $first - max($sold, 0);
                    if ($left > 0) {
                        $isActive = $tierActive && abs((float) $q['price'] - (float) $activeTier) < 0.005 && $sold >= $prevFirst;
                        $tierRows[] = [
                            'label' => trim((string) ($q['label'] ?? '')) ?: 'Primele '.$first.' bilete',
                            'price' => (float) $q['price'],
                            'note' => 'mai '.($left === 1 ? 'e 1 bilet' : 'sunt '.$left.' bilete').' la acest preț',
                            'sort' => '0',
                            'on' => $isActive,
                        ];
                    }
                    $prevFirst = $first;
                }
                if ($tierActive) {
                    foreach ($rows as &$r) {
                        $r['on'] = false;
                    }
                    unset($r);
                    $current = (float) $activeTier;
                }
                foreach ($rows as &$r) {
                    if ($r['label'] === 'Preț bilet') {
                        $r['label'] = 'Preț normal'; // după treptele „primele N”, prețul de bază e „normal”
                    }
                }
                unset($r);
                $rows = array_merge($tierRows, $rows);
            }

            // Prețul de bază tăiat: rândurile (oferte, trepte) mai ieftine decât prețul de bază îl arată ca „înainte”.
            $base = isset($type['price']) && is_numeric($type['price']) ? (float) $type['price'] : null;
            foreach ($rows as &$r) {
                $r['was'] = $base !== null && $r['price'] < $base - 0.005 ? $base : null;
            }
            unset($r);

            // Doar rândul cel mai potrivit e „acum” (două trepte cu același preț nu se marchează amândouă).
            $marked = false;
            foreach ($rows as &$r) {
                $r['on'] = $r['on'] && ! $marked;
                $marked = $marked || $r['on'];
                unset($r['sort']);
            }
            unset($r);

            $combos = $party->online_sales ? array_map(fn ($c) => $c + ['note' => TicketOrders::comboNote($party, $type, $entry['name'], $c)], array_values(TicketOrders::combos($type))) : [];

            $out[] = ['name' => $entry['name'], 'current' => $current, 'base' => $base, 'rows' => $rows, 'combos' => $combos];
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
