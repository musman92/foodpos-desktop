<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyReceiptLogoService
{
    /**
     * Build a high-contrast black-on-white PNG for thermal receipt printing.
     */
    public function generateFromStoredPath(string $storedPath): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        if (Str::endsWith(strtolower($storedPath), '.svg')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($storedPath)) {
            return null;
        }

        $contents = $disk->get($storedPath);
        if ($contents === null || $contents === '') {
            return null;
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return null;
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($source);

            return null;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        imagefill($canvas, 0, 0, $white);

        imagealphablending($source, true);
        imagesavealpha($source, true);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha >= 120) {
                    continue;
                }

                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;
                $gray = (int) round(($red * 0.299) + ($green * 0.587) + ($blue * 0.058));
                $gray = max(0, min(255, $gray));

                if ($gray < 175) {
                    imagesetpixel($canvas, $x, $y, $black);
                }
            }
        }

        imagedestroy($source);

        $printPath = $this->printPathForSource($storedPath);
        $fullPrintPath = $disk->path($printPath);
        $dir = dirname($fullPrintPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved = imagepng($canvas, $fullPrintPath, 6);
        imagedestroy($canvas);

        return $saved ? $printPath : null;
    }

    public function generateFromUpload(UploadedFile $file, string $storedPath): ?string
    {
        return $this->generateFromStoredPath($storedPath);
    }

    public function deletePrintLogo(?string $printPath): void
    {
        if (! $printPath) {
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($printPath)) {
            $disk->delete($printPath);
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function regenerateForCompany(\App\Models\Company $company): array
    {
        if (! $company->logo) {
            return [
                'success' => false,
                'message' => 'This company has no logo uploaded.',
            ];
        }

        if (Str::endsWith(strtolower($company->logo), '.svg')) {
            return [
                'success' => false,
                'message' => 'SVG logos cannot be converted automatically. Ask the tenant to upload PNG or JPG.',
            ];
        }

        if (! extension_loaded('gd')) {
            return [
                'success' => false,
                'message' => 'Server image processing (GD) is not available.',
            ];
        }

        $this->deletePrintLogo($company->logo_print);

        $printPath = $this->generateFromStoredPath($company->logo);
        if (! $printPath) {
            return [
                'success' => false,
                'message' => 'Could not generate a print logo from the current file.',
            ];
        }

        $company->update(['logo_print' => $printPath]);

        return [
            'success' => true,
            'message' => 'Receipt print logo generated from the current logo.',
        ];
    }

    private function printPathForSource(string $storedPath): string
    {
        $directory = trim(dirname($storedPath), '.\\/');
        $filename = pathinfo($storedPath, PATHINFO_FILENAME);

        return ($directory !== '' ? $directory.'/' : '').$filename.'-print.png';
    }
}
