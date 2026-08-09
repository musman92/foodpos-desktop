<?php

namespace App\Helpers;

use Illuminate\Support\Str;

/**
 * Canonical permission names: {module}.{action} (e.g. branches.index, branches.store).
 * Use this for DB sync, role checks, and frontend grouping.
 *
 * Platform-only modules (companies, global catalog, platform media) are excluded from
 * tenant roles — super admins bypass permission checks via User::hasAppPermission().
 */
final class AppPermissions
{
    /**
     * Tenant company modules: index, store, update, destroy.
     *
     * @var list<string>
     */
    public const TENANT_RESOURCE_MODULES = [
        'users',
        'roles',
        'branches',
        'floors',
        'tables',
        'customers',
        'suppliers',
        'categories',
        'cuisines',
        'taxes',
        'ingredients',
        'ingredient-categories',
        'ingredient-units',
        'product-addons',
        'variants',
        'recipes',
        'menu-items',
        'deals',
        'accounts',
        'money-sources',
        'shifts',
        'transactions',
        'purchases',
        'purchase-returns',
        'supplier-payments',
        'customer-payments',
        'employees',
        'attendance',
        'leaves',
        'payroll',
        'employee-payments',
        'orders',
    ];

    /**
     * Platform (super admin) modules — never assigned to tenant default roles.
     *
     * @var list<string>
     */
    public const PLATFORM_RESOURCE_MODULES = [
        'companies',
        'platform-media',
    ];

    public const RESOURCE_ACTIONS = ['index', 'store', 'update', 'destroy'];

    /**
     * @var array<string, list<string>>
     */
    private const CUSTOM_MODULE_ACTIONS = [
        'pos' => ['index', 'store', 'deals', 'invoice', 'foc'],
        'company-settings' => ['index', 'update'],
        'dashboard' => [
            'index',
            'today-stats',
            'period-stats',
            'revenue-chart',
            'expenses-chart',
            'operational-comparison',
            'order-types',
            'funds-overview',
            'receivables',
            'payables',
            'top-items',
            'low-stock',
        ],
        'reports' => ['index', 'sales', 'sales-by-category', 'sales-by-item', 'top-selling', 'daily', 'z-report', 'payment-methods', 'transactions-by-money-source', 'gross-margin', 'profit-loss', 'order-history', 'weekly-closing', 'monthly-closing', 'accounts-receivable', 'accounts-payable', 'customer-credits', 'supplier-prepayments', 'consumption', 'foc'],
        'inventory' => ['index', 'adjust'],
        'order-management' => ['index', 'show', 'refund', 'append-note', 'destroy'],
        'account-statements' => ['index'],
        'customers' => ['adjust-balance'],
        'suppliers' => ['adjust-balance'],
        'money-sources' => ['transfer', 'owner-withdrawal', 'reports'],
        'activity-logs' => ['index'],
    ];

    /**
     * Every permission name that should exist in the `permissions` table.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (array_merge(self::TENANT_RESOURCE_MODULES, self::PLATFORM_RESOURCE_MODULES) as $module) {
            foreach (self::RESOURCE_ACTIONS as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        foreach (self::CUSTOM_MODULE_ACTIONS as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * All dashboard widget permissions (excludes dashboard.index).
     *
     * @return list<string>
     */
    public static function dashboardWidgets(): array
    {
        return array_values(array_filter(
            self::forModule('dashboard'),
            static fn (string $p) => $p !== 'dashboard.index'
        ));
    }

