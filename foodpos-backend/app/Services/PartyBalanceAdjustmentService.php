<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PartyBalanceAdjustment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PartyBalanceAdjustmentService
{
    public function adjustCustomer(
        Customer $customer,
        float $newBalance,
        User $user,
        ?string $reason = null
    ): PartyBalanceAdjustment {
        return $this->adjust(
            $customer,
            PartyBalanceAdjustment::PARTY_CUSTOMER,
            $newBalance,
            $user,
            $reason
        );
    }

    public function adjustSupplier(
        Supplier $supplier,
        float $newBalance,
        User $user,
        ?string $reason = null
    ): PartyBalanceAdjustment {
        return $this->adjust(
            $supplier,
            PartyBalanceAdjustment::PARTY_SUPPLIER,
            $newBalance,
            $user,
            $reason
        );
    }

    /**
     * @param  Customer|Supplier  $party
     */
    protected function adjust(
        Customer|Supplier $party,
        string $partyType,
        float $newBalance,
        User $user,
        ?string $reason
    ): PartyBalanceAdjustment {
        $newBalance = round($newBalance, 2);
        $previousBalance = round((float) ($party->balance ?? 0), 2);

        if (abs($newBalance - $previousBalance) < 0.001) {
            throw new InvalidArgumentException('New balance must be different from the current balance.');
        }

        return DB::transaction(function () use ($party, $partyType, $previousBalance, $newBalance, $user, $reason) {
            $adjustment = PartyBalanceAdjustment::create([
                'company_id' => $party->company_id,
                'party_type' => $partyType,
                'party_id' => $party->id,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'reason' => $reason,
                'created_by' => $user->id,
            ]);

            $party->balance = $newBalance;
            $party->save();

            return $adjustment;
        });
    }
}
