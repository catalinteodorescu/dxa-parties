<?php

namespace App\Support;

/**
 * DXA: adaugat (PWA Bar - setări aplicație). Identitatea aplicației de bar (nume, logo, temă); logica e în
 * App\Support\PwaApp. Se editează în Setări › Aplicație bar (admin).
 */
class BarApp extends PwaApp
{
    public const KEY_NAME = 'bar_app_name';

    public const KEY_THEME = 'bar_app_theme';

    public const KEY_LOGO = 'bar_app_logo';

    /** Cheia din sesiune sub care se reține petrecerea aleasă de barman. */
    public const PARTY_SESSION_KEY = 'bar.party_id';

    public const DEFAULT_NAME = 'Bar';

    public const ROUTE_PREFIX = 'bar';

    public const URL_PREFIX = '/bar/';

    public const ICON_CACHE_PREFIX = 'bar';

    public const LABEL = 'bar';
}
