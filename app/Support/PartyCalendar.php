<?php

namespace App\Support;

use App\Models\Party;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 41). „Adaugă în calendar”: fișier .ics (iCalendar) pentru o petrecere. Merge pe iPhone (Calendar), Android (Google Calendar și altele)
 * și pe calculator. Petrecere simplă = un eveniment; festival = câte un eveniment pe zi (fiecare cu ora lui). Orele se scriu în UTC (Z), din fusul orar
 * al aplicației. Fără oră de început = eveniment pe toată ziua; fără oră de sfârșit = 3 ore.
 */
class PartyCalendar
{
    public const DEFAULT_HOURS = 3;

    /** @return array<int, array{title: string, start: Carbon, end: ?Carbon, all_day: bool}> */
    public static function events(Party $party): array
    {
        $rows = [];

        if ($party->isFestival() && ! empty($party->days)) {
            $n = count($party->days);
            foreach (array_values($party->days) as $i => $day) {
                if (empty($day['date'])) {
                    continue;
                }
                $rows[] = [$party->name.($n > 1 ? ' (ziua '.($i + 1).')' : ''), (string) $day['date'], $day['start_time'] ?? null, $day['end_time'] ?? null];
            }
        } elseif ($party->start_date) {
            $rows[] = [$party->name, $party->start_date->format('Y-m-d'), $party->start_time, $party->end_time];
        }

        $out = [];
        foreach ($rows as [$title, $date, $startTime, $endTime]) {
            if (! $startTime) {
                $out[] = ['title' => $title, 'start' => Carbon::parse($date)->startOfDay(), 'end' => null, 'all_day' => true];

                continue;
            }

            [$start, $end] = Party::dayInterval($date, $startTime, $endTime);
            $out[] = ['title' => $title, 'start' => $start, 'end' => $end ?? $start->copy()->addHours(self::DEFAULT_HOURS), 'all_day' => false];
        }

        return $out;
    }

    public static function ics(Party $party, string $pageUrl): string
    {
        $stamp = now()->utc()->format('Ymd\THis\Z');
        $host = parse_url($pageUrl, PHP_URL_HOST) ?: 'dxa.local';
        $location = trim(implode(', ', array_filter([$party->location_name, $party->location_address])));
        $description = 'Detalii și bilete: '.$pageUrl;

        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//DXA Parties//RO', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];

        foreach (self::events($party) as $i => $e) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:party-'.$party->id.'-'.($i + 1).'@'.$host;
            $lines[] = 'DTSTAMP:'.$stamp;
            if ($e['all_day']) {
                $lines[] = 'DTSTART;VALUE=DATE:'.$e['start']->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:'.$e['start']->copy()->addDay()->format('Ymd');
            } else {
                $lines[] = 'DTSTART:'.$e['start']->copy()->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTEND:'.$e['end']->copy()->utc()->format('Ymd\THis\Z');
            }
            $lines[] = 'SUMMARY:'.self::escape($e['title']);
            if ($location !== '') {
                $lines[] = 'LOCATION:'.self::escape($location);
            }
            $lines[] = 'DESCRIPTION:'.self::escape($description);
            $lines[] = 'URL:'.$pageUrl;
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    public static function filename(Party $party): string
    {
        return (Str::slug($party->name) ?: 'petrecere').'.ics';
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Linii de cel mult 75 de octeți, continuate cu un spațiu (RFC 5545), fără a tăia un caracter UTF-8 la mijloc. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = [];
        $limit = 75;
        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $out[] = $chunk;
            $line = mb_strcut($line, strlen($chunk), null, 'UTF-8');
            $limit = 74;   // liniile de continuare încep cu un spațiu
        }
        $out[] = $line;

        return implode("\r\n ", $out);
    }
}
