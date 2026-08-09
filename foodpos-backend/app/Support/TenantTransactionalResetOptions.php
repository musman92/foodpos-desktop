<?php

namespace App\Support;

final class TenantTransactionalResetOptions
{
    public const ORDERS = 'orders';

    public const PURCHASES = 'purchases';

    public const CUSTOMERS = 'customers';

    public const SUPPLIER_PAYMENTS = 'supplier_payments';

    public const INVENTORY = 'inventory';

    public const SHIFTS = 'shifts';

    public const FINANCIAL_LEDGER = 'financial_ledger';

    public const COUNTERS = 'counters';

    public const SUPPLIER_BALANCES = 'supplier_balances';

    public const RECREATE_WALK_IN = 'recreate_walk_in_customer';

    public const MONEY_SOURCES = 'money_sources';

    /**
     * @return array<string, array{label: string, description: string, primary: bool}>
     */
    public static function definitions(): array
    {
        return [
            self::ORDERS => [
                'label' => 'Orders & sales history',
                'description' => 'Orders, line items, payments, refunds, KOTs, kitchen display, and print jobs.',
                'primary' => true,
            ],
            self::PURCHASES => [
                'label' => 'Purchases',
                'description' => 'Purchase records, purchase lines, and purchase orders.',
                'primary' => true,
            ],
            self::CUSTOMERS => [
                'label' => 'Customers & credit',
                'description' => 'Customers, addresses, customer payments, and loyalty history.',
                'primary' => true,
            ],
            self::SUPPLIER_PAYMENTS => [
                'label' => 'Supplier payments',
                'description' => 'Supplier payment records and allocations to purchases.',
                'primary' => true,
            ],
            self::INVENTORY => [
                'label' => 'Stock levels & movements',
                'description' => 'Ingredient stock, menu-item stock, and stock movement history.',
                'primary' => false,
            ],
            self::SHIFTS => [
                'label' => 'Shifts & cash-ups',
                'description' => 'Shift sessions, shift money sources, and daily cash-ups.',
                'primary' => false,
            ],
            self::FINANCIAL_LEDGER => [
                'label' => 'Money ledger & expenses',
                'description' => 'Transactions, fund transfers between money sources, and expenses.',
                'primary' => false,
            ],
            self::COUNTERS => [
                'label' => 'Order / KOT / payment counters',
                'description' => 'Daily numbering counters for orders, kitchen tickets, and supplier payments.',
                'primary' => false,
            ],
            self::SUPPLIER_BALANCES => [
                'label' => 'Zero supplier balances',
                'description' => 'Reset supplier running balances to zero (keeps supplier records).',
                'primary' => false,
            ],
            self::RECREATE_WALK_IN => [
                'label' => 'Recreate Walk In customer',
                'description' => 'Create the default POS walk-in customer after customer data is cleared.',
                'primary' => false,
            ],
            self::MONEY_SOURCES => [
                'label' => 'Money source opening balances',
                'description' => 'Reset Cash, bank, and app account opening balances to zero (keeps the money source records and branch links).',
                'primary' => false,
            ],
        ];
    }

    /**
     * When a primary option is selected, these related options should also be selected.
     *
     * @return array<string, list<string>>
     */
    public static function dependencies(): array
    {
        return [
            self::ORDERS => [
                self::INVENTORY,
                self::SHIFTS,
                self::FINANCIAL_LEDGER,
                self::COUNTERS,
            ],
            self::PURCHASES => [
                self::INVENTORY,
                self::FINANCIAL_LEDGER,
                self::SUPPLIER_BALANCES,
            ],
            self::CUSTOMERS => [
                self::FINANCIAL_LEDGER,
                self::RECREATE_WALK_IN,
            ],
            self::SUPPLIER_PAYMENTS => [
                self::FINANCIAL_LEDGER,
                self::SUPPLIER_BALANCES,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @param  list<string>  $selected
     * @return list<string>
     */
    public static function expandSelected(array $selected): array
    {
        $expanded = array_values(array_unique($selected));
        $definitions = self::definitions();
        $dependencies = self::dependencies();

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($expanded as $key) {
                foreach ($dependencies[$key] ?? [] as $dependency) {
                    if (! in_array($dependency, $expanded, true) && isset($definitions[$dependency])) {
                        $expanded[] = $dependency;
                        $changed = true;
                    }
                }
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @param  list<string>  $selected
     * @return list<string>
     */
    public static function normalizeSelection(array $selected): array
    {
        $allowed = array_flip(self::allKeys());
        $filtered = array_values(array_unique(array_filter(
            $selected,
            static fn (string $key): bool => isset($allowed[$key])
        )));

        return self::expandSelected($filtered);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function requiredBy(): array
    {
        $requiredBy = [];

        foreach (self::dependencies() as $parent => $dependents) {
            foreach ($dependents as $dependent) {
                $requiredBy[$dependent][] = $parent;
            }
        }

        return $requiredBy;
    }
}
