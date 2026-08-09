<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderRefund extends Model
{
    protected $fillable = [
        'order_id',
        'created_by',
        'subtotal_refund',
        'tax_refund',
        'total_refund',
        'notes',
    ];

    protected $casts = [
        'subtotal_refund' => 'decimal:2',
        'tax_refund' => 'decimal:2',
        'total_refund' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderRefundLine::class);
    }
}
