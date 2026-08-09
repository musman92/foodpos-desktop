<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformInvoicePayment extends Model
{
    protected $fillable = [
        'platform_invoice_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PlatformInvoice::class, 'platform_invoice_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function paymentMethodLabel(): string
    {
        return config('platform_billing.payment_methods.'.$this->payment_method, ucfirst(str_replace('_', ' ', $this->payment_method)));
    }
}
