<?php

namespace App\Services;

use App\Models\KitchenKot;
use App\Models\Order;
use App\Models\OrderRefundLine;
use App\Models\Table;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class OrderDeleteService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected PosCreditService $posCreditService,
    ) {}

    /**
     * @throws InvalidArgumentException
     * @param  list<int>  $preserveKotIds  Keep these kitchen slips (and their print jobs) after delete — e.g. cancel VOID.
     */
    public function deleteOrder(Order $order, int $userId, array $preserveKotIds = []): void
    {
        DB::transaction(function () use ($order, $userId, $preserveKotIds) {
            $order = Order::withoutGlobalScopes(['tenant', 'branch'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->trashed()) {
                throw new InvalidArgumentException('This order has already been deleted.');
            }

            $order->load([
                'items.menuItem.defaultRecipe.items.ingredient',
                'items.menuItem.variantRecipes.recipe.items.ingredient',
                'items.menuItem.legacyRecipeLines.ingredient',
                'customer',
                'payments',
                'refunds',
            ]);

            $this->reverseInventory($order, $userId);
            $this->reverseFinancials($order);
            $this->reverseCustomerCredit($order);
            $this->releaseTable($order);
            $this->deleteRelatedRecords($order, $preserveKotIds);

            $order->archiveOrderNumber();
            $order->delete();
        });
    }

    protected function reverseInventory(Order $order, int $userId): void
    {
        $inventoryFinalized = Transaction::query()
            ->where('reference_type', 'sale')
            ->where('ref_id', $order->id)
            ->exists();

        if ($inventoryFinalized || in_array($order->payment_status, ['paid', 'partial', 'refunded'], true)) {
            $this->inventoryService->restockOrderForDelete($order, $userId);

            return;
        }

        if ($order->status === 'open' || $order->kitchenKots()->exists()) {
            $this->inventoryService->releaseReservedInventory($order);
        }
    }

    protected function reverseFinancials(Order $order): void
    {
        Transaction::query()
            ->where('company_id', $order->company_id)
            ->where('ref_id', $order->id)
            ->where(function ($query) {
                $query->whereIn('reference_type', ['sale', 'refund'])
                    ->orWhere(function ($foc) {
                        $foc->where('reference_type', 'expense')
                            ->where('notes', 'like', 'FOC Order #%');
                    });
            })
            ->delete();
    }

    protected function reverseCustomerCredit(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        $refundedTotal = (float) $order->refunds->sum('total_refund');
        $originalTotal = (float) $order->total_amount + $refundedTotal;
        $paidAmount = (float) $order->paid_amount;
        $delta = $this->posCreditService->orderBalanceDelta($originalTotal, $paidAmount);

        if (abs($delta) < 0.001) {
            return;
        }

        $customer = $order->customer()->lockForUpdate()->first();
        if ($customer) {
            $this->posCreditService->reverseOrderFromCustomerBalance($customer, $originalTotal, $paidAmount);
        }
    }

    protected function releaseTable(Order $order): void
    {
        if (! $order->table_id) {
            return;
        }

        if ($order->type === 'dine_in' && ($order->status === 'open' || $order->payment_status === 'unpaid')) {
            Table::withoutGlobalScope('branch')
                ->where('id', $order->table_id)
                ->update(['status' => 'available']);
        }
    }

    /**
     * @param  list<int>  $preserveKotIds
     */
    protected function deleteRelatedRecords(Order $order, array $preserveKotIds = []): void
    {
        $preserveKotIds = array_values(array_unique(array_map('intval', $preserveKotIds)));

        $kotIds = $order->kitchenKots()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => in_array($id, $preserveKotIds, true))
            ->values()
            ->all();

        if ($kotIds !== [] && Schema::hasTable('print_jobs')) {
            DB::table('print_jobs')
                ->where('company_id', $order->company_id)
                ->where('document_type', 'kitchen_kot')
                ->where('reference_type', KitchenKot::class)
                ->whereIn('reference_id', $kotIds)
                ->delete();
        }

        if (Schema::hasTable('print_jobs')) {
            DB::table('print_jobs')
                ->where('company_id', $order->company_id)
                ->where('document_type', 'receipt')
                ->where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->delete();
        }

        $refundIds = $order->refunds()->pluck('id')->all();
        if ($refundIds !== []) {
            OrderRefundLine::query()->whereIn('order_refund_id', $refundIds)->delete();
        }

        $order->refunds()->delete();
        if ($kotIds !== []) {
            $order->kitchenKots()->whereIn('id', $kotIds)->delete();
        }
        $order->kitchenDisplayOrders()->delete();
        $order->statusLogs()->delete();
        $order->payments()->delete();
        $order->items()->delete();
    }
}
