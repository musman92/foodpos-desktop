<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class CustomerPayment extends Model
{
    use HasFactory, HasTenantAndBranch, SoftDeletes;

    public const KIND_COLLECTION = 'collection';

    public const KIND_ADVANCE = 'advance';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'money_source_id',
        'created_by',
        'payment_number',
        'kind',
        'payment_date',
        'amount',
        'discount_amount',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function moneySource()
    {
        return $this->belongsTo(MoneySource::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generatePaymentNumber(?int $branchId = null): string
    {
        $branch = $branchId ? Branch::withoutGlobalScopes()->find($branchId) : null;
        $prefix = $branch ? ($branch->code ?? 'CP') : 'CP';
        $companyId = $branch?->company_id ?? Auth::user()?->company_id ?? 0;
        $date = local_now($branchId)->format('Ymd');

        $pattern = sprintf('%s-%d-%s-', $prefix, $companyId, $date);
        $lastNumber = static::withoutGlobalScopes()
            ->where('payment_number', 'like', $pattern.'%')
            ->orderByDesc('payment_number')
            ->value('payment_number');

        $sequence = 1;
        if ($lastNumber && preg_match('/-(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('%s-%d-%s-%04d', $prefix, $companyId, $date, $sequence);
    }
}
