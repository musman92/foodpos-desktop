<?php

namespace App\Support;

class PosLayout
{
    public const LAYOUT_CLASSIC = 'classic';

    public const LAYOUT_SIDEBAR = 'sidebar_actions';

    public const DENSITY_COMFORTABLE = 'comfortable';

    public const DENSITY_COMPACT = 'compact';

    public const ORDER_CONTEXT_LABELED = 'labeled';

    public const ORDER_CONTEXT_COMPACT = 'compact';

    public const CATEGORY_SIZE_NORMAL = 'normal';

    public const CATEGORY_SIZE_COMPACT = 'compact';

    public const CATEGORY_LAYOUT_STRIP = 'strip';

    public const CATEGORY_LAYOUT_GRID = 'grid';

    /**
     * @return array<string, array{label: string, description: string, available: bool}>
     */
    public static function layoutPresets(): array
    {
        return [
            self::LAYOUT_CLASSIC => [
                'label' => 'Classic',
                'description' => 'Product grid with checkout bar under the menu (current default).',
                'available' => true,
            ],
            self::LAYOUT_SIDEBAR => [
                'label' => 'Sidebar actions',
                'description' => 'Order, payment, and action buttons in the right sidebar; larger product area.',
                'available' => true,
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function productDensities(): array
    {
        return [
            self::DENSITY_COMFORTABLE => [
                'label' => 'Comfortable',
                'description' => 'Larger product cards (current default).',
            ],
            self::DENSITY_COMPACT => [
                'label' => 'Compact',
                'description' => 'Smaller cards — more products visible per row.',
            ],
        ];
    }

    public static function normalizeLayout(mixed $value): string
    {
        $value = is_string($value) ? $value : self::LAYOUT_CLASSIC;

        if (! array_key_exists($value, self::layoutPresets())) {
            return self::LAYOUT_CLASSIC;
        }

        if (! self::layoutPresets()[$value]['available']) {
            return self::LAYOUT_CLASSIC;
        }

        return $value;
    }

    public static function normalizeProductDensity(mixed $value): string
    {
        $value = is_string($value) ? $value : self::DENSITY_COMFORTABLE;

        return array_key_exists($value, self::productDensities())
            ? $value
            : self::DENSITY_COMFORTABLE;
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function orderContextStyles(): array
    {
        return [
            self::ORDER_CONTEXT_LABELED => [
                'label' => 'Labeled rows',
                'description' => 'Type, customer, table, and waiter as labeled rows (current default).',
            ],
            self::ORDER_CONTEXT_COMPACT => [
                'label' => 'Compact chips',
                'description' => 'Two fields per row with chips; customer on a full row; kitchen bar in this block.',
            ],
        ];
    }

    public static function normalizeOrderContextStyle(mixed $value): string
    {
        $value = is_string($value) ? $value : self::ORDER_CONTEXT_LABELED;

        return array_key_exists($value, self::orderContextStyles())
            ? $value
            : self::ORDER_CONTEXT_LABELED;
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function categorySizes(): array
    {
        return [
            self::CATEGORY_SIZE_NORMAL => [
                'label' => 'Normal',
                'description' => 'Standard category pills (current default).',
            ],
            self::CATEGORY_SIZE_COMPACT => [
                'label' => 'Compact',
                'description' => 'Smaller pills — more categories fit on screen.',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function categoryLayouts(): array
    {
        return [
            self::CATEGORY_LAYOUT_STRIP => [
                'label' => 'Scrollable row',
                'description' => 'One horizontal row with sideways scroll (current default).',
            ],
            self::CATEGORY_LAYOUT_GRID => [
                'label' => 'Wrap all',
                'description' => 'Show every category without scrolling — pills wrap to multiple rows.',
            ],
        ];
    }

    public static function normalizeCategorySize(mixed $value): string
    {
        $value = is_string($value) ? $value : self::CATEGORY_SIZE_NORMAL;

        return array_key_exists($value, self::categorySizes())
            ? $value
            : self::CATEGORY_SIZE_NORMAL;
    }

    public static function normalizeCategoryLayout(mixed $value): string
    {
        $value = is_string($value) ? $value : self::CATEGORY_LAYOUT_STRIP;

        return array_key_exists($value, self::categoryLayouts())
            ? $value
            : self::CATEGORY_LAYOUT_STRIP;
    }

    public static function usesCategoryGrid(string $layout): bool
    {
        return $layout === self::CATEGORY_LAYOUT_GRID;
    }

    /**
     * Tailwind classes for the POS category bar container and pills.
     *
     * @return array{container: string, button: string, icon_margin: string}
     */
    public static function categoryBarClasses(string $layout, string $size): array
    {
        $gap = $size === self::CATEGORY_SIZE_COMPACT ? 'gap-1 sm:gap-1.5' : 'gap-1.5 sm:gap-2';

        $container = self::usesCategoryGrid($layout)
            ? "pos-category-grid flex {$gap} flex-wrap items-center -mx-0.5 px-0.5"
            : "pos-category-strip flex {$gap} flex-nowrap overflow-x-auto pb-0.5 sm:pb-1 items-center -mx-0.5 px-0.5";

        if ($size === self::CATEGORY_SIZE_COMPACT) {
            return [
                'container' => $container,
                'button' => 'flex-shrink-0 whitespace-nowrap px-2 sm:px-2.5 py-1 min-h-[32px] sm:min-h-0 rounded-full text-[11px] sm:text-xs font-medium transition-colors touch-manipulation',
                'icon_margin' => 'mr-1',
            ];
        }

        return [
            'container' => $container,
            'button' => 'flex-shrink-0 whitespace-nowrap px-3 sm:px-4 py-2 min-h-[40px] sm:min-h-0 rounded-full text-xs sm:text-sm font-medium transition-colors touch-manipulation',
            'icon_margin' => 'mr-1.5',
        ];
    }

    public static function usesCompactOrderContext(string $style): bool
    {
        return $style === self::ORDER_CONTEXT_COMPACT;
    }

    public static function usesSidebarCheckout(string $layout): bool
    {
        return $layout === self::LAYOUT_SIDEBAR;
    }

    /** Desktop shell grid (Tailwind classes). */
    public static function mainShellGridClasses(string $layout): string
    {
        if ($layout === self::LAYOUT_SIDEBAR) {
            return 'lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,min(26rem,100%))] lg:grid-rows-[minmax(0,1fr)]';
        }

        return 'lg:grid lg:grid-cols-[minmax(0,1fr)_min(26rem,100%)] lg:grid-rows-[minmax(0,1fr)_auto]';
    }

    /** Cart column placement on desktop. */
    public static function cartColumnClasses(string $layout): string
    {
        if ($layout === self::LAYOUT_SIDEBAR) {
            return 'lg:col-start-2 lg:row-start-1 lg:row-span-1 lg:min-w-0 lg:max-w-full lg:w-full lg:overflow-hidden';
        }

        return 'lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:min-w-0 lg:max-w-full lg:w-full';
    }

    /** Product browse column on desktop. */
    public static function browseColumnClasses(string $layout): string
    {
        if ($layout === self::LAYOUT_SIDEBAR) {
            return 'lg:col-start-1 lg:row-start-1 lg:row-span-1 lg:min-w-0';
        }

        return 'lg:col-start-1 lg:row-start-1 lg:min-w-0';
    }

    /**
     * Tailwind grid classes for menu/deal product cards.
     *
     * @return array{grid: string, card: string, deal_card: string, image: string, title: string, price_badge: string, deal_price_badge: string}
     */
    public static function productGridClasses(string $density): array
    {
        if ($density === self::DENSITY_COMPACT) {
            return [
                'grid' => 'grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 gap-1.5 sm:gap-2',
                'card' => 'menu-item-card group relative bg-white rounded-md shadow-sm border border-gray-200 overflow-hidden cursor-pointer hover:shadow-md hover:border-indigo-200 transition-all active:scale-[0.98]',
                'deal_card' => 'menu-item-card group relative bg-white rounded-md shadow-sm border-2 border-amber-200 overflow-hidden cursor-pointer hover:shadow-md hover:border-amber-300 transition-all active:scale-[0.98]',
                'image' => 'relative aspect-square bg-gray-100 flex items-center justify-center overflow-hidden',
                'title' => 'px-2 py-1.5 text-xs font-semibold text-gray-900 line-clamp-2 leading-tight',
                'price_badge' => 'absolute bottom-1 right-1 z-10 max-w-[88%] rounded px-1.5 py-0.5 text-[11px] font-bold text-white bg-black/65 tabular-nums truncate pointer-events-none',
                'deal_price_badge' => 'absolute bottom-1 right-1 z-10 max-w-[88%] rounded px-1.5 py-0.5 text-[11px] font-bold text-white bg-amber-900/75 tabular-nums truncate pointer-events-none',
            ];
        }

        return [
            'grid' => 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 sm:gap-3 lg:gap-4',
            'deal_card' => 'menu-item-card group relative bg-white rounded-lg shadow-md border-2 border-amber-200 overflow-hidden cursor-pointer hover:shadow-lg hover:border-amber-300 transition-all active:scale-[0.98]',
            'card' => 'menu-item-card group relative bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden cursor-pointer hover:shadow-lg hover:border-indigo-200 transition-all active:scale-[0.98]',
            'image' => 'relative aspect-square bg-gray-100 flex items-center justify-center overflow-hidden',
            'title' => 'px-2.5 py-2 text-sm font-semibold text-gray-900 line-clamp-2 leading-snug',
            'price_badge' => 'absolute bottom-1.5 right-1.5 z-10 max-w-[88%] rounded-md px-2 py-1 text-sm font-bold text-white bg-black/65 tabular-nums truncate pointer-events-none',
            'deal_price_badge' => 'absolute bottom-1.5 right-1.5 z-10 max-w-[88%] rounded-md px-2 py-1 text-sm font-bold text-white bg-amber-900/75 tabular-nums truncate pointer-events-none',
        ];
    }

    /**
     * POS fulfillment buttons (single source for labels, icons, modes).
     *
     * @return list<array{mode: string, label: string, short_label: string, icon: string, enabled_class: string, visible: bool, sidebar_full_width: bool}>
     */
    public static function fulfillmentActions(): array
    {
        return [
            [
                'mode' => 'save',
                'label' => 'Save',
                'short_label' => 'Save',
                'icon' => 'fa-save',
                'enabled_class' => 'bg-emerald-600 text-white hover:bg-emerald-700',
                'visible' => true,
                'sidebar_full_width' => false,
            ],
            [
                'mode' => 'kot',
                'label' => 'KOT',
                'short_label' => 'KOT',
                'icon' => 'fa-utensils',
                'enabled_class' => 'bg-orange-600 text-white hover:bg-orange-700',
                'visible' => true,
                'sidebar_full_width' => false,
            ],
            [
                'mode' => 'kot_bill',
                'label' => 'KOT+Print',
                'short_label' => 'KOT+Print',
                'icon' => 'fa-receipt',
                'enabled_class' => 'bg-orange-500 text-white hover:bg-orange-600',
                'visible' => false,
                'sidebar_full_width' => false,
            ],
            [
                'mode' => 'kot_bill_pay',
                'label' => 'KOT+Print+Pay',
                'short_label' => 'KOT+Pay',
                'icon' => 'fa-bolt',
                'enabled_class' => 'bg-indigo-600 text-white hover:bg-indigo-700',
                'visible' => false,
                'sidebar_full_width' => false,
            ],
            [
                'mode' => 'print',
                'label' => 'Print',
                'short_label' => 'Print',
                'icon' => 'fa-print',
                'enabled_class' => 'bg-white text-gray-800 border border-gray-300 hover:bg-gray-50',
                'visible' => true,
                'sidebar_full_width' => false,
            ],
            [
                'mode' => 'checkout',
                'label' => 'Checkout',
                'short_label' => 'Checkout',
                'icon' => 'fa-cash-register',
                'enabled_class' => 'bg-indigo-800 text-white hover:bg-indigo-900',
                'visible' => true,
                'sidebar_full_width' => true,
            ],
        ];
    }

    /**
     * Fulfillment buttons shown in the POS UI (toggle visibility via `visible` in fulfillmentActions()).
     *
     * @return list<array{mode: string, label: string, short_label: string, icon: string, enabled_class: string, visible: bool, sidebar_full_width: bool}>
     */
    public static function visibleFulfillmentActions(): array
    {
        return array_values(array_filter(
            self::fulfillmentActions(),
            fn (array $action) => $action['visible'] ?? true
        ));
    }
}
