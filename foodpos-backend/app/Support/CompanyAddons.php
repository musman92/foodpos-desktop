<?php

namespace App\Support;

class CompanyAddons
{
    public const KITCHEN_TRACKING = 'kitchen_tracking';

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            self::KITCHEN_TRACKING => [
                'label' => 'Kitchen & order tracking',
                'description' => 'Order status timeline, expected ready time, and prep time on menu items. Kitchen display screen coming later.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }
}
