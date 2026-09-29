<?php

namespace App\Support;

/**
 * DXA: adaugat (PWA Recepție - setări aplicație). Identitatea aplicației de recepție (nume, logo, temă); logica e în
 * App\Support\PwaApp. Se editează în Setări › Aplicație recepție (admin).
 */
class ReceptionApp extends PwaApp
{
    public const KEY_NAME = 'reception_app_name';

    public const KEY_THEME = 'reception_app_theme';

    public const KEY_LOGO = 'reception_app_logo';

    /** Cheia din sesiune sub care se reține petrecerea aleasă de recepționer. */
    public const PARTY_SESSION_KEY = 'receptie.party_id';

    public const DEFAULT_NAME = 'Recepție';

    public const ROUTE_PREFIX = 'receptie';

    public const URL_PREFIX = '/receptie/';

    public const ICON_CACHE_PREFIX = 'reception';

    public const LABEL = 'recepție';
}
