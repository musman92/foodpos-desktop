<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\ActivityLogger;

class OrderObserver
{
    public function created(Order $order): void
    {
        ActivityLogger::log(
            'order.created',
            (int) $order->company_id,
            'Order created '.$order->order_number,
            [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'money_source_id' => $order->money_source_id,
                'total_amount' => $order->total_amount,
                'paid_amount' => $order->paid_amount,
                'type' => $order->type,
                'shift_id' => $order->shift_id,
                'business_date' => optional($order->business_date)->format('Y-m-d'),
            ],
            $order,
            (int) $order->branch_id,
            $order->shift_id ? (int) $order->shift_id : null
        );
    }

    public function updated(Order $order): void
    {
        $watched = [
            'status',
            'payment_status',
            'payment_method',
            'money_source_id',
            'total_amount',
            'paid_amount',
            'discount_amount',
            'subtotal',
            'tax_amount',
        ];

        $before = [];
        $after = [];
        foreach ($watched as $key) {
            $old = $order->getOriginal($key);
            $new = $order->getAttribute($key);
            if ($old != $new) {
                $before[$key] = $old;
                $after[$key] = $new;
            }
        }

        if ($before === []) {
            return;
        }

        ActivityLogger::log(
            'order.updated',
            (int) $order->company_id,
            'Order updated '.$order->order_number,
            ['before' => $before, 'after' => $after],
            $order,
            (int) $order->branch_id,
            $order->shift_id ? (int) $order->shift_id : null
        );
    }

    public function deleted(Order $order): void
    {
        ActivityLogger::log(
            'order.deleted',
            (int) $order->company_id,
            'Order deleted '.$order->order_number,
            [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'payment_status' => $order->payment_status,
            ],
            $order,
            (int) $order->branch_id,
            $order->shift_id ? (int) $order->shift_id : null
        );
    }
}
