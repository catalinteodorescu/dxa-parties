<?php

namespace App\Support;

/**
 * DXA: adaugat (Aplicația participanților - runda 1). Identitatea PWA-ului participanților, în rădăcina domeniului.
 * Logica (iconița, tema) e în App\Support\PwaApp. Se editează în Setări › Aplicație participanți (admin): nume, logo și temă
 * (tema colorează doar iconița de pe ecranul telefonului); fără alegere, se folosesc logo-ul școlii și tema panoului.
 */
class ParticipantApp extends PwaApp
{
    public const KEY_NAME = 'participant_app_name';

    public const KEY_THEME = 'participant_app_theme';

    public const KEY_LOGO = 'participant_app_logo';

    public const PARTY_SESSION_KEY = 'app.party_id';

    public const DEFAULT_NAME = 'DXA Parties';

    /** Prefixul numelor de rute: `app.home`, `app.login`, `app.manifest` ... */
    public const ROUTE_PREFIX = 'app';

    public const URL_PREFIX = '/';

    public const ICON_CACHE_PREFIX = 'participant';

    public const LABEL = 'participanți';

    /** Fundal întunecat, ca ecranele aplicației. */
    public static function manifestBackground(): string
    {
        return '#120810';
    }

    public static function manifestThemeColor(): string
    {
        return '#120810';
    }

    /**
     * DXA: adaugat. Unde mergem după login / verificarea contului: adresa păstrată în sesiune (`url.intended`) DOAR dacă e o pagină din
     * aplicația participanților. Sesiunea e comună cu admin / Recepție / Bar, care își pun și ele o adresă acolo când te trimit la login.
     */
    public static function intendedOrHome(): string
    {
        $home = route('app.home');
        $intended = session()->pull('url.intended');
        if (! is_string($intended) || $intended === '') {
            return $home;
        }

        $parts = parse_url($intended);
        $host = $parts['host'] ?? null;
        $path = '/'.ltrim($parts['path'] ?? '/', '/');
        if ($host !== null && $host !== request()->getHost()) {
            return $home;
        }
        foreach (['admin', 'receptie', 'bar', 'livewire', 'sesiune'] as $blocked) {
            if ($path === '/'.$blocked || str_starts_with($path, '/'.$blocked.'/')) {
                return $home;
            }
        }

        return $intended;
    }
}
