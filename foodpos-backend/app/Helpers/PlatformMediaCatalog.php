<?php

namespace App\Helpers;

use App\Models\PlatformMedia;

/**
 * Canonical categories for the platform media library.
 * Edit here, then assign images to a category when uploading.
 */
final class PlatformMediaCatalog
{
    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            'Pizzas',
            'Starters',
            'Sandwiches',
            'Shawarma',
            'Sides',
            'Drinks',
            'Coffee',
            'Tea',
            'Desserts',
            'General',
        ];
    }

    /**
     * Categories that have at least one active library image, in catalog order.
     *
     * @return list<array{name: string, count: int}>
     */
    public static function categoriesWithCounts(): array
    {
        $counts = PlatformMedia::query()
            ->active()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->selectRaw('category, COUNT(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        $result = [];

        foreach (self::categories() as $name) {
            $count = (int) ($counts[$name] ?? 0);
            if ($count > 0) {
                $result[] = ['name' => $name, 'count' => $count];
            }
        }

        foreach ($counts as $name => $count) {
            if (! in_array($name, self::categories(), true) && (int) $count > 0) {
                $result[] = ['name' => $name, 'count' => (int) $count];
            }
        }

        return $result;
    }
}
