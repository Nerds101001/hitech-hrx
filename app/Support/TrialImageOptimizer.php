<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shrinks a trial photo's file size while keeping it sharp: honour EXIF rotation, cap the long
 * edge, re-encode as JPEG at a high quality, and drop the EXIF (privacy + a few more KB). A 4-6 MB
 * phone photo lands around 300-600 KB, visually the same. Uses GD (bundled with PHP); if GD is
 * unavailable or the bytes aren't a decodable image, the original is stored unchanged so an upload
 * never fails.
 */
class TrialImageOptimizer
{
    public const MAX_EDGE = 1920;   // px, long edge
    public const QUALITY  = 82;     // JPEG quality
    public const DIR      = 'sales-trial-images';

    /** @return array{path:string,width:?int,height:?int,bytes:?int} */
    public static function store(UploadedFile $file): array
    {
        $folder = self::DIR . '/' . date('Y/m');

        if (!function_exists('imagecreatefromstring')) {
            return self::storeOriginal($file, $folder);
        }

        $img = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (!$img) {
            return self::storeOriginal($file, $folder);
        }

        $img = self::applyExifOrientation($img, $file->getRealPath());
        $w = imagesx($img);
        $h = imagesy($img);

        $scale = min(1, self::MAX_EDGE / max($w, $h));
        if ($scale < 1) {
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $dst;
            $w = $nw;
            $h = $nh;
        }

        ob_start();
        imagejpeg($img, null, self::QUALITY);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        $path = $folder . '/' . Str::uuid()->toString() . '.jpg';
        Storage::disk('public')->put($path, $jpeg);

        return ['path' => $path, 'width' => $w, 'height' => $h, 'bytes' => strlen($jpeg)];
    }

    private static function storeOriginal(UploadedFile $file, string $folder): array
    {
        $path = $file->store($folder, 'public');

        return ['path' => $path, 'width' => null, 'height' => null, 'bytes' => Storage::disk('public')->size($path)];
    }

    /** @param \GdImage $img */
    private static function applyExifOrientation($img, string $path)
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        try {
            $exif = @exif_read_data($path);
        } catch (\Throwable $e) {
            return $img;
        }
        $o = (int) ($exif['Orientation'] ?? 0);
        $rotated = match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };

        return $rotated ?: $img;
    }
}
