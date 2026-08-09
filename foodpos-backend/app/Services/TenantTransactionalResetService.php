<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Shift;
use App\Models\SupplierPayment;
use App\Support\TenantTransactionalResetOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TenantTransactionalResetService
{
    public function __construct(
        protected PurchaseService $purchaseService,
    ) {}
    /**
     * @param  list<string>  $options
     * @return array<string, int|string>
     */
    public function reset(Company $company, array $options): array
    {
        $options = TenantTransactionalResetOptions::normalizeSelection($options);

        if ($options === []) {
            throw new \InvalidArgumentException('Select at least one reset option.');
        }

        $companyId = (int) $company->id;
        $branchIds = DB::table('branches')
            ->where('company_id', $companyId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $summary = [
            'orders' => 0,
            'purchases' => 0,
            'customers' => 0,
            'supplier_payments' => 0,
            'stock_movements' => 0,
            'shifts' => 0,
            'transactions' => 0,
            'expenses' => 0,
            'suppliers_zeroed' => 0,
            'money_sources_reset' => 0,
        ];

        DB::transaction(function () use ($company, $companyId, $branchIds, $options, &$summary) {
            if ($this->selected($options, TenantTransactionalResetOptions::SUPPLIER_PAYMENTS)) {
                $summary['supplier_payments'] = $this->resetSupplierPayments($companyId);
            }

            if ($this->selected($options, TenantTransactionalResetOptions::PURCHASES)) {
                $summary['purchases'] = $this->resetPurchases($companyId);
            }

            if ($this->selected($options, TenantTransactionalResetOptions::ORDERS)) {
                $summary['orders'] = $this->resetOrders($companyId);
            }

            if ($this->selected($options, TenantTransactionalResetOptions::CUSTOMERS)) {
                $summary['customers'] = $this->resetCustomers($companyId);
            }

            if ($this->selected($options, TenantTransactionalResetOptions::FINANCIAL_LEDGER)) {
                $ledger = $this->resetFinancialLedger($companyId);
                $summary['transactions'] = $ledger['transactions'];
                $summary['expenses'] = $ledger['expenses'];
            }

            if ($this->selected($options, TenantTransactionalResetOptions::SHIFTS)) {
                $summary['shifts'] = $this->resetShifts($companyId);
            }

            if ($this->selected($options, TenantTransactionalResetOptions::INVENTORY) && $branchIds !== []) {
                $summary['stock_movements'] = $this->resetInventory($branchIds);

                if (! $this->selected($options, TenantTransactionalResetOptions::PURCHASES)) {
                    $this->purchaseService->rebuildBranchInventoryFromPurchases($companyId, $branchIds);
                }
            }

            if ($this->selected($options, TenantTransactionalResetOptions::COUNTERS) && $branchIds !== []) {
                $this->resetCounters($branchIds);
            }

            if ($this->selected($options, TenantTransactionalResetOptions::SUPPLIER_BALANCES)) {
                $summary['suppliers_zeroed'] = $this->zeroSupplierBalances($companyId);
            }

            if ($this->selected($options, TenantTransactionalResetOptions::RECREATE_WALK_IN)) {
                $this->ensureWalkInCustomer($company);
            }

            if ($this->selected($options, TenantTransactionalResetOptions::MONEY_SOURCES)) {
                $summary['money_sources_reset'] = $this->resetMoneySourceOpeningBalances($companyId);
            }
        });

        Log::info('Tenant transactional reset completed', [
            'company_id' => $companyId,
            'company_name' => $company->name,
            'options' => $options,
            'summary' => $summary,
        ]);

        return $summary;
    }

    /**
     * @param  list<string>  $options
     */
    private function selected(array $options, string $key): bool
    {
        return in_array($key, $options, true);
    }

    private function resetOrders(int $companyId): int
    {
        $orderIds = Order::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        if ($orderIds !== []) {
            $refundIds = DB::table('order_refunds')->whereIn('order_id', $orderIds)->pluck('id')->all();
            if ($refundIds !== []) {
                DB::table('order_refund_lines')->whereIn('order_refund_id', $refundIds)->delete();
            }

            DB::table('order_refunds')->whereIn('order_id', $orderIds)->delete();
            DB::table('order_status_logs')->whereIn('order_id', $orderIds)->delete();
            DB::table('kitchen_display_orders')->whereIn('order_id', $orderIds)->delete();

            if (Schema::hasTable('kitchen_kots')) {
                DB::table('kitchen_kots')->whereIn('order_id', $orderIds)->delete();
            }
        }

        if (Schema::hasTable('print_jobs')) {
            DB::table('print_jobs')->where('company_id', $companyId)->delete();
        }

        $count = Order::withoutGlobalScopes()->where('company_id', $companyId)->count();
        Order::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();

        return $count;
    }

    private function resetPurchases(int $companyId): int
    {
        $purchaseIds = DB::table('purchases')->where('company_id', $companyId)->pluck('id')->all();
        if ($purchaseIds !== []) {
            DB::table('purchase_items')->whereIn('purchase_id', $purchaseIds)->delete();
        }

        $count = Purchase::withoutGlobalScopes()->where('company_id', $companyId)->count();
        Purchase::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();

        if (Schema::hasTable('purchase_orders')) {
            $purchaseOrderIds = DB::table('purchase_orders')->where('company_id', $companyId)->pluck('id')->all();
            if ($purchaseOrderIds !== []) {
                DB::table('purchase_order_items')->whereIn('purchase_order_id', $purchaseOrderIds)->delete();
            }
            DB::table('purchase_orders')->where('company_id', $companyId)->delete();
        }

        return $count;
    }

    private function resetCustomers(int $companyId): int
    {
        if (Schema::hasTable('customer_payments')) {
            DB::table('customer_payments')->where('company_id', $companyId)->delete();
        }

        if (Schema::hasTable('customer_loyalty')) {
            DB::table('customer_loyalty')->where('company_id', $companyId)->delete();
        }

        $customerIds = DB::table('customers')->where('company_id', $companyId)->pluck('id')->all();
        if ($customerIds !== []) {
            DB::table('customer_addresses')->whereIn('customer_id', $customerIds)->delete();
        }

        $count = Customer::withoutTenantScope()->where('company_id', $companyId)->count();
        Customer::withoutTenantScope()->where('company_id', $companyId)->forceDelete();

        return $count;
    }

    private function resetSupplierPayments(int $companyId): int
    {
        $paymentIds = DB::table('supplier_payments')->where('company_id', $companyId)->pluck('id')->all();
        if ($paymentIds !== [] && Schema::hasTable('supplier_payment_purchase')) {
            DB::table('supplier_payment_purchase')->whereIn('supplier_payment_id', $paymentIds)->delete();
        }

        $count = SupplierPayment::withoutGlobalScopes(['tenant', 'branch'])->where('company_id', $companyId)->count();
        SupplierPayment::withoutGlobalScopes(['tenant', 'branch'])->where('company_id', $companyId)->forceDelete();

        return $count;
    }

    /**
     * @return array{transactions: int, expenses: int}
     */
    private function resetFinancialLedger(int $companyId): array
    {
        $transactions = DB::table('transactions')->where('company_id', $companyId)->count();
        DB::table('transactions')->where('company_id', $companyId)->delete();

        if (Schema::hasTable('money_source_fund_movements')) {
            DB::table('money_source_fund_movements')->where('company_id', $companyId)->delete();
        }

        $expenses = DB::table('expenses')->where('company_id', $companyId)->count();
        DB::table('expenses')->where('company_id', $companyId)->delete();

        return [
            'transactions' => $transactions,
            'expenses' => $expenses,
        ];
    }

    private function resetShifts(int $companyId): int
    {
        $shiftIds = DB::table('shifts')->where('company_id', $companyId)->pluck('id')->all();
        if ($shiftIds !== []) {
            DB::table('shift_money_sources')->whereIn('shift_id', $shiftIds)->delete();
        }

        $count = Shift::withoutGlobalScopes()->where('company_id', $companyId)->count();
        Shift::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();

        if (Schema::hasTable('daily_cash_ups')) {
            DB::table('daily_cash_ups')
                ->whereIn('branch_id', DB::table('branches')->where('company_id', $companyId)->pluck('id'))
                ->delete();
        }

        return $count;
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function resetInventory(array $branchIds): int
    {
        $count = DB::table('stock_movements')->whereIn('branch_id', $branchIds)->count();
        DB::table('stock_movements')->whereIn('branch_id', $branchIds)->delete();
        DB::table('branch_stock')->whereIn('branch_id', $branchIds)->delete();

        if (Schema::hasTable('menu_item_stock')) {
            DB::table('menu_item_stock')->whereIn('branch_id', $branchIds)->delete();
        }

        return $count;
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function resetCounters(array $branchIds): void
    {
        if (Schema::hasTable('branch_order_counters')) {
            DB::table('branch_order_counters')->whereIn('branch_id', $branchIds)->delete();
        }

        if (Schema::hasTable('branch_kitchen_counters')) {
            DB::table('branch_kitchen_counters')->whereIn('branch_id', $branchIds)->delete();
        }

        if (Schema::hasTable('branch_supplier_payment_counters')) {
            DB::table('branch_supplier_payment_counters')->whereIn('branch_id', $branchIds)->delete();
        }
    }

    private function zeroSupplierBalances(int $companyId): int
    {
        return DB::table('suppliers')
            ->where('company_id', $companyId)
            ->update(['balance' => 0]);
    }

    private function resetMoneySourceOpeningBalances(int $companyId): int
    {
        return MoneySource::withoutTenantScope()
            ->where('company_id', $companyId)
            ->update(['opening_balance' => 0]);
    }

    private function ensureWalkInCustomer(Company $company): void
    {
        $exists = Customer::withoutTenantScope()
            ->where('company_id', $company->id)
            ->where('is_default', true)
            ->exists();

        if ($exists) {
            return;
        }

        Customer::withoutTenantScope()->create([
            'company_id' => $company->id,
            'name' => 'Walk In',
            'email' => null,
            'phone' => null,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
