<?php

namespace App\Services;

use App\Models\MoneySource;

class PurchasePaymentService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     payment_status: string,
     *     paid_amount: float,
     *     money_source_id: ?int,
     *     payment_method: string
     * }
     */
    public function resolve(array $input, float $totalAmount, int $companyId, ?int $branchId): array
    {
        $selection = (string) ($input['payment_selection'] ?? 'credit');
        $totalAmount = round(max(0, $totalAmount), 2);

        if ($selection === 'credit' || $selection === '') {
            return [
                'payment_status' => 'pending',
                'paid_amount' => 0,
                'money_source_id' => null,
                'payment_method' => 'credit',
            ];
        }

        $moneySourceId = (int) $selection;
        $moneySource = MoneySource::forPayments()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->find($moneySourceId);

        if (! $moneySource) {
            throw new \InvalidArgumentException('Invalid payment source selected. Owner Withdrawal cannot be used for payments.');
        }

        if ($branchId) {
            $assignedToBranch = $moneySource->branches()->where('branches.id', $branchId)->exists();
            if (! $assignedToBranch && $moneySource->branches()->exists()) {
                throw new \InvalidArgumentException('Selected payment source is not available for this branch.');
            }
        }

        $paidAmount = round(min((float) ($input['paid_amount'] ?? 0), $totalAmount), 2);

        if ($paidAmount <= 0) {
            return [
                'payment_status' => 'pending',
                'paid_amount' => 0,
                'money_source_id' => $moneySourceId,
                'payment_method' => PaymentMethodService::paymentMethodFromMoneySourceType($moneySource->type),
            ];
        }

        if ($paidAmount >= $totalAmount) {
            return [
                'payment_status' => 'paid',
                'paid_amount' => $totalAmount,
                'money_source_id' => $moneySourceId,
                'payment_method' => PaymentMethodService::paymentMethodFromMoneySourceType($moneySource->type),
            ];
        }

        return [
            'payment_status' => 'partial',
            'paid_amount' => $paidAmount,
            'money_source_id' => $moneySourceId,
            'payment_method' => PaymentMethodService::paymentMethodFromMoneySourceType($moneySource->type),
        ];
    }
}
