<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SitePhotoStorage
{
    public const MAX_UPLOAD_KB = 8192;

    private const MAX_PIXELS = 18_000_000;

    private const MAX_EDGE = 2000;

    public function detectedMime(UploadedFile $file): ?string
    {
        $mime = $file->getMimeType();
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'], true)) {
            return $mime;
        }

        // Some cPanel fileinfo builds classify HEIC as octet-stream. Verify its ISO BMFF brand.
        if (in_array(strtolower($file->getClientOriginalExtension()), ['heic', 'heif'], true)) {
            $handle = fopen($file->getRealPath(), 'rb');
            $header = $handle ? fread($handle, 32) : '';
            if ($handle) {
                fclose($handle);
            }
            if (substr($header, 4, 4) === 'ftyp'
                && in_array(substr($header, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1'], true)) {
                return strtolower($file->getClientOriginalExtension()) === 'heif' ? 'image/heif' : 'image/heic';
            }
        }

        return null;
    }

    /** @return array{path: string, mime_type: string} */
    public function store(UploadedFile $file, int $siteVisitId, string $phase): array
    {
        $mime = $this->detectedMime($file);
        if (! $mime) {
            throw ValidationException::withMessages(['photo' => 'Choose a valid JPG, PNG, WebP, HEIC or HEIF image.']);
        }

        $folder = "site-visits/{$siteVisitId}/{$phase}";
        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            // GD on shared hosting cannot decode HEIC, so keep the original within the 8 MB limit.
            $extension = $mime === 'image/heif' ? 'heif' : 'heic';
            $path = $file->storeAs($folder, Str::uuid().'.'.$extension, 'local');

            return $this->saved($path, $mime);
        }

        $details = @getimagesize($file->getRealPath());
        if (! $details || $details[0] * $details[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['photo' => 'The image is invalid or too large in dimensions. Use an image under 18 megapixels.']);
        }

        $loader = match ($mime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
        };
        if (! function_exists($loader) || ! function_exists('imagejpeg')) {
            throw ValidationException::withMessages(['photo' => 'Image processing is unavailable on this server. Please contact the administrator.']);
        }

        $image = @$loader($file->getRealPath());
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'The image could not be read. Please choose another file.']);
        }

        try {
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
                if (in_array($orientation, [3, 6, 8], true)) {
                    $rotated = imagerotate($image, [3 => 180, 6 => -90, 8 => 90][$orientation], 0);
                    if ($rotated) {
                        imagedestroy($image);
                        $image = $rotated;
                    }
                }
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, self::MAX_EDGE / max($width, $height));
            $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            if (! $target) {
                throw ValidationException::withMessages(['photo' => 'The image could not be resized.']);
            }
            try {
                // Site photographs do not need transparency; white prevents black PNG backgrounds.
                imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
                imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
                ob_start();
                imagejpeg($target, null, 78);
                $bytes = ob_get_clean();
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($image);
        }

        if (! $bytes) {
            throw ValidationException::withMessages(['photo' => 'The image could not be compressed.']);
        }

        // Small originals stay original when conversion would enlarge them and resizing was unnecessary.
        if ($scale === 1 && strlen($bytes) >= $file->getSize()) {
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $path = $file->storeAs($folder, Str::uuid().'.'.$extension, 'local');

            return $this->saved($path, $mime);
        }

        $path = $folder.'/'.Str::uuid().'.jpg';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Please try again.']);
        }

        return ['path' => $path, 'mime_type' => 'image/jpeg'];
    }

    /** @return array{path: string, mime_type: string} */
    private function saved(string|false $path, string $mime): array
    {
        if (! $path) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Please try again.']);
        }

        return ['path' => $path, 'mime_type' => $mime];
    }
}
