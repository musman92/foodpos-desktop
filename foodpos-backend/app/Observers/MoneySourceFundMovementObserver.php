<?php

namespace App\Observers;

use App\Models\MoneySourceFundMovement;
use App\Services\ActivityLogger;

class MoneySourceFundMovementObserver
{
    public function created(MoneySourceFundMovement $movement): void
    {
        ActivityLogger::log(
            'fund_movement.created',
            (int) $movement->company_id,
            sprintf(
                'Fund movement %s: %s',
                $movement->movement_type,
                $movement->amount
            ),
            [
                'movement_type' => $movement->movement_type,
                'amount' => $movement->amount,
                'from_money_source_id' => $movement->from_money_source_id,
                'to_money_source_id' => $movement->to_money_source_id,
                'movement_date' => optional($movement->movement_date)->format('Y-m-d'),
                'shift_id' => $movement->shift_id,
                'notes' => $movement->notes,
            ],
            $movement,
            (int) $movement->branch_id,
            $movement->shift_id ? (int) $movement->shift_id : null
        );
    }

    public function deleted(MoneySourceFundMovement $movement): void
    {
        ActivityLogger::log(
            'fund_movement.deleted',
            (int) $movement->company_id,
            'Fund movement deleted #'.$movement->id,
            [
                'movement_type' => $movement->movement_type,
                'amount' => $movement->amount,
                'from_money_source_id' => $movement->from_money_source_id,
                'to_money_source_id' => $movement->to_money_source_id,
            ],
            $movement,
            (int) $movement->branch_id,
            $movement->shift_id ? (int) $movement->shift_id : null
        );
    }
}
