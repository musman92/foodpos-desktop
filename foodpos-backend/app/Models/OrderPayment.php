<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    protected $fillable = [
        'order_id',
        'money_source_id',
        'amount',
        'given_amount',
        'change_amount',
        'payment_method',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'given_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function moneySource(): BelongsTo
    {
        return $this->belongsTo(MoneySource::class);
    }
}
