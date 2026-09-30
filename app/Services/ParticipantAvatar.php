<?php

namespace App\Services;

use App\Models\Participant;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * DXA: adaugat (Aplicația participanților - runda 12). Poza de profil: se decodează și se RE-CODIFICĂ mereu pe server
 * (kare 256×256, JPEG) — orice ar fi încărcat utilizatorul, pe disc ajunge doar o imagine curată, fără metadate și mică.
 * Discul e cel privat (`local`): poza se servește doar participantului logat, prin ruta `app.avatar`.
 */
class ParticipantAvatar
{
    public const SIZE = 256;

    public const MAX_KB = 8192;

    public static function store(Participant $participant, UploadedFile $file): void
    {
        $bytes = @file_get_contents($file->getRealPath());
        $source = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new DomainException('Fișierul nu e o imagine validă (folosește JPG, PNG sau WebP).');
        }

        $source = self::orient($source, $file->getRealPath());
        $w = imagesx($source);
        $h = imagesy($source);
        $side = min($w, $h);

        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // PNG cu transparență → fundal alb
        imagecopyresampled($out, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($out, null, 85);
        $jpeg = (string) ob_get_clean();

        $path = 'participant-avatars/'.$participant->uuid.'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        $participant->forceFill(['avatar_path' => $path, 'avatar_updated_at' => now()])->save();
    }

    public static function delete(Participant $participant): void
    {
        if ($participant->avatar_path) {
            Storage::disk('local')->delete($participant->avatar_path);
        }
        $participant->forceFill(['avatar_path' => null, 'avatar_updated_at' => null])->save();
    }

    /** Aplică orientarea EXIF (pozele de pe telefon) când extensia există; altfel imaginea rămâne cum e. */
    private static function orient(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle !== 0 ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}
