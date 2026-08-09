<?php

namespace App\Services;

use App\Models\MoneySource;
use App\Models\MoneySourceFundMovement;
use App\Models\User;
use App\Support\CurrentShift;
use Illuminate\Support\Facades\DB;

class OwnerWithdrawalService
{
    /**
     * Record owner withdrawal: operational source → Owner Withdrawal bucket.
     *
     * @throws \InvalidArgumentException
     */
    public function record(
        User $user,
        int $fromMoneySourceId,
        float $amount,
        int $branchId,
        string $date,
        ?string $notes = null
    ): MoneySourceFundMovement {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        $fromSource = MoneySource::query()->findOrFail($fromMoneySourceId);

        if (! $fromSource->isSelectableForPayment()) {
            throw new \InvalidArgumentException('Invalid source money source.');
        }

        if ((int) $fromSource->company_id !== (int) $user->company_id) {
            throw new \InvalidArgumentException('Invalid source money source.');
        }

        $ownerBucket = MoneySource::ownerWithdrawalForCompany((int) $user->company_id);
        if (! $ownerBucket) {
            throw new \InvalidArgumentException('Owner Withdrawal source is not configured for this company.');
        }

        $available = $fromSource->getCurrentBalance($branchId, $date);
        if ($amount > $available + 0.0001) {
            throw new \InvalidArgumentException(
                'Insufficient balance in '.$fromSource->name.'. Available: '.format_currency($available)
            );
        }

        return DB::transaction(function () use ($user, $fromSource, $ownerBucket, $amount, $branchId, $date, $notes) {
            return MoneySourceFundMovement::create([
                'company_id' => $user->company_id,
                'branch_id' => $branchId,
                'from_money_source_id' => $fromSource->id,
                'to_money_source_id' => $ownerBucket->id,
                'movement_type' => MoneySourceFundMovement::TYPE_OWNER_WITHDRAWAL,
                'amount' => round($amount, 2),
                'movement_date' => $date,
                'notes' => $notes,
                'created_by' => $user->id,
                'shift_id' => CurrentShift::id($branchId, $user),
            ]);
        });
    }
}