    /**
     * Permissions for tenant companies (Administrator / Manager / custom tenant roles).
     *
     * @return list<string>
     */
    public static function tenantScoped(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $permission) => ! self::isPlatformPermission($permission)
        ));
    }

    /**
     * Platform-only permissions (super admin tooling).
     *
     * @return list<string>
     */
    public static function platformScoped(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $permission) => self::isPlatformPermission($permission)
        ));
    }

    public static function isPlatformPermission(string $permission): bool
    {
        $module = self::parse($permission)['module'] ?? '';

        return in_array($module, self::PLATFORM_RESOURCE_MODULES, true);
    }

    /**
     * All permissions for one module prefix.
     *
     * @return list<string>
     */
    public static function forModule(string $module): array
    {
        $prefix = $module.'.';

        return array_values(array_filter(
            self::all(),
            static fn (string $p) => str_starts_with($p, $prefix)
        ));
    }

    /**
     * @return array{module: string, action: string}|null
     */
    public static function parse(?string $permission): ?array
    {
        if ($permission === null || $permission === '') {
            return null;
        }

        $lastDot = strrpos($permission, '.');
        if ($lastDot === false) {
            return ['module' => $permission, 'action' => ''];
        }

        return [
            'module' => substr($permission, 0, $lastDot),
            'action' => substr($permission, $lastDot + 1),
        ];
    }

    /**
     * Grouped for Blade/API: module title => rows with name, action, label, module_key.
     *
     * @param  list<string>|null  $permissionNames  Defaults to all catalog permissions.
     * @return array<string, list<array{name: string, action: string, label: string, module_key: string}>>
     */
    public static function groupedForFrontend(?array $permissionNames = null): array
    {
        $names = $permissionNames ?? self::all();
        $grouped = [];

        foreach ($names as $name) {
            $parsed = self::parse($name);
            if (! $parsed) {
                continue;
            }
            $moduleKey = $parsed['module'];
            $action = $parsed['action'];
            $title = self::moduleTitle($moduleKey);
            $grouped[$title] ??= [];
            $grouped[$title][] = [
                'name' => $name,
                'action' => $action,
                'label' => self::permissionLabel($moduleKey, $action),
                'module_key' => $moduleKey,
            ];
        }

        ksort($grouped);

        foreach ($grouped as $title => $rows) {
            usort($rows, static fn ($a, $b) => strcmp($a['name'], $b['name']));
            $grouped[$title] = $rows;
        }

        return $grouped;
    }

    public static function moduleTitle(string $moduleKey): string
    {
        return match ($moduleKey) {
            'menu-items' => 'Menu items',
            'ingredient-categories' => 'Ingredient categories',
            'ingredient-units' => 'Ingredient units',
            'product-addons' => 'Product addons',
            'recipes' => 'Recipes',
            'money-sources' => 'Money sources',
            'supplier-payments' => 'Supplier payments',
            'customer-payments' => 'Customer payments',
            'company-settings' => 'Company settings',
            'activity-logs' => 'Activity logs',
            'order-management' => 'Order management',
            'account-statements' => 'Account statements',
            'dashboard' => 'Dashboard',
            'inventory' => 'Inventory',
            'platform-media' => 'Platform media',
            default => Str::title(str_replace('-', ' ', $moduleKey)),
        };
    }

    public static function permissionLabel(string $moduleKey, string $action): string
    {
        $module = self::moduleTitle($moduleKey);
        $actionLabel = match ($action) {
            'index' => 'View / list',
            'store' => 'Create',
            'update' => 'Update',
            'destroy' => 'Delete',
            'show' => 'View detail',
            'refund' => 'Refund',
            'append-note' => 'Add notes',
            'adjust' => 'Adjust stock',
            'top-selling' => 'Top selling',
            'payment-methods' => 'Payment methods',
            'gross-margin' => 'Gross margin',
            'profit-loss' => 'Profit & loss',
            'order-history' => 'Order history',
            'weekly-closing' => 'Weekly closing',
            'monthly-closing' => 'Monthly closing',
            'accounts-receivable' => 'Accounts receivable',
            'accounts-payable' => 'Accounts payable',
            'consumption' => 'Consumption',
            'payment-methods' => 'Payment methods',
            'transactions-by-money-source' => 'Transactions by money source',
            'foc' => $moduleKey === 'reports' ? 'FOC' : 'FOC (complimentary)',
            'today-stats' => 'Today KPIs',
            'period-stats' => 'Period KPIs',
            'revenue-chart' => 'Revenue chart',
            'expenses-chart' => 'Expenses chart',
            'operational-comparison' => 'Operational comparison',
            'order-types' => 'Orders by type',
            'funds-overview' => 'Funds overview',
            'receivables' => 'Customer receivables',
            'payables' => 'Supplier payables',
            'top-items' => 'Top food items',
            'low-stock' => 'Low stock alerts',
            default => Str::title(str_replace('-', ' ', $action)),
        };

        return "{$module} — {$actionLabel}";
    }
}
