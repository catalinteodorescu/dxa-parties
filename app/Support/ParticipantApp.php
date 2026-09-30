<?php

namespace App\Support;

/**
 * DXA: adaugat (Aplicația participanților - runda 1). Identitatea PWA-ului participanților, în rădăcina domeniului.
 * Logica (iconița, tema) e în App\Support\PwaApp. Nu are încă pagină de setări proprie: numele, tema și logo-ul vin din
 * valorile implicite (logo-ul școlii, tema panoului); cheile de mai jos sunt pregătite pentru o pagină de setări ulterioară.
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
}
