<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Cuisine;
use App\Models\Deal;
use App\Models\MenuItem;
use App\Models\PlatformMedia;
use App\Services\ImageOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReoptimizeImagesCommand extends Command
{
    protected $signature = 'images:reoptimize
                            {--dry-run : List actions without writing files or updating the database}
                            {--force : Re-optimize files that already match preset dimensions and format}
                            {--only= : Comma-separated folders: platform-media,menu-items,categories,cuisines,deals}';

    protected $description = 'Resize and compress existing stored images (menu items, platform media, catalog)';

    /** @var array<string, string> */
    private array $folderPresets = [
        'platform-media' => 'platform_media',
        'menu-items' => 'menu',
        'categories' => 'catalog',
        'cuisines' => 'catalog',
        'deals' => 'catalog',
    ];

    public function handle(ImageOptimizationService $optimizer): int
    {
        if (! extension_loaded('gd')) {
            $this->error('The PHP GD extension is required.');

            return self::FAILURE;
        }

        $folders = $this->resolveFolders();
        if ($folders === null) {
            return self::FAILURE;
        }
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('Dry run — no files or database records will be changed.');
        }

        $stats = [
            'optimized' => 0,
            'skipped' => 0,
            'failed' => 0,
            'bytes_before' => 0,
            'bytes_after' => 0,
        ];

        if (in_array('platform-media', $folders, true)) {
            $this->processPlatformMedia($optimizer, $dryRun, $force, $stats);
        }

        if (in_array('menu-items', $folders, true)) {
            $this->processMenuItems($optimizer, $dryRun, $force, $stats);
        }

        if (in_array('categories', $folders, true)) {
            $this->processModelImages(Category::class, 'image', 'categories', $optimizer, $dryRun, $force, $stats);
        }

        if (in_array('cuisines', $folders, true)) {
            $this->processModelImages(Cuisine::class, 'image', 'cuisines', $optimizer, $dryRun, $force, $stats);
        }

        if (in_array('deals', $folders, true)) {
            $this->processModelImages(Deal::class, 'image', 'deals', $optimizer, $dryRun, $force, $stats);
        }

        $saved = $stats['bytes_before'] - $stats['bytes_after'];
        $savedLabel = $saved >= 0
            ? number_format($saved).' bytes saved'
            : number_format(abs($saved)).' bytes added (re-encoded)';

        $this->newLine();
        $this->info("Done. Optimized: {$stats['optimized']}, skipped: {$stats['skipped']}, failed: {$stats['failed']}. {$savedLabel}.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    private function resolveFolders(): ?array
    {
        $only = trim((string) $this->option('only'));
        if ($only === '') {
            return array_keys($this->folderPresets);
        }

        $requested = array_values(array_filter(array_map('trim', explode(',', $only))));
        $invalid = array_diff($requested, array_keys($this->folderPresets));
        if ($invalid !== []) {
            $this->error('Invalid --only value(s): '.implode(', ', $invalid));
            $this->line('Allowed: '.implode(', ', array_keys($this->folderPresets)));

            return null;
        }

        return $requested;
    }

    /**
     * @param  array{optimized: int, skipped: int, failed: int, bytes_before: int, bytes_after: int}  $stats
     */
    private function processPlatformMedia(
        ImageOptimizationService $optimizer,
        bool $dryRun,
        bool $force,
        array &$stats
    ): void {
        $this->info('Platform media library…');

        $paths = PlatformMedia::query()
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->unique()
            ->values();

        foreach ($paths as $path) {
            $this->reoptimizePath(
                $path,
                'platform_media',
                $optimizer,
                $dryRun,
                $force,
                $stats,
                function (string $oldPath, string $newPath) use ($dryRun) {
                    if ($dryRun) {
                        return;
                    }

                    PlatformMedia::query()
                        ->where('file_path', $oldPath)
                        ->update(['file_path' => $newPath]);

                    MenuItem::withoutGlobalScopes()
                        ->where('image', $oldPath)
                        ->update(['image' => $newPath]);
                }
            );
        }
    }

    /**
     * @param  array{optimized: int, skipped: int, failed: int, bytes_before: int, bytes_after: int}  $stats
     */
    private function processMenuItems(
        ImageOptimizationService $optimizer,
        bool $dryRun,
        bool $force,
        array &$stats
    ): void {
        $this->info('Menu item uploads…');

        $paths = MenuItem::withoutGlobalScopes()
            ->whereNotNull('image')
            ->where('image', 'like', 'menu-items/%')
            ->pluck('image')
            ->unique()
            ->values();

        foreach ($paths as $path) {
            $this->reoptimizePath(
                $path,
                'menu',
                $optimizer,
                $dryRun,
                $force,
                $stats,
                function (string $oldPath, string $newPath) use ($dryRun) {
                    if ($dryRun) {
                        return;
                    }

                    MenuItem::withoutGlobalScopes()
                        ->where('image', $oldPath)
                        ->update(['image' => $newPath]);
                }
            );
        }
    }

    /**
     * @param  class-string  $modelClass
     * @param  array{optimized: int, skipped: int, failed: int, bytes_before: int, bytes_after: int}  $stats
     */
    private function processModelImages(
        string $modelClass,
        string $column,
        string $label,
        ImageOptimizationService $optimizer,
        bool $dryRun,
        bool $force,
        array &$stats
    ): void {
        $this->info(ucfirst($label).'…');

        $preset = $this->folderPresets[$label];
        $paths = $modelClass::query()
            ->whereNotNull($column)
            ->where($column, 'like', $label.'/%')
            ->pluck($column)
            ->unique()
            ->values();

        foreach ($paths as $path) {
            $this->reoptimizePath(
                $path,
                $preset,
                $optimizer,
                $dryRun,
                $force,
                $stats,
                function (string $oldPath, string $newPath) use ($modelClass, $column, $dryRun) {
                    if ($dryRun) {
                        return;
                    }

                    $modelClass::query()
                        ->where($column, $oldPath)
                        ->update([$column => $newPath]);
                }
            );
        }
    }

    /**
     * @param  callable(string, string): void  $onReplaced
     * @param  array{optimized: int, skipped: int, failed: int, bytes_before: int, bytes_after: int}  $stats
     */
    private function reoptimizePath(
        string $path,
        string $preset,
        ImageOptimizationService $optimizer,
        bool $dryRun,
        bool $force,
        array &$stats,
        callable $onReplaced
    ): void {
        $path = ltrim($path, '/');
        $disk = Storage::disk(config('images.disk', 'public'));

        if ($path === '' || ! $disk->exists($path)) {
            $this->line("  <comment>skip</comment> {$path} (missing file)");
            $stats['skipped']++;

            return;
        }

        if (! $force && $optimizer->storedPathMatchesPreset($path, $preset)) {
            $this->line("  <comment>skip</comment> {$path} (already optimized)");
            $stats['skipped']++;

            return;
        }

        $bytesBefore = $disk->size($path);

        if ($dryRun) {
            $this->line("  <info>would optimize</info> {$path} ({$preset})");
            $stats['optimized']++;
            $stats['bytes_before'] += $bytesBefore;

            return;
        }

        $newPath = $optimizer->reoptimizeStoredPath($path, $preset);

        if ($newPath === null) {
            $this->line("  <error>fail</error> {$path}");
            $stats['failed']++;

            return;
        }

        $bytesAfter = $disk->size($newPath);
        $stats['bytes_before'] += $bytesBefore;
        $stats['bytes_after'] += $bytesAfter;
        $stats['optimized']++;

        if ($newPath !== $path) {
            $onReplaced($path, $newPath);
            $this->line("  <info>ok</info> {$path} → {$newPath} (".number_format($bytesBefore).' → '.number_format($bytesAfter).' bytes)');
        } else {
            $this->line("  <info>ok</info> {$path} (".number_format($bytesBefore).' → '.number_format($bytesAfter).' bytes)');
        }
    }
}
