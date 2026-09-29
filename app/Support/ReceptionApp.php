<?php

namespace App\Support;

use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Storage;

/**
 * DXA: adaugat (PWA Recepție - setări aplicație). Identitatea aplicației de recepție: nume, logo și culoarea temei,
 * independente de tema panoului admin. Se editează în Recepție › Aplicație (admin) și se citesc DOAR prin
 * această clasă. Setările (reception_app_name / reception_app_theme / reception_app_logo) nu sunt în
 * SettingsRegistry (n-au ce căuta pe pagina de Setări generale), deci valorile implicite sunt aici.
 *
 * Iconița de pe ecranul telefonului e generată aici (GD): fundal cu gradient în culorile temei + logo-ul aplicației,
 * cu cache pe disc; adresa iconiței poartă o „versiune” (temă + logo), deci se reîmprospătează când se schimbă ceva.
 */
class ReceptionApp
{
    public const KEY_NAME = 'reception_app_name';

    public const KEY_THEME = 'reception_app_theme';

    public const KEY_LOGO = 'reception_app_logo';

    /** Cheia din sesiune sub care se reține petrecerea aleasă de recepționer. */
    public const PARTY_SESSION_KEY = 'receptie.party_id';

    public const DEFAULT_NAME = 'Recepție';

    public const ICON_SIZES = [180, 192, 512];

    public static function name(): string
    {
        $name = trim((string) Settings::get(self::KEY_NAME));

        return $name !== '' ? $name : self::DEFAULT_NAME;
    }

    /** Cheia paletei alese pentru aplicație; dacă nu s-a ales nimic (sau e invalidă), tema panoului. */
    public static function themeKey(): string
    {
        $key = (string) Settings::get(self::KEY_THEME);

        return isset(Theme::presets()[$key]) ? $key : Theme::key();
    }

    public static function hasCustomTheme(): bool
    {
        return isset(Theme::presets()[(string) Settings::get(self::KEY_THEME)]);
    }

    /** @return array{label: string, primary: string, hover: string, soft: string, dark: string, bright: string} */
    public static function preset(): array
    {
        return Theme::preset(self::themeKey());
    }

    public static function primary(): string
    {
        return self::preset()['primary'];
    }

    /** Variabilele CSS ale temei aplicației, ca text pentru <style>. */
    public static function inlineStyle(): string
    {
        return Theme::inlineStyle(self::themeKey());
    }

    /** Calea (pe disk-ul public) a logo-ului încărcat pentru aplicație, sau null dacă se folosește logo-ul școlii. */
    public static function uploadedLogoPath(): ?string
    {
        $path = trim((string) Settings::get(self::KEY_LOGO));

        return $path !== '' && Storage::disk('public')->exists($path) ? $path : null;
    }

    /** Logo pentru fundal colorat (antet + iconiță): cel încărcat, altfel varianta „pe culoare” a școlii. */
    public static function logoUrl(): string
    {
        $path = self::uploadedLogoPath();

        return $path ? asset('storage/'.$path) : Branding::logoUrl('on_color');
    }

    public static function logoFile(): string
    {
        $path = self::uploadedLogoPath();

        return $path ? Storage::disk('public')->path($path) : Branding::logoFile('on_color');
    }

    /** Versiune scurtă a iconiței: se schimbă când se schimbă tema sau logo-ul. */
    public static function iconVersion(): string
    {
        $file = self::logoFile();
        $stamp = is_file($file) ? filesize($file).'-'.filemtime($file) : 'none';

        return substr(md5(self::themeKey().'|'.$file.'|'.$stamp), 0, 10);
    }

    public static function iconUrl(int $size): string
    {
        return route('receptie.icon', ['size' => $size, 'v' => self::iconVersion()]);
    }

    /** PNG-ul iconiței (gradient în culorile temei + logo), din cache pe disc sau generat acum. */
    public static function iconPng(int $size): string
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);

        $cache = 'pwa-icons/reception-'.self::iconVersion().'-'.$size.'.png';
        $disk = Storage::disk('local');

        if ($disk->exists($cache)) {
            return $disk->get($cache);
        }

        $png = self::renderIcon($size);
        $disk->put($cache, $png);

        return $png;
    }

    /** Desenează iconița: gradient diagonal bright → primary → dark, logo centrat (~62% din lățime). */
    private static function renderIcon(int $size): string
    {
        $p = self::preset();
        $stops = [self::rgb($p['bright']), self::rgb($p['primary']), self::rgb($p['dark'])];

        $img = imagecreatetruecolor($size, $size);
        $max = 2 * ($size - 1);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $t = ($x + $y) / $max;
                [$from, $to, $u] = $t < 0.5 ? [$stops[0], $stops[1], $t / 0.5] : [$stops[1], $stops[2], ($t - 0.5) / 0.5];
                imagesetpixel($img, $x, $y, imagecolorallocate(
                    $img,
                    (int) round($from[0] + ($to[0] - $from[0]) * $u),
                    (int) round($from[1] + ($to[1] - $from[1]) * $u),
                    (int) round($from[2] + ($to[2] - $from[2]) * $u),
                ));
            }
        }

        $logo = @imagecreatefromstring((string) @file_get_contents(self::logoFile()));
        if ($logo !== false) {
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            $w = (int) round($size * 0.62);
            $h = (int) round($lh * $w / $lw);
            if ($h > $size * 0.62) {          // logo înalt: limitează după înălțime
                $h = (int) round($size * 0.62);
                $w = (int) round($lw * $h / $lh);
            }
            imagealphablending($img, true);
            imagecopyresampled($img, $logo, (int) (($size - $w) / 2), (int) (($size - $h) / 2), 0, 0, $w, $h, $lw, $lh);
            imagedestroy($logo);
        }

        ob_start();
        imagepng($img, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
