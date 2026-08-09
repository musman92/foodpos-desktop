<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\Account;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Services\InventoryService;
use App\Services\PaymentMethodService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrderCreated
{

    protected InventoryService $inventoryService;

    /**
     * Create the event listener.
     */
    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        if ($this->orderAlreadyProcessed($order)) {
            Log::info('Order already processed, skipping duplicate processing', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        // Ensure order has all necessary relationships loaded
        if (!$order->relationLoaded('items')) {
            $order->load([
                'items.menuItem.defaultRecipe.items.ingredient',
                'items.menuItem.variantRecipes.recipe.items.ingredient',
                'items.menuItem.legacyRecipeLines.ingredient',
                'items.deal.menuItems.defaultRecipe.items.ingredient',
                'items.deal.menuItems.variantRecipes.recipe.items.ingredient',
                'items.deal.menuItems.legacyRecipeLines.ingredient',
            ]);
        } else {
            // If items are already loaded, ensure menuItem relationships are loaded
            foreach ($order->items as $item) {
                if ($item->deal_id) {
                    $item->loadMissing([
                        'deal.menuItems.defaultRecipe.items.ingredient',
                        'deal.menuItems.variantRecipes.recipe.items.ingredient',
                        'deal.menuItems.legacyRecipeLines.ingredient',
                    ]);

                    continue;
                }

                if ($item->relationLoaded('menuItem') && $item->menuItem) {
                    // Load recipes only if type is recipe
                    if ($item->menuItem->type === 'recipe') {
                        $item->menuItem->loadMissing([
                            'defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient'
                        ]);
                    }
                } else {
                    // Load menuItem if not loaded
                    $item->load([
                        'menuItem.defaultRecipe.items.ingredient',
                        'menuItem.variantRecipes.recipe.items.ingredient',
                        'menuItem.legacyRecipeLines.ingredient',
                    ]);
                }
            }
        }

        // Ensure items collection is not empty
        if ($order->items->isEmpty()) {
            Log::warning('Order has no items, skipping processing', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            return;
        }

        // Process inventory and create transaction
        // Note: This runs within the controller's transaction, so we don't need another transaction here
        
        // 1. Process inventory deduction for each order item
        // First reserve inventory for all items
        foreach ($order->items as $orderItem) {
            $this->processInventoryDeduction($orderItem, $order->branch_id);
        }

        // Then finalize inventory deduction (moves from reserved to actual deduction)
        // Since POS orders are paid immediately, we finalize right away
        $this->inventoryService->finalizeInventoryDeduction($order);

        // 2. Create transaction record(s)
        $this->createTransactions($order);
        
        Log::info('Order processed successfully', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    protected function orderAlreadyProcessed(Order $order): bool
    {
        if (Transaction::where('reference_type', 'sale')->where('ref_id', $order->id)->exists()) {
            return true;
        }

        if ($order->payment_method === 'foc'
            && Transaction::where('reference_type', 'expense')
                ->where('ref_id', $order->id)
                ->where('notes', 'like', 'FOC Order #%')
                ->exists()) {
            return true;
        }

        if (! $order->relationLoaded('items')) {
            $order->load('items');
        }

        $orderItemIds = $order->items->pluck('id');
        if ($orderItemIds->isEmpty()) {
            return false;
        }

        return StockMovement::where('reference_type', OrderItem::class)
            ->whereIn('reference_id', $orderItemIds)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->where('notes', 'like', '%Finalized for completed order%')
            ->exists();
    }

    /**
     * Process inventory deduction for an order item.
     */
    protected function processInventoryDeduction(OrderItem $orderItem, int $branchId): void
    {
        try {
            $this->inventoryService->reserveInventory($orderItem, $branchId);
            Log::info('Inventory reserved for order item', [
                'order_item_id' => $orderItem->id,
                'menu_item_id' => $orderItem->menu_item_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reserve inventory', [
                'order_item_id' => $orderItem->id,
                'menu_item_id' => $orderItem->menu_item_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create transaction record(s) for the order.
     */
    protected function createTransactions($order): void
    {
        if ($order->payment_method === 'foc') {
            $this->createFocExpenseTransaction($order);

            return;
        }

        if ($order->payment_method === 'split') {
            $this->createSplitTransactions($order);

            return;
        }

        $this->createTransaction($order);
    }

    /**
     * Post complimentary sale as FOC expense (no cash/bank movement, no Sales income).
     */
    protected function createFocExpenseTransaction($order): void
    {
        $existing = Transaction::where('reference_type', 'expense')
            ->where('ref_id', $order->id)
            ->where('notes', 'like', 'FOC Order #%')
            ->first();

        if ($existing) {
            Log::info('FOC expense transaction already exists for order, skipping duplicate creation', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $amount = round((float) $order->total_amount, 2);
        if ($amount <= 0) {
            Log::info('Skipping FOC expense — order total is zero', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $focAccount = Account::ensureSystemAccount((int) $order->company_id, 'FOC', 'expense');

        Transaction::create([
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'account_id' => $focAccount->id,
            'amount' => $amount,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => null,
            'reference_type' => 'expense',
            'date' => $this->transactionDateForOrder($order),
            'ref_id' => $order->id,
            'created_by' => $order->cashier_id,
            'shift_id' => $order->shift_id,
            'notes' => "FOC Order #{$order->order_number}",
        ]);
    }

    protected function createSplitTransactions($order): void
    {
        $existingTransaction = Transaction::where('reference_type', 'sale')
            ->where('ref_id', $order->id)
            ->first();

        if ($existingTransaction) {
            Log::info('Split sale transactions already exist for order, skipping duplicate creation', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $salesAccount = Account::where('company_id', $order->company_id)
            ->where('name', 'Sales')
            ->where('type', 'income')
            ->where('is_active', true)
            ->first();

        if (! $salesAccount) {
            Log::warning('Sales account not found for split order transactions', [
                'order_id' => $order->id,
                'company_id' => $order->company_id,
            ]);

            return;
        }

        $order->loadMissing('payments.moneySource');
        $splitPaymentService = app(\App\Services\PosSplitPaymentService::class);

        foreach ($order->payments as $payment) {
            $amount = (float) $payment->amount;
            if ($amount <= 0) {
                continue;
            }

            Transaction::create([
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'account_id' => $salesAccount->id,
                'amount' => $amount,
                'type' => 'in',
                'payment_method' => $splitPaymentService->transactionPaymentMethod($payment->payment_method),
                'money_source_id' => $payment->money_source_id,
                'reference_type' => 'sale',
                'date' => $this->transactionDateForOrder($order),
                'ref_id' => $order->id,
                'created_by' => $order->cashier_id,
                'shift_id' => $order->shift_id,
                'notes' => "Order #{$order->order_number} (split)",
            ]);
        }
    }

    /**
     * Create a single transaction record for the order.
     */
    protected function createTransaction($order): void
    {
        // Check if transaction already exists for this order to prevent duplicates
        $existingTransaction = Transaction::where('reference_type', 'sale')
            ->where('ref_id', $order->id)
            ->first();

        if ($existingTransaction) {
            Log::info('Transaction already exists for order, skipping duplicate creation', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'existing_transaction_id' => $existingTransaction->id,
            ]);
            return;
        }

        // Get Sales account (income type)
        $salesAccount = Account::where('company_id', $order->company_id)
            ->where('name', 'Sales')
            ->where('type', 'income')
            ->where('is_active', true)
            ->first();

        if (!$salesAccount) {
            Log::warning('Sales account not found for order transaction', [
                'order_id' => $order->id,
                'company_id' => $order->company_id,
            ]);
            return;
        }

        // Map payment method to transaction payment method
        // Transaction table uses: cash, transfer, card, online
        $paymentMethodMap = [
            'cash' => 'cash',
            'card' => 'card',
            'digital_wallet' => 'online',
            'credit' => 'cash',
            'split' => 'cash', // Default to cash for split payments
        ];

        $transactionPaymentMethod = $paymentMethodMap[$order->payment_method] ?? 'cash';

        // Get money source from order (if set) or fallback to payment method mapping
        $moneySource = null;
        if ($order->money_source_id) {
            $moneySource = \App\Models\MoneySource::find($order->money_source_id);
        }
        
        // Fallback to payment method mapping if money source not found
        if (!$moneySource && $order->branch_id) {
            $moneySource = PaymentMethodService::getMoneySourceForPaymentMethod(
                $transactionPaymentMethod,
                $order->branch_id,
                $order->company_id
            );
        }

        $transactionAmount = app(\App\Services\PosCreditService::class)->saleTransactionAmount($order);

        if ($transactionAmount <= 0) {
            Log::info('Skipping sale transaction — no cash received on this order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
            ]);

            return;
        }

        Transaction::create([
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'account_id' => $salesAccount->id,
            'amount' => $transactionAmount,
            'type' => 'in', // Income transaction
            'payment_method' => $transactionPaymentMethod,
            'money_source_id' => $moneySource?->id,
            'reference_type' => 'sale',
            'date' => $this->transactionDateForOrder($order),
            'ref_id' => $order->id,
            'created_by' => $order->cashier_id,
            'shift_id' => $order->shift_id,
            'notes' => "Order #{$order->order_number}",
        ]);
    }

    protected function transactionDateForOrder(Order $order): string
    {
        if (filled($order->business_date)) {
            return substr((string) $order->business_date, 0, 10);
        }

        return $order->created_at->toDateString();
    }
}
