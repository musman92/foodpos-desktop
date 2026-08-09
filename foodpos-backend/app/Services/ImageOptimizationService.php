<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageOptimizationService
{
    /**
     * Resize and compress an upload, then store it on the public disk.
     */
    public function storeFromUpload(UploadedFile $file, string $directory, string $preset = 'menu'): string
    {
        $disk = config('images.disk', 'public');
        $settings = config("images.presets.{$preset}") ?? config('images.presets.menu');

        try {
            $optimized = $this->optimize($file, $settings);
        } catch (\Throwable $e) {
            report($e);

            return $file->store(trim($directory, '/'), $disk);
        }

        $extension = $optimized['extension'];
        $path = trim($directory, '/').'/'.Str::uuid().'.'.$extension;

        Storage::disk($disk)->put($path, $optimized['binary']);

        return $path;
    }

    /**
     * Re-optimize an existing file on the public disk. Returns the new relative path,
     * or the original path when optimization was skipped or failed.
     */
    public function reoptimizeStoredPath(string $relativePath, string $preset = 'menu'): ?string
    {
        $disk = config('images.disk', 'public');
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '' || ! Storage::disk($disk)->exists($relativePath)) {
            return null;
        }

        $settings = config("images.presets.{$preset}") ?? config('images.presets.menu');

        try {
            $absolutePath = Storage::disk($disk)->path($relativePath);
            $optimized = $this->optimizeFromPath($absolutePath, $settings);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $extension = $optimized['extension'];
        $directory = trim(dirname($relativePath), '.');
        $newPath = ($directory !== '' ? $directory.'/' : '').Str::uuid().'.'.$extension;

        Storage::disk($disk)->put($newPath, $optimized['binary']);

        if ($newPath !== $relativePath) {
            Storage::disk($disk)->delete($relativePath);
        }

        return $newPath;
    }

    /**
     * Whether a stored file already matches the preset (WebP/JPEG at or below max dimensions).
     */
    public function storedPathMatchesPreset(string $relativePath, string $preset = 'menu'): bool
    {
        $relativePath = ltrim($relativePath, '/');
        $disk = config('images.disk', 'public');

        if ($relativePath === '' || ! Storage::disk($disk)->exists($relativePath)) {
            return false;
        }

        $settings = config("images.presets.{$preset}") ?? config('images.presets.menu');
        $format = strtolower((string) ($settings['format'] ?? 'webp'));
        $expectedExtension = $format === 'jpeg' ? 'jpg' : $format;
        $actualExtension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if ($actualExtension !== $expectedExtension) {
            return false;
        }

        $info = @getimagesize(Storage::disk($disk)->path($relativePath));
        if ($info === false) {
            return false;
        }

        return $info[0] <= (int) $settings['max_width']
            && $info[1] <= (int) $settings['max_height'];
    }

    /**
     * @param  array{max_width: int, max_height: int, quality: int, format: string}  $settings
     * @return array{binary: string, extension: string, mime: string}
     */
    public function optimizeFromPath(string $absolutePath, array $settings): array
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('GD extension is required for image optimization.');
        }

        $source = $this->loadImageFromPath($absolutePath);
        if ($source === null) {
            throw new RuntimeException('Unsupported or unreadable image file.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        [$targetWidth, $targetHeight] = $this->fitWithin(
            $sourceWidth,
            $sourceHeight,
            (int) $settings['max_width'],
            (int) $settings['max_height']
        );

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            imagedestroy($source);
            throw new RuntimeException('Could not allocate image canvas.');
        }

        $this->preserveAlpha($canvas);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagedestroy($source);

        $format = strtolower((string) ($settings['format'] ?? 'webp'));
        $quality = max(1, min(100, (int) ($settings['quality'] ?? 82)));

        if ($format === 'webp' && ! function_exists('imagewebp')) {
            $format = 'jpeg';
        }

        $binary = $this->encode($canvas, $format, $quality);
        imagedestroy($canvas);

        return [
            'binary' => $binary,
            'extension' => $format === 'jpeg' ? 'jpg' : $format,
            'mime' => $format === 'webp' ? 'image/webp' : 'image/jpeg',
        ];
    }

    /**
     * @param  array{max_width: int, max_height: int, quality: int, format: string}  $settings
     * @return array{binary: string, extension: string, mime: string}
     */
    public function optimize(UploadedFile $file, array $settings): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Unsupported or unreadable image upload.');
        }

        return $this->optimizeFromPath($path, $settings);
    }

    private function loadImage(UploadedFile $file): ?\GdImage
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return null;
        }

        return $this->loadImageFromPath($path, strtolower((string) $file->getMimeType()));
    }

    private function loadImageFromPath(string $path, ?string $mime = null): ?\GdImage
    {
        if ($mime === null) {
            $mime = strtolower((string) (@mime_content_type($path) ?: ''));
        }

        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => '',
            };
        }

        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            'image/gif' => @imagecreatefromgif($path) ?: null,
            default => null,
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fitWithin(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        if ($width <= 0 || $height <= 0) {
            return [1, 1];
        }

        if ($width <= $maxWidth && $height <= $maxHeight) {
            return [$width, $height];
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function preserveAlpha(\GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        }
    }

    private function encode(\GdImage $image, string $format, int $quality): string
    {
        ob_start();

        $saved = match ($format) {
            'webp' => imagewebp($image, null, $quality),
            'png' => imagepng($image, null, (int) round((100 - $quality) / 10)),
            default => imagejpeg($image, null, $quality),
        };

        $binary = ob_get_clean();

        if ($saved === false || $binary === false || $binary === '') {
            throw new RuntimeException('Failed to encode optimized image.');
        }

        return $binary;
    }
}
