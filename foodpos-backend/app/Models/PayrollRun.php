<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PayrollRun extends Model
{
    use HasTenantAndBranch, SoftDeletes;

    public const STATUSES = ['draft', 'finalized', 'partially_paid', 'paid'];

    protected $fillable = [
        'company_id',
        'branch_id',
        'payroll_number',
        'pay_frequency',
        'period_start',
        'period_end',
        'status',
        'employee_count',
        'gross_total',
        'deduction_total',
        'advance_recovery_total',
        'net_total',
        'paid_total',
        'generated_by',
        'finalized_by',
        'finalized_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'gross_total' => 'decimal:2',
        'deduction_total' => 'decimal:2',
        'advance_recovery_total' => 'decimal:2',
        'net_total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public static function generateNumber(int $companyId): string
    {
        do {
            $number = sprintf(
                'PR-%d-%s-%s',
                $companyId,
                now()->format('Ymd'),
                Str::upper(Str::random(6))
            );
        } while (static::withoutGlobalScopes()->withTrashed()->where('payroll_number', $number)->exists());

        return $number;
    }
}
