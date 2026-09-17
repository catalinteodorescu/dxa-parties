<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload de imagini comun (folosit de formularele din admin: Anunțuri, Petreceri etc.).
 *
 * Necesita `WithFileUploads` pe componenta Livewire care il foloseste (pentru
 * $file->getRealPath() / $file->store()).
 */
trait HandlesImageUploads
{
    /**
     * Stochează o imagine încărcată, redimensionată (max $maxWidth px lățime) și
     * compresată, ca pozele mari de pe telefon să nu umple discul. Păstrează
     * transparența la PNG. Dacă GD lipsește sau fișierul nu poate fi procesat,
     * salvează originalul. Returnează path-ul relativ pe disk-ul 'public'.
     *
     * @param  \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|\Illuminate\Http\UploadedFile  $file
     */
    protected function storeUploadedImage($file, string $dir, int $maxWidth = 1600): string
    {
        $realPath = $file->getRealPath();
        $info = @getimagesize($realPath);

        if (! function_exists('imagecreatefromstring') || $info === false) {
            return $file->store($dir, 'public');
        }

        [$width, $height] = $info;
        $isPng = ($info['mime'] ?? null) === 'image/png';

        $src = @imagecreatefromstring(@file_get_contents($realPath));
        if ($src === false) {
            return $file->store($dir, 'public');
        }

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round($height * $maxWidth / $width);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        if ($isPng) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        if ($isPng) {
            imagepng($dst, null, 6);
            $ext = 'png';
        } else {
            imagejpeg($dst, null, 82);
            $ext = 'jpg';
        }
        $binary = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        $path = $dir.'/'.Str::random(40).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
