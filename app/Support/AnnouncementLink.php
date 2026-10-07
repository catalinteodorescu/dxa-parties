<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Party;

/**
 * DXA: adaugat (runda 44). Butonul unui anunț poate duce în aplicație („deep link intern”): o petrecere anume sau o pagină
 * a aplicației. Destinația se păstrează ca text în `announcements.link_target` („party:12”, „tickets” …), exclusiv cu `url` (link extern).
 * Pagina anunțului cere rezolvarea la afișare: o petrecere ștearsită/ciornă/dezactivată sau o destinație nepotrivită vizitatorului (ex. „Creează cont”
 * pentru cine e deja logat) nu produce butonul, ca să nu ducă într-o pagină goală.
 */
class AnnouncementLink
{
    /** cheie => [etichetă în admin, textul implicit al butonului, numele rutei, parametrii rutei] */
    private const PAGES = [
        'parties' => ['Lista de petreceri', 'Vezi petrecerile', 'app.parties', []],
        'saved' => ['Petreceri favorite', 'Vezi favoritele', 'app.parties', ['lista' => 'saved']],
        'tickets' => ['Biletele mele', 'Vezi biletele', 'app.tickets', []],
        'wallet' => ['Portofel', 'Deschide portofelul', 'app.wallet', []],
        'account' => ['Contul meu', 'Contul meu', 'app.account', []],
        'register' => ['Creare cont (doar nelogați)', 'Creează cont', 'app.register', []],
        'announcements' => ['Lista de anunțuri', 'Vezi anunțurile', 'app.announcements', []],
    ];

    /** @return array<string, string> cheie => etichetă, pentru selectul din admin (paginile aplicației + petrecerile publicate). */
    public static function options(): array
    {
        $out = ['' => 'Alege destinația…'];
        foreach (self::PAGES as $key => $def) {
            $out[$key] = $def[0];
        }
        foreach (Party::query()->where('status', 'published')->orderByDesc('start_date')->limit(200)->get(['id', 'name', 'start_date']) as $p) {
            $out['party:'.$p->id] = 'Petrecere: '.$p->name.($p->start_date ? ' ('.$p->start_date->format('d.m.Y').')' : '');
        }

        return $out;
    }

    /** O destinație acceptată la salvare (pagină cunoscută sau petrecere existentă, neciornă). */
    public static function isValidTarget(?string $target): bool
    {
        if ($target === null || $target === '') {
            return false;
        }
        if (isset(self::PAGES[$target])) {
            return true;
        }

        return (bool) preg_match('/^party:(\d+)$/', $target, $m)
            && Party::query()->whereKey((int) $m[1])->where('status', '!=', 'draft')->exists();
    }

    /**
     * @return object{href: string, external: bool, label: string}|null null = fără buton
     */
    public static function resolve(Announcement $a, bool $loggedIn): ?object
    {
        $label = trim((string) $a->url_label);

        if ($a->link_target === null || $a->link_target === '') {
            return $a->url ? (object) ['href' => $a->url, 'external' => true, 'label' => $label !== '' ? $label : 'Vezi mai mult'] : null;
        }

        $target = $a->link_target;
        if (isset(self::PAGES[$target])) {
            if ($target === 'register' && $loggedIn) {
                return null;
            }
            [, $default, $route, $params] = self::PAGES[$target];

            return (object) ['href' => route($route, $params), 'external' => false, 'label' => $label !== '' ? $label : $default];
        }

        if (preg_match('/^party:(\d+)$/', $target, $m)) {
            $party = Party::query()->find((int) $m[1]);
            if (! $party || $party->isDraft() || ! $party->is_active || ($party->audience === 'auth' && ! $loggedIn)) {
                return null;
            }

            return (object) ['href' => route('app.party', $party), 'external' => false, 'label' => $label !== '' ? $label : 'Vezi petrecerea'];
        }

        return null;
    }
}
