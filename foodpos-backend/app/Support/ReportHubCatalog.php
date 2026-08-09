<?php

namespace App\Support;

class ReportHubCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     group: 'sales'|'inventory'|'financial'|'closings'|'outstanding',
     *     icon: string,
     *     permission: string|null,
     *     filters: list<string>
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'sales',
                'label' => 'Sales',
                'group' => 'sales',
                'icon' => 'chart-line',
                'permission' => 'reports.sales',
                'filters' => ['branch', 'dates', 'category'],
            ],
            [
                'key' => 'sales-by-item',
                'label' => 'Sales by Item',
                'group' => 'sales',
                'icon' => 'utensils',
                'permission' => 'reports.sales-by-item',
                'filters' => ['branch', 'dates', 'category', 'menu_item'],
            ],
            [
                'key' => 'top-selling',
                'label' => 'Top Selling Items',
                'group' => 'sales',
                'icon' => 'trophy',
                'permission' => null,
                'filters' => ['branch', 'dates', 'limit'],
            ],
            [
                'key' => 'daily',
                'label' => 'Daily Sales',
                'group' => 'sales',
                'icon' => 'calendar-day',
                'permission' => null,
                'filters' => ['branch', 'dates'],
            ],
            [
                'key' => 'z-report',
                'label' => 'Z Report',
                'group' => 'sales',
                'icon' => 'file-invoice',
                'permission' => null,
                'filters' => ['branch', 'dates'],
            ],
            [
                'key' => 'payment-methods',
                'label' => 'Payment Methods',
                'group' => 'sales',
                'icon' => 'money-bill-wave',
                'permission' => null,
                'filters' => ['branch', 'dates'],
            ],
            [
                'key' => 'transactions-by-money-source',
                'label' => 'Transactions by Money Source',
                'group' => 'sales',
                'icon' => 'exchange-alt',
                'permission' => 'reports.transactions-by-money-source',
                'filters' => ['branch', 'dates', 'money_sources'],
            ],
            [
                'key' => 'foc',
                'label' => 'FOC',
                'group' => 'sales',
                'icon' => 'gift',
                'permission' => 'reports.foc',
                'filters' => ['branch', 'dates'],
            ],
            [
                'key' => 'order-history',
                'label' => 'Order History',
                'group' => 'sales',
                'icon' => 'receipt',
                'permission' => null,
                'filters' => ['branch', 'dates', 'customer', 'waiter', 'rider', 'order_type', 'bill_number'],
            ],
            [
                'key' => 'consumption',
                'label' => 'Consumption',
                'group' => 'inventory',
                'icon' => 'box-open',
                'permission' => 'reports.consumption',
                'filters' => ['branch', 'dates', 'search', 'category', 'menu_item'],
            ],
            [
                'key' => 'ingredient-ledger',
                'label' => 'Ingredient Ledger',
                'group' => 'inventory',
                'icon' => 'clipboard-list',
                'permission' => 'reports.consumption',
                'filters' => ['branch', 'dates', 'ingredient'],
            ],
            [
                'key' => 'gross-margin',
                'label' => 'Gross Margin',
                'group' => 'financial',
                'icon' => 'percentage',
                'permission' => null,
                'filters' => ['search', 'category', 'sort'],
            ],
            [
                'key' => 'profit-loss',
                'label' => 'Profit & Loss',
                'group' => 'financial',
                'icon' => 'file-invoice-dollar',
                'permission' => null,
                'filters' => ['branch', 'dates'],
            ],
            [
                'key' => 'account-statement',
                'label' => 'Account Statement',
                'group' => 'financial',
                'icon' => 'file-invoice',
                'permission' => 'account-statements.index',
                'filters' => ['branch', 'dates', 'statement_type', 'party'],
            ],
            [
                'key' => 'weekly-closing',
                'label' => 'Weekly Closing',
                'group' => 'closings',
                'icon' => 'calendar-week',
                'permission' => null,
                'filters' => ['branch', 'week'],
            ],
            [
                'key' => 'monthly-closing',
                'label' => 'Monthly Closing',
                'group' => 'closings',
                'icon' => 'calendar-alt',
                'permission' => null,
                'filters' => ['branch', 'month'],
            ],
            [
                'key' => 'accounts-receivable',
                'label' => 'Accounts Receivable',
                'group' => 'outstanding',
                'icon' => 'hand-holding-usd',
                'permission' => null,
                'filters' => ['branch'],
            ],
            [
                'key' => 'accounts-payable',
                'label' => 'Accounts Payable',
                'group' => 'outstanding',
                'icon' => 'truck-loading',
                'permission' => null,
                'filters' => ['branch'],
            ],
            [
                'key' => 'customer-credits',
                'label' => 'Customer Credits',
                'group' => 'outstanding',
                'icon' => 'piggy-bank',
                'permission' => null,
                'filters' => ['branch'],
            ],
            [
                'key' => 'supplier-prepayments',
                'label' => 'Supplier Prepayments',
                'group' => 'outstanding',
                'icon' => 'wallet',
                'permission' => null,
                'filters' => ['branch'],
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     group: string,
     *     icon: string,
     *     permission: string|null,
     *     filters: list<string>
     * }|null
     */
    public static function definition(string $key): ?array
    {
        foreach (self::all() as $report) {
            if ($report['key'] === $key) {
                return $report;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    /**
     * Reports visible to the user. When permission is null, the report is open to
     * anyone who can reach the reports hub (reports.index or any reports access).
     *
     * @param  \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable  $user
     * @return list<array{
     *     key: string,
     *     label: string,
     *     group: string,
     *     icon: string,
     *     permission: string|null,
     *     filters: list<string>
     * }>
     */
    public static function forUser($user): array
    {
        $canAccessReportsHub = self::userCanAccessReportsHub($user);

        return array_values(array_filter(
            self::all(),
            static function (array $report) use ($user, $canAccessReportsHub): bool {
                if ($report['permission'] === null) {
                    return $canAccessReportsHub;
                }

                if ($report['key'] === 'sales') {
                    return $user->hasAppPermission('reports.sales')
                        || $user->hasAppPermission('reports.sales-by-category');
                }

                return $user->hasAppPermission($report['permission']);
            }
        ));
    }

    private static function userCanAccessReportsHub($user): bool
    {
        if ($user->hasAppPermission('reports.index')) {
            return true;
        }

        foreach (self::all() as $report) {
            if ($report['permission'] !== null && $user->hasAppPermission($report['permission'])) {
                return true;
            }
        }

        return $user->hasAppPermission('reports.sales-by-category');
    }
}
