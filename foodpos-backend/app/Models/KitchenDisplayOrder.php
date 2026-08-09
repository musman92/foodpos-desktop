<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenDisplayOrder extends Model
{
    use HasFactory;

    protected $table = 'kitchen_display_orders';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'status',
        'prepared_by',
        'started_at',
        'completed_at',
        'preparation_time',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the order.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the order item.
     */
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Get the user who prepared this item.
     */
    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}

