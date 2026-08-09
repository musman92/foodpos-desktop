<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRefundLine extends Model
{
    protected $fillable = [
        'order_refund_id',
        'order_item_id',
        'quantity',
        'refund_subtotal',
        'refund_tax',
        'restock_inventory',
        'line_notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'refund_subtotal' => 'decimal:2',
        'refund_tax' => 'decimal:2',
        'restock_inventory' => 'boolean',
    ];

    public function orderRefund(): BelongsTo
    {
        return $this->belongsTo(OrderRefund::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
