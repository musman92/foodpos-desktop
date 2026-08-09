<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'period_start',
        'period_end',
        'subtotal',
        'tax_amount',
        'total_amount',
        'currency',
        'billing_interval',
        'status',
        'notes',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    protected $appends = [
        'amount_paid',
        'balance_due',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlatformInvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PlatformInvoicePayment::class)->orderByDesc('payment_date');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getAmountPaidAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            return round((float) $this->payments->sum('amount'), 2);
        }

        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, round((float) $this->total_amount - $this->amount_paid, 2));
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true) && $this->amount_paid <= 0;
    }

    public function isOverdue(): bool
    {
        return $this->balance_due > 0
            && $this->due_date !== null
            && $this->due_date->isPast()
            && ! in_array($this->status, ['paid', 'void'], true);
    }

    public function statusLabel(): string
    {
        return config('platform_billing.statuses.'.$this->status, ucfirst($this->status));
    }

    public function billingIntervalLabel(): string
    {
        return \App\Support\TenantBilling::intervalLabel($this->billing_interval);
    }

    public function formattedTotal(): string
    {
        return format_platform_currency((float) $this->total_amount, $this->currency);
    }

    public function formattedBalanceDue(): string
    {
        return format_platform_currency($this->balance_due, $this->currency);
    }

    /**
     * Exclude invoices for demo tenant companies.
     */
    public function scopeForBillableTenants($query)
    {
        return $query->whereHas('company', fn ($q) => $q->where('demo', false));
    }

    public static function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $pattern = "PINV-{$date}-";
        $lastNumber = static::withTrashed()
            ->where('invoice_number', 'like', $pattern.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = 1;
        if ($lastNumber && preg_match('/-(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('%s%04d', $pattern, $sequence);
    }

    public function refreshPaymentStatus(): void
    {
        if ($this->status === 'void') {
            return;
        }

        $paid = $this->amount_paid;
        $total = (float) $this->total_amount;

        if ($paid <= 0) {
            if ($this->status === 'partial' || $this->status === 'paid') {
                $this->status = $this->sent_at ? 'sent' : 'draft';
            }
        } elseif ($paid >= $total) {
            $this->status = 'paid';
        } else {
            $this->status = 'partial';
        }

        $this->save();
    }
}
