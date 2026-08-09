<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use App\Traits\StampsBusinessDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes, HasTenantAndBranch, StampsBusinessDate;

    protected $fillable = [
        'company_id',
        'branch_id',
        'account_id',
        'amount',
        'type',
        'payment_method',
        'money_source_id',
        'reference_type',
        'date',
        'ref_id',
        'created_by',
        'shift_id',
        'is_manual',
        'notes',
        'business_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_manual' => 'boolean',
        'business_date' => 'date',
    ];

    public function isManuallyEntered(): bool
    {
        return (bool) $this->is_manual;
    }

    public function canBeModifiedBy(User $user): bool
    {
        return $this->isManuallyEntered()
            && ($user->isSuperAdmin() || (int) $this->company_id === (int) $user->company_id);
    }

    /**
     * Get the company that owns this transaction.
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
     * Get the account.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the user who created this transaction.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the money source used for this transaction.
     */
    public function moneySource()
    {
        return $this->belongsTo(MoneySource::class);
    }
}
