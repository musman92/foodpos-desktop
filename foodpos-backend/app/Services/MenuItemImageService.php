<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\PlatformMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MenuItemImageService
{
    public function __construct(private ImageOptimizationService $imageOptimizer) {}

    public function resolveFromRequest(Request $request, ?string $existingPath = null): ?string
    {
        if ($request->hasFile('image')) {
            if ($existingPath && ! $this->isSharedPath($existingPath)) {
                Storage::disk('public')->delete($existingPath);
            }

            return $this->imageOptimizer->storeFromUpload(
                $request->file('image'),
                'menu-items',
                'menu'
            );
        }

        if ($request->filled('platform_media_path')) {
            $path = $request->string('platform_media_path')->toString();

            if (! PlatformMedia::active()->where('file_path', $path)->exists()) {
                throw ValidationException::withMessages([
                    'platform_media_path' => 'Selected library image is invalid or no longer available.',
                ]);
            }

            if ($existingPath && ! $this->isSharedPath($existingPath) && $existingPath !== $path) {
                Storage::disk('public')->delete($existingPath);
            }

            return $path;
        }

        if ($request->boolean('clear_image')) {
            if ($existingPath && ! $this->isSharedPath($existingPath)) {
                Storage::disk('public')->delete($existingPath);
            }

            return null;
        }

        return $existingPath;
    }

    public function isSharedPath(?string $path): bool
    {
        return MenuItem::imagePathIsPublicDemoAsset($path)
            || PlatformMedia::isPlatformMediaPath($path);
    }

    public function duplicatePath(?string $imagePath): ?string
    {
        if ($imagePath === null || $imagePath === '') {
            return null;
        }

        if ($this->isSharedPath($imagePath)) {
            return $imagePath;
        }

        if (! Storage::disk('public')->exists($imagePath)) {
            return $imagePath;
        }

        $extension = pathinfo($imagePath, PATHINFO_EXTENSION);
        $newPath = 'menu-items/'.\Illuminate\Support\Str::uuid().($extension !== '' ? '.'.$extension : '');

        Storage::disk('public')->copy($imagePath, $newPath);

        return $newPath;
    }

    public function deleteIfOwned(?string $path): void
    {
        if ($path && ! $this->isSharedPath($path) && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
