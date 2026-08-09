<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use App\Traits\StampsBusinessDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes, HasTenantAndBranch, StampsBusinessDate;

    protected $fillable = [
        'company_id',
        'branch_id',
        'supplier_id',
        'created_by',
        'shift_id',
        'purchase_number',
        'purchase_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'returned_amount',
        'payment_method',
        'money_source_id',
        'payment_status',
        'notes',
        'business_date',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2',
        'business_date' => 'date',
    ];

    /**
     * Get the company.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the supplier.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function moneySource()
    {
        return $this->belongsTo(MoneySource::class);
    }

    /**
     * Get the user who created this purchase.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all items in this purchase.
     */
    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get all supplier payments for this purchase.
     */
    public function supplierPayments()
    {
        return $this->belongsToMany(SupplierPayment::class, 'supplier_payment_purchase')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * Get the pending amount (net total after returns minus paid).
     */
    public function getPendingAmountAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) ($this->returned_amount ?? 0) - (float) $this->paid_amount);
    }

    /**
     * Net purchase value after returns.
     */
    public function getNetAmountAttribute(): float
    {
        return max(0, round((float) $this->total_amount - (float) ($this->returned_amount ?? 0), 2));
    }

    public function returns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    /**
     * Check if purchase is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->total_amount;
    }

    /**
     * Supplier payment created when the purchase was first saved (not later allocations).
     */
    public function atPurchaseSupplierPayment(): ?SupplierPayment
    {
        return $this->supplierPayments()
            ->where('notes', 'Payment at purchase #'.$this->purchase_number)
            ->first();
    }

    /**
     * Whether later supplier-payment entries exist for this purchase.
     */
    public function hasAdditionalSupplierPayments(): bool
    {
        $payments = $this->supplierPayments()->get();

        if ($payments->isEmpty()) {
            return false;
        }

        if ($payments->count() === 1 && $this->atPurchaseSupplierPayment()) {
            return false;
        }

        return true;
    }

    /**
     * Release the purchase number for reuse by appending -d01, -d02, … before soft delete.
     */
    public function archivePurchaseNumber(): void
    {
        if (preg_match('/-d\d+$/', (string) $this->purchase_number)) {
            return;
        }

        $base = (string) $this->purchase_number;
        $suffix = 1;

        while (true) {
            $candidate = sprintf('%s-d%02d', $base, $suffix);
            $exists = static::withoutGlobalScopes()
                ->withTrashed()
                ->where('purchase_number', $candidate)
                ->where('id', '!=', $this->id)
                ->exists();

            if (! $exists) {
                $this->forceFill(['purchase_number' => $candidate])->saveQuietly();

                return;
            }

            $suffix++;
        }
    }

    /**
     * Generate the next purchase number for a branch on the current local date.
     */
    public static function generatePurchaseNumber(int $branchId): string
    {
        $branch = Branch::find($branchId);
        $prefix = $branch ? ($branch->code ?? 'PUR') : 'PUR';
        $date = local_now($branchId)->format('Ymd');
        $numberPrefix = sprintf('%s-%s-', $prefix, $date);

        $existingNumbers = static::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('purchase_number', 'like', $numberPrefix.'%')
            ->pluck('purchase_number');

        $maxSequence = 0;
        $pattern = '/^'.preg_quote($numberPrefix, '/').'(\d+)$/';

        foreach ($existingNumbers as $purchaseNumber) {
            if (preg_match($pattern, (string) $purchaseNumber, $matches)) {
                $maxSequence = max($maxSequence, (int) $matches[1]);
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $maxSequence + 1);
    }
}
