<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Support\PartyBalance;

class PosCreditService
{
    public const SETTING_KEY = 'allow_pos_credit_sales';

    public function creditSalesAllowed(?Company $company = null): bool
    {
        if (! $company) {
            $user = auth()->user();
            $company = $user?->company;
        }

        if (! $company) {
            return false;
        }

        return (bool) (($company->settings ?? [])[self::SETTING_KEY] ?? false);
    }

    /**
     * Outstanding amount on this sale (total minus cash received now).
     */
    public function creditAmount(float $totalAmount, float $paidAmount): float
    {
        return round(max(0, $totalAmount - $paidAmount), 2);
    }

    public function orderBalanceDelta(float $totalAmount, float $paidAmount): float
    {
        return round($totalAmount - $paidAmount, 2);
    }

    public function customerCreditAvailable(Customer $customer): float
    {
        return PartyBalance::customerCreditAvailable((float) ($customer->balance ?? 0));
    }

    public function isExplicitCreditPayment(array $validated): bool
    {
        return ($validated['payment_method'] ?? null) === 'credit';
    }

    /**
     * @return string|null Error message when invalid; null when OK.
     */
    public function validateCreditSale(
        bool $creditAllowed,
        float $totalAmount,
        float $paidAmount,
        ?int $customerId,
        bool $explicitCredit = false,
        ?int $companyId = null
    ): ?string {
        $shortfall = $this->creditAmount($totalAmount, $paidAmount);

        if ($shortfall <= 0) {
            return null;
        }

        if ($explicitCredit) {
            if (! $creditAllowed) {
                return 'Credit sales are disabled. Payment must cover the full total.';
            }

            if (! $customerId) {
                return 'Select a registered customer to sell on credit.';
            }

            return null;
        }

        if ($customerId && $companyId) {
            $customer = $this->resolveCustomerForCompany($customerId, $companyId);
            if ($customer && $shortfall <= $this->customerCreditAvailable($customer) + 0.001) {
                return null;
            }
        }

        return 'Payment must cover the full total, select Credit, or choose a customer with sufficient advance.';
    }

    public function resolveCustomerForCompany(?int $customerId, int $companyId): ?Customer
    {
        if (! $customerId) {
            return null;
        }

        return Customer::withoutTenantScope()
            ->where('company_id', $companyId)
            ->where('id', $customerId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Update customer balance after an order: balance += (total − paid).
     */
    public function applyOrderToCustomerBalance(Customer $customer, float $totalAmount, float $paidAmount): void
    {
        $delta = $this->orderBalanceDelta($totalAmount, $paidAmount);
        if (abs($delta) < 0.001) {
            return;
        }

        $customer->balance = round((float) ($customer->balance ?? 0) + $delta, 2);
        $customer->save();
    }

    /**
     * @deprecated Use applyOrderToCustomerBalance()
     */
    public function applyCreditToCustomer(Customer $customer, float $creditAmount): void
    {
        if ($creditAmount <= 0) {
            return;
        }

        $customer->balance = round((float) ($customer->balance ?? 0) + $creditAmount, 2);
        $customer->save();
    }

    /**
     * Reverse customer balance change when an order is voided/deleted.
     */
    public function reverseOrderFromCustomerBalance(Customer $customer, float $totalAmount, float $paidAmount): void
    {
        $delta = $this->orderBalanceDelta($totalAmount, $paidAmount);
        if (abs($delta) < 0.001) {
            return;
        }

        $customer->balance = round((float) ($customer->balance ?? 0) - $delta, 2);
        $customer->save();
    }

    /**
     * @deprecated Use reverseOrderFromCustomerBalance()
     */
    public function reverseCreditFromCustomer(Customer $customer, float $creditAmount): void
    {
        if ($creditAmount <= 0) {
            return;
        }

        $customer->balance = round((float) ($customer->balance ?? 0) - $creditAmount, 2);
        $customer->save();
    }

    /**
     * Attach customer snapshot fields on the order from a Customer record.
     *
     * @param  array<string, mixed>  $orderAttributes
     * @return array<string, mixed>
     */
    public function mergeCustomerSnapshot(array $orderAttributes, ?Customer $customer): array
    {
        if (! $customer) {
            return $orderAttributes;
        }

        $orderAttributes['customer_id'] = $customer->id;

        if (empty($orderAttributes['customer_name'])) {
            $orderAttributes['customer_name'] = $customer->name;
        }
        if (empty($orderAttributes['customer_phone'])) {
            $orderAttributes['customer_phone'] = $customer->phone;
        }
        if (empty($orderAttributes['customer_email'])) {
            $orderAttributes['customer_email'] = $customer->email;
        }

        return $orderAttributes;
    }

    public function saleTransactionAmount(Order $order): float
    {
        $paid = (float) ($order->paid_amount ?? 0);
        $total = (float) $order->total_amount;

        if ($order->payment_status === 'partial') {
            return min($paid, $total);
        }

        return $total;
    }
}
