<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoneySource extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    public const SYSTEM_OWNER_WITHDRAWAL = 'owner_withdrawal';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'opening_balance',
        'active',
        'exclude_from_dashboard_profit',
        'is_system',
        'system_key',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'active' => 'boolean',
        'exclude_from_dashboard_profit' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_money_sources')
            ->withTimestamps();
    }

    public function shifts()
    {
        return $this->belongsToMany(Shift::class, 'shift_money_sources')
            ->withPivot('opening_balance', 'closing_balance', 'expected_balance', 'difference', 'notes')
            ->withTimestamps();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function fundMovementsOut()
    {
        return $this->hasMany(MoneySourceFundMovement::class, 'from_money_source_id');
    }

    public function fundMovementsIn()
    {
        return $this->hasMany(MoneySourceFundMovement::class, 'to_money_source_id');
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    /**
     * Cash, bank, and app sources that can receive POS, purchase, and supplier payments.
     * Excludes the Owner Withdrawal system bucket.
     */
    public function scopeForPayments(Builder $query): Builder
    {
        return $query->operational()
            ->where('type', '!=', 'OWNER_DRAW')
            ->where(function (Builder $inner) {
                $inner->whereNull('system_key')
                    ->orWhere('system_key', '!=', self::SYSTEM_OWNER_WITHDRAWAL);
            });
    }

    public function isOperational(): bool
    {
        return ! $this->is_system;
    }

    public function isSelectableForPayment(): bool
    {
        if (! $this->isOperational()) {
            return false;
        }

        if ($this->isOwnerWithdrawalBucket()) {
            return false;
        }

        return strtoupper((string) $this->type) !== 'OWNER_DRAW';
    }

    public function isOwnerWithdrawalBucket(): bool
    {
        return $this->system_key === self::SYSTEM_OWNER_WITHDRAWAL
            || strtoupper((string) $this->type) === 'OWNER_DRAW';
    }

    public static function ownerWithdrawalForCompany(int $companyId): ?self
    {
        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('system_key', self::SYSTEM_OWNER_WITHDRAWAL)
            ->first();
    }

    /**
     * @param int|null $branchId Branch ID, or null for company-wide
     * @param string|null $asOfDate Calculate balance up to this date (default: today)
     */
    public function getCurrentBalance(?int $branchId = null, ?string $asOfDate = null): float
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $balance = (float) $this->opening_balance;

        $transactionQuery = Transaction::where('money_source_id', $this->id)
            ->whereDate('date', '<=', $asOfDate);

        if ($branchId) {
            $transactionQuery->where('branch_id', $branchId);
        }

        foreach ($transactionQuery->get() as $transaction) {
            if ($transaction->type === 'in') {
                $balance += (float) $transaction->amount;
            } else {
                $balance -= (float) $transaction->amount;
            }
        }

        $movementQuery = MoneySourceFundMovement::query()
            ->where(function (Builder $query) {
                $query->where('from_money_source_id', $this->id)
                    ->orWhere('to_money_source_id', $this->id);
            })
            ->whereDate('movement_date', '<=', $asOfDate);

        if ($branchId) {
            $movementQuery->where('branch_id', $branchId);
        }

        foreach ($movementQuery->get() as $movement) {
            if ((int) $movement->from_money_source_id === (int) $this->id) {
                $balance -= (float) $movement->amount;
            }
            if ((int) $movement->to_money_source_id === (int) $this->id) {
                $balance += (float) $movement->amount;
            }
        }

        return round($balance, 2);
    }

    public function getBranchBalance(int $branchId, ?string $asOfDate = null): float
    {
        return $this->getCurrentBalance($branchId, $asOfDate);
    }

    public function getTransactionHistory(?int $branchId = null, ?int $limit = null)
    {
        $query = $this->transactions()
            ->with(['account', 'branch', 'creator'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($limit) {
            return $query->limit($limit)->get();
        }

        return $query->paginate(20);
    }

    /**
     * Owner withdrawal fund movements into this bucket (or out if from).
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function getFundMovementHistory(?int $branchId = null, ?int $limit = null)
    {
        $query = MoneySourceFundMovement::query()
            ->with(['fromMoneySource', 'toMoneySource', 'branch', 'creator'])
            ->where(function (Builder $q) {
                $q->where('from_money_source_id', $this->id)
                    ->orWhere('to_money_source_id', $this->id);
            })
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($limit) {
            return $query->limit($limit)->get();
        }

        return $query->paginate(20);
    }
}
