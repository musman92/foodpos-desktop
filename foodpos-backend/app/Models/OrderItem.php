<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'deal_id',
        'menu_item_id',
        'item_name',
        'quantity',
        'quantity_refunded',
        'unit_price',
        'total_price',
        'variants',
        'addons',
        'special_instructions',
        'status',
        'prepared_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_refunded' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'variants' => 'array',
        'addons' => 'array',
        'prepared_at' => 'datetime',
    ];

    /**
     * Get the order.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the deal (when this line is a deal).
     */
    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Get the menu item (null when this line is a deal).
     */
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Get kitchen display order.
     */
    public function kitchenDisplayOrder()
    {
        return $this->hasOne(KitchenDisplayOrder::class);
    }

    /**
     * Refund line records referencing this order item.
     */
    public function refundLines()
    {
        return $this->hasMany(OrderRefundLine::class);
    }

    /**
     * Billable quantity remaining on this line (ordered minus refunded).
     */
    public function billableQuantity(): string
    {
        $q = (float) $this->quantity - (float) $this->quantity_refunded;

        return (string) max(0, round($q, 2));
    }
}
