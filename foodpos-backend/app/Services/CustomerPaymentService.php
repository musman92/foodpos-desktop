<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CurrentShift;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerPaymentService
{
    /**
     * Record a payment received from a customer (reduces balance owed).
     * Optional discount/write-off clears additional balance without cash received.
     * Overpayment creates customer advance (negative balance).
     */
    public function receivePayment(
        Customer $customer,
        float $amount,
        int $moneySourceId,
        User $user,
        ?int $branchId = null,
        ?string $paymentDate = null,
        ?string $notes = null,
        float $discountAmount = 0,
        ?string $paymentNumber = null
    ): CustomerPayment {
        $amount = round($amount, 2);
        $discountAmount = round(max(0, $discountAmount), 2);
        $totalApplied = round($amount + $discountAmount, 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if ($totalApplied <= 0) {
            throw new InvalidArgumentException('Amount received plus discount must be greater than zero.');
        }

        $moneySource = $this->resolveMoneySource($customer, $moneySourceId, $branchId);
        $salesAccount = $this->resolveSalesAccount($customer);
        $paymentMethod = $this->paymentMethodForSource($moneySource);
        $paymentDate = $paymentDate ?? now()->toDateString();

        return DB::transaction(function () use (
            $customer,
            $amount,
            $discountAmount,
            $totalApplied,
            $moneySource,
            $user,
            $branchId,
            $paymentDate,
            $notes,
            $salesAccount,
            $paymentMethod,
            $paymentNumber
        ) {
            $payment = CustomerPayment::create([
                'company_id' => $customer->company_id,
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'money_source_id' => $moneySource->id,
                'created_by' => $user->id,
                'payment_number' => $paymentNumber ?? CustomerPayment::generatePaymentNumber($branchId),
                'kind' => CustomerPayment::KIND_COLLECTION,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'discount_amount' => $discountAmount,
                'notes' => $notes,
            ]);

            $balance = round((float) ($customer->balance ?? 0), 2);
            $applyToOrders = round(min($totalApplied, max(0, $balance)), 2);
            if ($applyToOrders > 0) {
                $this->applyToOutstandingOrders($customer, $applyToOrders);
            }

            $customer->balance = round((float) $customer->balance - $totalApplied, 2);
            $customer->save();

            $transactionNotes = "Customer payment #{$payment->payment_number} — {$customer->name}";
            if ($discountAmount > 0) {
                $transactionNotes .= ' (includes '.number_format($discountAmount, 2).' discount/write-off)';
            }

            Transaction::create([
                'company_id' => $customer->company_id,
                'branch_id' => $branchId,
                'account_id' => $salesAccount->id,
                'amount' => $amount,
                'type' => 'in',
                'payment_method' => $paymentMethod,
                'money_source_id' => $moneySource->id,
                'reference_type' => 'customer_payment',
                'date' => $paymentDate,
                'ref_id' => $payment->id,
                'created_by' => $user->id,
                'shift_id' => CurrentShift::id($branchId, $user),
                'notes' => $transactionNotes,
            ]);

            return $payment->load(['customer', 'branch', 'moneySource', 'creator']);
        });
    }

    /**
     * Record advance received from a customer (creates or increases customer credit).
     */
    public function receiveAdvance(
        Customer $customer,
        float $amount,
        int $moneySourceId,
        User $user,
        ?int $branchId = null,
        ?string $paymentDate = null,
        ?string $notes = null,
        ?string $paymentNumber = null
    ): CustomerPayment {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Advance amount must be greater than zero.');
        }

        $moneySource = $this->resolveMoneySource($customer, $moneySourceId, $branchId);
        $salesAccount = $this->resolveSalesAccount($customer);
        $paymentMethod = $this->paymentMethodForSource($moneySource);
        $paymentDate = $paymentDate ?? now()->toDateString();

        return DB::transaction(function () use (
            $customer,
            $amount,
            $moneySource,
            $user,
            $branchId,
            $paymentDate,
            $notes,
            $salesAccount,
            $paymentMethod,
            $paymentNumber
        ) {
            $payment = CustomerPayment::create([
                'company_id' => $customer->company_id,
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'money_source_id' => $moneySource->id,
                'created_by' => $user->id,
                'payment_number' => $paymentNumber ?? CustomerPayment::generatePaymentNumber($branchId),
                'kind' => CustomerPayment::KIND_ADVANCE,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'discount_amount' => 0,
                'notes' => $notes,
            ]);

            $customer->balance = round((float) ($customer->balance ?? 0) - $amount, 2);
            $customer->save();

            Transaction::create([
                'company_id' => $customer->company_id,
                'branch_id' => $branchId,
                'account_id' => $salesAccount->id,
                'amount' => $amount,
                'type' => 'in',
                'payment_method' => $paymentMethod,
                'money_source_id' => $moneySource->id,
                'reference_type' => 'customer_payment',
                'date' => $paymentDate,
                'ref_id' => $payment->id,
                'created_by' => $user->id,
                'shift_id' => CurrentShift::id($branchId, $user),
                'notes' => "Customer advance #{$payment->payment_number} — {$customer->name}",
            ]);

            return $payment->load(['customer', 'branch', 'moneySource', 'creator']);
        });
    }

    /**
     * Delete a customer payment and reverse customer balance, order allocations, and ledger.
     *
     * @throws InvalidArgumentException
     */
    public function deletePayment(CustomerPayment $payment): void
    {
        if ($payment->trashed()) {
            throw new InvalidArgumentException('This payment has already been deleted.');
        }

        DB::transaction(function () use ($payment) {
            $payment = CustomerPayment::withoutGlobalScopes(['tenant', 'branch'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $customer = Customer::withoutTenantScope()->lockForUpdate()->findOrFail($payment->customer_id);
            $totalApplied = round((float) $payment->amount + (float) $payment->discount_amount, 2);

            if ($payment->kind === CustomerPayment::KIND_COLLECTION && $totalApplied > 0.0001) {
                $balanceBeforePayment = round((float) $customer->balance + $totalApplied, 2);
                $orderReversal = round(min($totalApplied, max(0, $balanceBeforePayment)), 2);
                $this->reverseOrderApplications($customer, $orderReversal);
            }

            $customer->balance = round((float) $customer->balance + $totalApplied, 2);
            $customer->save();

            Transaction::query()
                ->where('company_id', $payment->company_id)
                ->where('ref_id', $payment->id)
                ->where('reference_type', 'customer_payment')
                ->delete();

            $payment->delete();
        });
    }

    protected function resolveMoneySource(Customer $customer, int $moneySourceId, ?int $branchId): MoneySource
    {
        $moneySource = MoneySource::withoutTenantScope()
            ->forPayments()
            ->where('company_id', $customer->company_id)
            ->where('id', $moneySourceId)
            ->where('active', true)
            ->first();

        if (! $moneySource) {
            throw new InvalidArgumentException('Invalid or inactive payment source. Owner Withdrawal cannot be used for payments.');
        }

        if ($branchId) {
            $attached = $moneySource->branches()->where('branches.id', $branchId)->exists();
            if (! $attached && $moneySource->branches()->exists()) {
                throw new InvalidArgumentException('This payment source is not available for the selected branch.');
            }
        }

        return $moneySource;
    }

    protected function resolveSalesAccount(Customer $customer): Account
    {
        $salesAccount = Account::withoutTenantScope()
            ->where('company_id', $customer->company_id)
            ->where('name', 'Sales')
            ->where('type', 'income')
            ->where('is_active', true)
            ->first();

        if (! $salesAccount) {
            throw new InvalidArgumentException('Sales income account is not configured for this company.');
        }

        return $salesAccount;
    }

    protected function paymentMethodForSource(MoneySource $moneySource): string
    {
        return match ($moneySource->type) {
            'CASH' => 'cash',
            'BANK' => 'card',
            'APP' => 'online',
            default => 'cash',
        };
    }

    /**
     * Apply payment (cash + discount) to oldest partial orders for this customer.
     */
    protected function applyToOutstandingOrders(Customer $customer, float $totalToApply): void
    {
        if ($totalToApply <= 0) {
            return;
        }

        $remaining = $totalToApply;

        $orders = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->withOutstandingPayment()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($orders as $order) {
            if ($remaining <= 0.001) {
                break;
            }

            $outstanding = $order->outstandingAmount();
            if ($outstanding <= 0) {
                continue;
            }

            $apply = round(min($remaining, $outstanding), 2);
            $newPaid = round((float) ($order->paid_amount ?? 0) + $apply, 2);
            $order->paid_amount = $newPaid;

            if ($newPaid >= (float) $order->total_amount - 0.001) {
                $order->payment_status = 'paid';
                $order->paid_amount = (float) $order->total_amount;
            }

            $order->save();
            $remaining = round($remaining - $apply, 2);
        }
    }

    /**
     * Undo payment allocations from customer orders (newest orders first).
     */
    protected function reverseOrderApplications(Customer $customer, float $amountToReverse): void
    {
        if ($amountToReverse <= 0.0001) {
            return;
        }

        $remaining = $amountToReverse;

        $orders = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('paid_amount', '>', 0)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($orders as $order) {
            if ($remaining <= 0.0001) {
                break;
            }

            $paid = (float) $order->paid_amount;
            if ($paid <= 0.0001) {
                continue;
            }

            $take = round(min($remaining, $paid), 2);
            $newPaid = round($paid - $take, 2);
            $order->paid_amount = $newPaid;

            if ($newPaid <= 0.0001) {
                $order->paid_amount = 0;
                $order->payment_status = 'unpaid';
            } elseif ($newPaid < (float) $order->total_amount - 0.01) {
                $order->payment_status = 'partial';
            } else {
                $order->payment_status = 'paid';
                $order->paid_amount = (float) $order->total_amount;
            }

            $order->save();
            $remaining = round($remaining - $take, 2);
        }
    }
}
