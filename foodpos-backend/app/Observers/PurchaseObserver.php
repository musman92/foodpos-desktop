<?php

namespace App\Observers;

use App\Models\Purchase;
use App\Services\ActivityLogger;

class PurchaseObserver
{
    public function created(Purchase $purchase): void
    {
        ActivityLogger::log(
            'purchase.created',
            (int) $purchase->company_id,
            'Purchase created '.$purchase->purchase_number,
            [
                'purchase_number' => $purchase->purchase_number,
                'purchase_date' => optional($purchase->purchase_date)->format('Y-m-d'),
                'supplier_id' => $purchase->supplier_id,
                'total_amount' => $purchase->total_amount,
                'paid_amount' => $purchase->paid_amount,
                'payment_status' => $purchase->payment_status,
                'payment_method' => $purchase->payment_method,
                'money_source_id' => $purchase->money_source_id,
                'shift_id' => $purchase->shift_id,
                'business_date' => optional($purchase->business_date)->format('Y-m-d'),
            ],
            $purchase,
            (int) $purchase->branch_id,
            $purchase->shift_id ? (int) $purchase->shift_id : null
        );
    }

    public function updated(Purchase $purchase): void
    {
        $watched = [
            'payment_status',
            'paid_amount',
            'total_amount',
            'money_source_id',
            'payment_method',
            'supplier_id',
        ];

        $before = [];
        $after = [];
        foreach ($watched as $key) {
            $old = $purchase->getOriginal($key);
            $new = $purchase->getAttribute($key);
            if ($old != $new) {
                $before[$key] = $old;
                $after[$key] = $new;
            }
        }

        if ($before === []) {
            return;
        }

        ActivityLogger::log(
            'purchase.updated',
            (int) $purchase->company_id,
            'Purchase updated '.$purchase->purchase_number,
            ['before' => $before, 'after' => $after],
            $purchase,
            (int) $purchase->branch_id,
            $purchase->shift_id ? (int) $purchase->shift_id : null
        );
    }

    public function deleted(Purchase $purchase): void
    {
        ActivityLogger::log(
            'purchase.deleted',
            (int) $purchase->company_id,
            'Purchase deleted '.$purchase->purchase_number,
            [
                'purchase_number' => $purchase->purchase_number,
                'total_amount' => $purchase->total_amount,
                'paid_amount' => $purchase->paid_amount,
            ],
            $purchase,
            (int) $purchase->branch_id,
            $purchase->shift_id ? (int) $purchase->shift_id : null
        );
    }
}
