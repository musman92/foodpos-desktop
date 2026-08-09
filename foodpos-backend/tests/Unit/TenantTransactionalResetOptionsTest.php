<?php

namespace Tests\Unit;

use App\Support\TenantTransactionalResetOptions;
use PHPUnit\Framework\TestCase;

class TenantTransactionalResetOptionsTest extends TestCase
{
    public function test_expand_selected_includes_inventory_when_orders_selected(): void
    {
        $expanded = TenantTransactionalResetOptions::normalizeSelection([
            TenantTransactionalResetOptions::ORDERS,
        ]);

        $this->assertContains(TenantTransactionalResetOptions::INVENTORY, $expanded);
        $this->assertContains(TenantTransactionalResetOptions::SHIFTS, $expanded);
        $this->assertContains(TenantTransactionalResetOptions::FINANCIAL_LEDGER, $expanded);
        $this->assertContains(TenantTransactionalResetOptions::COUNTERS, $expanded);
    }

    public function test_expand_selected_includes_supplier_balances_when_purchases_selected(): void
    {
        $expanded = TenantTransactionalResetOptions::normalizeSelection([
            TenantTransactionalResetOptions::PURCHASES,
        ]);

        $this->assertContains(TenantTransactionalResetOptions::SUPPLIER_BALANCES, $expanded);
        $this->assertContains(TenantTransactionalResetOptions::INVENTORY, $expanded);
    }

    public function test_expand_selected_includes_walk_in_when_customers_selected(): void
    {
        $expanded = TenantTransactionalResetOptions::normalizeSelection([
            TenantTransactionalResetOptions::CUSTOMERS,
        ]);

        $this->assertContains(TenantTransactionalResetOptions::RECREATE_WALK_IN, $expanded);
    }

    public function test_expand_selected_does_not_auto_include_money_sources_when_orders_selected(): void
    {
        $expanded = TenantTransactionalResetOptions::normalizeSelection([
            TenantTransactionalResetOptions::ORDERS,
        ]);

        $this->assertNotContains(TenantTransactionalResetOptions::MONEY_SOURCES, $expanded);
    }
}
