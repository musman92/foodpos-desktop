<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use App\Traits\StampsBusinessDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use HasTenantAndBranch;
    use StampsBusinessDate;

    protected $fillable = [
        'company_id',
        'branch_id',
        'purchase_id',
        'supplier_id',
        'created_by',
        'shift_id',
        'return_number',
        'return_date',
        'business_date',
        'subtotal',
        'total_amount',
        'settlement_type',
        'payable_reduction_amount',
        'credit_amount',
        'notes',
    ];

    protected $casts = [
        'return_date' => 'date',
        'business_date' => 'date',
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payable_reduction_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public static function generateReturnNumber(int $branchId): string
    {
        $branch = Branch::find($branchId);
        $prefix = $branch ? (($branch->code ?? 'PUR').'-RET') : 'PUR-RET';
        $date = local_now($branchId)->format('Ymd');
        $numberPrefix = sprintf('%s-%s-', $prefix, $date);

        $existingNumbers = static::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('return_number', 'like', $numberPrefix.'%')
            ->pluck('return_number');

        $maxSequence = 0;
        $pattern = '/^'.preg_quote($numberPrefix, '/').'(\d+)$/';

        foreach ($existingNumbers as $returnNumber) {
            if (preg_match($pattern, (string) $returnNumber, $matches)) {
                $maxSequence = max($maxSequence, (int) $matches[1]);
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $maxSequence + 1);
    }
}
