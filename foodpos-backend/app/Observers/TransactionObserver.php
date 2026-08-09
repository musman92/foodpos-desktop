<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\ActivityLogger;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        ActivityLogger::log(
            'transaction.created',
            (int) $transaction->company_id,
            sprintf(
                'Transaction %s %s via %s',
                $transaction->type,
                $transaction->amount,
                $transaction->reference_type ?? 'manual'
            ),
            [
                'amount' => $transaction->amount,
                'type' => $transaction->type,
                'payment_method' => $transaction->payment_method,
                'money_source_id' => $transaction->money_source_id,
                'reference_type' => $transaction->reference_type,
                'ref_id' => $transaction->ref_id,
                'date' => optional($transaction->date)->format('Y-m-d') ?? $transaction->date,
                'shift_id' => $transaction->shift_id,
                'business_date' => optional($transaction->business_date)->format('Y-m-d'),
            ],
            $transaction,
            $transaction->branch_id ? (int) $transaction->branch_id : null,
            $transaction->shift_id ? (int) $transaction->shift_id : null
        );
    }

    public function updated(Transaction $transaction): void
    {
        $changes = ActivityLogger::changes(
            $transaction->getOriginal(),
            $transaction->getAttributes()
        );

        unset(
            $changes['before']['updated_at'],
            $changes['after']['updated_at'],
            $changes['before']['created_at'],
            $changes['after']['created_at']
        );

        if ($changes['before'] === []) {
            return;
        }

        ActivityLogger::log(
            'transaction.updated',
            (int) $transaction->company_id,
            'Transaction updated #'.$transaction->id,
            $changes,
            $transaction,
            $transaction->branch_id ? (int) $transaction->branch_id : null,
            $transaction->shift_id ? (int) $transaction->shift_id : null
        );
    }

    public function deleted(Transaction $transaction): void
    {
        ActivityLogger::log(
            'transaction.deleted',
            (int) $transaction->company_id,
            'Transaction deleted #'.$transaction->id,
            [
                'amount' => $transaction->amount,
                'type' => $transaction->type,
                'money_source_id' => $transaction->money_source_id,
                'reference_type' => $transaction->reference_type,
                'ref_id' => $transaction->ref_id,
            ],
            $transaction,
            $transaction->branch_id ? (int) $transaction->branch_id : null,
            $transaction->shift_id ? (int) $transaction->shift_id : null
        );
    }
}
