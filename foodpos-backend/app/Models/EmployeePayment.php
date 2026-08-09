<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeePayment extends Model
{
    use HasTenantAndBranch, SoftDeletes;

    public const KINDS = ['payroll', 'wage', 'advance', 'bonus'];

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'payroll_item_id',
        'account_id',
        'money_source_id',
        'transaction_id',
        'created_by',
        'payment_number',
        'kind',
        'payment_date',
        'amount',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function payrollItem()
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function moneySource()
    {
        return $this->belongsTo(MoneySource::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function advance()
    {
        return $this->hasOne(EmployeeAdvance::class);
    }

    public static function generateNumber(int $companyId, ?int $branchId): string
    {
        $branch = $branchId ? Branch::withoutGlobalScopes()->find($branchId) : null;
        $prefix = $branch?->code ?: 'EP';

        do {
            $number = sprintf(
                '%s-%d-EP-%s-%s',
                $prefix,
                $companyId,
                now()->format('Ymd'),
                Str::upper(Str::random(6))
            );
        } while (static::withoutGlobalScopes()->withTrashed()->where('payment_number', $number)->exists());

        return $number;
    }
}
