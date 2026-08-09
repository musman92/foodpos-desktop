<?php

namespace App\Helpers;

/**
 * Built-in tenant roles created by {@see \App\Services\TenantRoleBootstrapService}.
 * These names cannot be deleted from the UI.
 */
final class TenantDefaultRoles
{
    public const ADMINISTRATOR = 'Administrator';

    public const MANAGER = 'Manager';

    public const CASHIER = 'Cashier';

    public const ORDER_TAKER = 'Order Taker';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            self::ADMINISTRATOR,
            self::MANAGER,
            self::CASHIER,
            self::ORDER_TAKER,
        ];
    }

    public static function isProtected(?string $name): bool
    {
        return $name !== null && in_array($name, self::names(), true);
    }
}
