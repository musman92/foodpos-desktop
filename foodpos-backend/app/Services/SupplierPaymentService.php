<?php

namespace App\Services;

use App\Models\Account;
use App\Models\MoneySource;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CurrentShift;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierPaymentService
{
    /**
     * Record advance paid to a supplier (creates or increases supplier prepayment).
     */
    public function payAdvance(
        Supplier $supplier,
        float $amount,
        int $accountId,
        int $moneySourceId,
        User $user,
        ?int $branchId = null,
        ?string $paymentDate = null,
        ?string $notes = null
    ): SupplierPayment {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Advance amount must be greater than zero.');
        }

        $moneySource = $this->resolveMoneySource($supplier, $moneySourceId, $branchId);
        $account = $this->resolveAccount($supplier, $accountId);
        $paymentMethod = $this->paymentMethodForSource($moneySource);
        $paymentDate = $paymentDate ?? now()->toDateString();

        return DB::transaction(function () use (
            $supplier,
            $amount,
            $account,
            $moneySource,
            $user,
            $branchId,
            $paymentDate,
            $notes,
            $paymentMethod
        ) {
            $payment = $this->createPaymentWithUniqueNumber([
                'company_id' => $supplier->company_id,
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'account_id' => $account->id,
                'money_source_id' => $moneySource->id,
                'created_by' => $user->id,
                'payment_date' => $paymentDate,
                'total_amount' => $amount,
                'payment_method' => $paymentMethod,
                'kind' => SupplierPayment::KIND_ADVANCE,
                'notes' => $notes,
            ], $branchId);

            $supplier->balance = round((float) ($supplier->balance ?? 0) - $amount, 2);
            $supplier->save();

            Transaction::create([
                'company_id' => $supplier->company_id,
                'branch_id' => $branchId,
                'account_id' => $account->id,
                'amount' => $amount,
                'type' => 'out',
                'payment_method' => $paymentMethod,
                'money_source_id' => $moneySource->id,
                'reference_type' => 'purchase',
                'date' => $paymentDate,
                'ref_id' => $payment->id,
                'created_by' => $user->id,
                'shift_id' => CurrentShift::id($branchId, $user),
                'notes' => "Supplier advance #{$payment->payment_number}",
            ]);

            return $payment->load(['supplier', 'branch', 'account', 'moneySource', 'creator']);
        });
    }

    /**
     * Delete a supplier payment and reverse supplier balance, purchase allocations, and ledger.
     *
     * @throws InvalidArgumentException
     */
    public function deletePayment(SupplierPayment $payment): void
    {
        if ($payment->trashed()) {
            throw new InvalidArgumentException('This payment has already been deleted.');
        }

        DB::transaction(function () use ($payment) {
            $payment = SupplierPayment::withoutGlobalScopes(['tenant', 'branch'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment->load(['purchases']);

            if ($payment->kind === SupplierPayment::KIND_PAYMENT) {
                foreach ($payment->purchases as $purchase) {
                    $allocated = (float) $purchase->pivot->amount;
                    $newPaid = round(max(0, (float) $purchase->paid_amount - $allocated), 2);
                    $purchase->paid_amount = $newPaid;

                    if ($newPaid <= 0.01) {
                        $purchase->paid_amount = 0;
                        $purchase->payment_status = 'pending';
                    } elseif ($newPaid < (float) $purchase->total_amount - 0.01) {
                        $purchase->payment_status = 'partial';
                    } else {
                        $purchase->payment_status = 'paid';
                    }

                    $purchase->save();
                }

                $payment->purchases()->detach();
            }

            $supplier = Supplier::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->supplier_id);
            $supplier->balance = round((float) $supplier->balance + (float) $payment->total_amount, 2);
            $supplier->save();

            Transaction::query()
                ->where('company_id', $payment->company_id)
                ->where('ref_id', $payment->id)
                ->where('reference_type', 'purchase')
                ->delete();

            $payment->delete();
        });
    }

    protected function resolveMoneySource(Supplier $supplier, int $moneySourceId, ?int $branchId): MoneySource
    {
        $moneySource = MoneySource::withoutTenantScope()
            ->forPayments()
            ->where('company_id', $supplier->company_id)
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

    protected function resolveAccount(Supplier $supplier, int $accountId): Account
    {
        $account = Account::withoutTenantScope()
            ->where('company_id', $supplier->company_id)
            ->where('id', $accountId)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw new InvalidArgumentException('Invalid or inactive account.');
        }

        return $account;
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
     * @param  array<string, mixed>  $attributes
     */
    protected function createPaymentWithUniqueNumber(array $attributes, ?int $branchId): SupplierPayment
    {
        $lastDuplicate = null;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $attributes['payment_number'] = SupplierPayment::allocatePaymentNumber($branchId);

            try {
                return SupplierPayment::create($attributes);
            } catch (\Illuminate\Database\QueryException $e) {
                if (! SupplierPayment::isDuplicateKeyException($e)) {
                    throw $e;
                }
                $lastDuplicate = $e;
            }
        }

        throw $lastDuplicate ?? new \RuntimeException('Unable to allocate a unique supplier payment number.');
    }
}
