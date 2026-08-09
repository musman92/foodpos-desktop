<?php

namespace App\Services;

use App\Models\MoneySource;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\JsonResponse;

class PosSplitPaymentService
{
    /**
     * @param  array<int, array<string, mixed>>  $splits
     * @return array{payment_method: string, paid_amount: float, payment_status: string, lines: list<array{money_source: MoneySource, amount: float, given_amount: ?float, change_amount: ?float, payment_method: string}>}|JsonResponse
     */
    public function resolve(array $splits, float $totalAmount, int $companyId, ?int $branchId): array|JsonResponse
    {
        if ($splits === []) {
            return response()->json([
                'success' => false,
                'message' => 'Add at least one payment line.',
            ], 422);
        }

        $totalAmount = round(max(0, $totalAmount), 2);
        $lines = [];
        $paidTotal = 0.0;

        foreach ($splits as $index => $split) {
            $moneySourceId = (int) ($split['money_source_id'] ?? 0);
            $amount = round((float) ($split['amount'] ?? 0), 2);

            if ($moneySourceId <= 0 || $amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Each split payment needs a source and a positive amount.',
                ], 422);
            }

            $moneySource = MoneySource::forPayments()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->find($moneySourceId);

            if (! $moneySource) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment source selected. Owner Withdrawal cannot be used for payments.',
                ], 422);
            }

            if ($branchId) {
                $assignedToBranch = $moneySource->branches()->where('branches.id', $branchId)->exists();
                if (! $assignedToBranch && $moneySource->branches()->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Payment source \"{$moneySource->name}\" is not available for this branch.",
                    ], 422);
                }
            }

            $paymentMethod = $this->paymentMethodFromMoneySource($moneySource);
            $givenAmount = isset($split['given_amount']) ? round((float) $split['given_amount'], 2) : null;
            $changeAmount = isset($split['change_amount']) ? round((float) $split['change_amount'], 2) : null;

            if ($paymentMethod === 'cash') {
                if ($givenAmount !== null && $givenAmount > 0 && $givenAmount >= $amount) {
                    $changeAmount = $changeAmount ?? round(max(0, $givenAmount - $amount), 2);
                } else {
                    $givenAmount = null;
                    $changeAmount = null;
                }
            } else {
                $givenAmount = null;
                $changeAmount = null;
            }

            $lines[] = [
                'money_source' => $moneySource,
                'amount' => $amount,
                'given_amount' => $givenAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod,
                'sort_order' => $index,
            ];

            $paidTotal = round($paidTotal + $amount, 2);
        }

        if (abs($paidTotal - $totalAmount) > 0.009) {
            return response()->json([
                'success' => false,
                'message' => 'Split payments must equal the order total.',
            ], 422);
        }

        return [
            'payment_method' => 'split',
            'paid_amount' => $totalAmount,
            'payment_status' => 'paid',
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array{money_source: MoneySource, amount: float, given_amount: ?float, change_amount: ?float, payment_method: string, sort_order: int}>  $lines
     */
    public function persist(Order $order, array $lines): void
    {
        OrderPayment::query()->where('order_id', $order->id)->delete();

        foreach ($lines as $line) {
            OrderPayment::create([
                'order_id' => $order->id,
                'money_source_id' => $line['money_source']->id,
                'amount' => $line['amount'],
                'given_amount' => $line['given_amount'],
                'change_amount' => $line['change_amount'],
                'payment_method' => $line['payment_method'],
                'sort_order' => $line['sort_order'],
            ]);
        }
    }

    public function paymentMethodFromMoneySource(MoneySource $moneySource): string
    {
        return match ($moneySource->type) {
            'CASH' => 'cash',
            'BANK' => 'card',
            'APP' => 'digital_wallet',
            default => 'cash',
        };
    }

    public function transactionPaymentMethod(string $orderPaymentMethod): string
    {
        return match ($orderPaymentMethod) {
            'cash' => 'cash',
            'card' => 'card',
            'digital_wallet' => 'online',
            default => 'cash',
        };
    }
}
