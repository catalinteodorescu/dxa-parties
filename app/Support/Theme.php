<?php

namespace App\Support;

use App\Support\Settings\Settings;

/**
 * Culoarea temei aplicatiei: un set mic de palete prestabilite (nu color picker).
 *
 * Fiecare paleta defineste cele 5 trepte de "primary" din resources/css/app.css
 * (primary, hover, soft, dark, bright). La randare, layout-urile injecteaza
 * <x-theme-style />, care suprascrie variabilele CSS pe :root la runtime —
 * deci schimbarea culorii NU cere `npm run build`. Setarea se numeste
 * `theme_color` (vezi SettingsRegistry, sectiunea "Aspect").
 *
 * Ca sa adaugi o paleta: un element nou in presets(). Verifica contrastul textului
 * alb pe `primary` (butoanele sunt text-white pe bg-primary; tinta >= 4.5) si
 * distanta fata de culorile de sistem: danger (rosu), warning (auriu), info
 * (albastru), purple (duplicare), success (verde).
 */
class Theme
{
    public const DEFAULT = 'terracotta';

    /** @return array<string, array{label: string, primary: string, hover: string, soft: string, dark: string, bright: string}> */
    public static function presets(): array
    {
        return [
            'terracotta' => [
                'label' => 'Terracotta',
                'primary' => '#DD6441', 'hover' => '#C4512F', 'soft' => '#FAE6DE', 'dark' => '#A83E22', 'bright' => '#E8825F',
            ],
            'rose' => [
                'label' => 'Coral',
                'primary' => '#D13B66', 'hover' => '#BA355B', 'soft' => '#F9E4EA', 'dark' => '#9F2D4E', 'bright' => '#D8587D',
            ],
            'indigo' => [
                'label' => 'Indigo',
                'primary' => '#4F46E5', 'hover' => '#463ECC', 'soft' => '#E6E5FB', 'dark' => '#3C35AE', 'bright' => '#6962E9',
            ],
            'emerald' => [
                'label' => 'Smarald',
                'primary' => '#0B7A62', 'hover' => '#0A6D57', 'soft' => '#DDECE9', 'dark' => '#085D4A', 'bright' => '#308E7A',
            ],
            'magenta' => [
                'label' => 'Magenta',
                'primary' => '#A6248F', 'hover' => '#94207F', 'soft' => '#F3E0EF', 'dark' => '#7E1B6D', 'bright' => '#B345A0',
            ],
            'graphite' => [
                'label' => 'Grafit',
                'primary' => '#27272A', 'hover' => '#0F0F11', 'soft' => '#EFEEED', 'dark' => '#09090B', 'bright' => '#52525B',
            ],
        ];
    }

    /** Cheia paletei alese (cu revenire la implicita daca in DB e o valoare necunoscuta). */
    public static function key(): string
    {
        $key = (string) Settings::get('theme_color');

        return isset(static::presets()[$key]) ? $key : static::DEFAULT;
    }

    public static function preset(?string $key = null): array
    {
        $key = $key !== null && isset(static::presets()[$key]) ? $key : static::key();

        return static::presets()[$key];
    }

    /** Culoarea principala a paletei active (ex. pentru PDF-uri, unde nu exista variabile CSS). */
    public static function primary(): string
    {
        return static::preset()['primary'];
    }

    /** @return array<string, string> variabila CSS => valoare */
    public static function cssVars(?string $key = null): array
    {
        $p = static::preset($key);

        return [
            '--color-primary' => $p['primary'],
            '--color-primary-hover' => $p['hover'],
            '--color-primary-soft' => $p['soft'],
            '--color-primary-dark' => $p['dark'],
            '--color-primary-bright' => $p['bright'],
        ];
    }

    /** Variabilele ca text pentru atributul style (ex. previzualizare izolata pe un container). */
    public static function inlineStyle(?string $key = null): string
    {
        return collect(static::cssVars($key))->map(fn ($v, $k) => "$k:$v")->implode(';');
    }
}
