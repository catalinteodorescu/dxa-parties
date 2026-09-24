<?php

namespace App\Support;

use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Storage;

/**
 * Identitatea scolii: nume, doua variante de logo, adresa si linkul de harta.
 * Se editeaza in Setari > Scoala (vezi SettingsRegistry) si se citesc DOAR prin
 * aceasta clasa — nu hardcoda numele/logo-ul in view-uri.
 *
 * Logo-urile au doua variante:
 *  - 'on_color' : pentru fundal colorat/inchis (antetul panoului, panoul de login) = varianta alba;
 *  - 'on_light' : pentru fundal deschis (PDF-uri, login pe mobil).
 * Daca nu s-a incarcat nimic (sau fisierul lipseste de pe disc), se foloseste
 * logo-ul implicit din public/images.
 */
class Branding
{
    public const DEFAULT_NAME = 'Dance Xplosion Academy';

    private const VARIANTS = [
        'on_color' => ['setting' => 'logo_on_color', 'default' => 'images/logo-xplosion-white.png'],
        'on_light' => ['setting' => 'logo_on_light', 'default' => 'images/logo-xplosion.png'],
    ];

    public static function name(): string
    {
        $name = trim((string) Settings::get('school_name'));

        return $name !== '' ? $name : static::DEFAULT_NAME;
    }

    public static function address(): ?string
    {
        $v = trim((string) Settings::get('school_address'));

        return $v !== '' ? $v : null;
    }

    public static function mapsUrl(): ?string
    {
        $v = trim((string) Settings::get('school_maps_url'));

        return $v !== '' ? $v : null;
    }

    /** Calea (pe disk-ul public) a logo-ului incarcat, sau null daca se foloseste cel implicit. */
    public static function uploadedPath(string $variant): ?string
    {
        $path = trim((string) Settings::get(static::VARIANTS[$variant]['setting']));

        return $path !== '' && Storage::disk('public')->exists($path) ? $path : null;
    }

    public static function logoUrl(string $variant): string
    {
        $path = static::uploadedPath($variant);

        return $path ? asset('storage/'.$path) : static::defaultLogoUrl($variant);
    }

    public static function defaultLogoUrl(string $variant): string
    {
        return asset(static::VARIANTS[$variant]['default']);
    }

    /** Calea absoluta pe disc a logo-ului activ (pentru PDF-uri). */
    public static function logoFile(string $variant): string
    {
        $path = static::uploadedPath($variant);

        return $path
            ? Storage::disk('public')->path($path)
            : public_path(static::VARIANTS[$variant]['default']);
    }

    /** Logo-ul ca data URI (PDF-urile nu incarca imagini prin URL). Null daca fisierul nu poate fi citit. */
    public static function logoDataUri(string $variant): ?string
    {
        $file = static::logoFile($variant);
        if (! is_file($file)) {
            return null;
        }

        $mime = @getimagesize($file)['mime'] ?? 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file));
    }
}
